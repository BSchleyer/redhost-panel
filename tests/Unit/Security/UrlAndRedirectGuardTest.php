<?php

declare(strict_types=1);

namespace Nebrix\Tests\Unit\Security;

use Nebrix\Http\IpRange;
use Nebrix\Security\RedirectGuard;
use Nebrix\Security\UnsafeUrlException;
use Nebrix\Security\UrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlAndRedirectGuardTest extends TestCase
{
    private function guard(bool $allowHttp = false, bool $allowLoopback = false, array $dns = []): UrlGuard
    {
        return new UrlGuard($allowHttp, $allowLoopback, static fn (string $host): array => $dns[$host] ?? []);
    }

    public function testAcceptsPrivateNetworkHttpsTargets(): void
    {
        $result = $this->guard(dns: ['pve.internal.example' => ['10.0.0.5']])->validateUrl('https://pve.internal.example:8006/api2/json');

        self::assertSame('10.0.0.5', $result['ip']);
        self::assertSame(8006, $result['port']);
        self::assertSame('pve.internal.example', $result['host']);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeUrls(): iterable
    {
        yield 'cloud metadata' => ['https://169.254.169.254/latest/meta-data'];
        yield 'loopback' => ['https://127.0.0.1:8443'];
        yield 'ipv6 loopback' => ['https://[::1]/'];
        yield 'mapped metadata' => ['https://[::ffff:169.254.169.254]/'];
        yield 'mapped public ipv4' => ['https://[::ffff:203.0.113.10]/'];
        yield 'ipv4-compatible loopback' => ['https://[::127.0.0.1]/'];
        yield 'nat64 metadata' => ['https://[64:ff9b::a9fe:a9fe]/'];
        yield '6to4 loopback' => ['https://[2002:7f00:1::1]/'];
        yield 'teredo' => ['https://[2001:0:4136:e378:8000:63bf:3fff:fdd2]/'];
        yield 'credentials in url' => ['https://user:pass@panel.example.com'];
        yield 'plain http' => ['http://panel.example.com'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'gopher' => ['gopher://panel.example.com'];
        yield 'unresolvable' => ['https://does-not-resolve.example'];
        yield 'dns to metadata' => ['https://rebind.example'];
    }

    #[DataProvider('unsafeUrls')]
    public function testRejectsUnsafeTargets(string $url): void
    {
        $this->expectException(UnsafeUrlException::class);
        $this->guard(dns: ['rebind.example' => ['169.254.169.254'], 'panel.example.com' => ['203.0.113.10']])->validateUrl($url);
    }

    public function testLoopbackAndHttpCanBeExplicitlyAllowed(): void
    {
        $result = $this->guard(allowHttp: true, allowLoopback: true)->validateUrl('http://127.0.0.1:8080');

        self::assertSame('127.0.0.1', $result['ip']);
    }

    public function testIpv6LoopbackCanBeAllowedButEmbeddedIpv4FormsCannot(): void
    {
        $guard = $this->guard(allowLoopback: true);
        self::assertSame('::1', $guard->validateUrl('https://[::1]:8443')['ip']);
        self::assertSame('2001:db8::10', $guard->validateUrl('https://[2001:db8::10]/')['ip'], 'Ordinary global IPv6 addresses are allowed.');

        $this->expectException(UnsafeUrlException::class);
        $guard->validateUrl('https://[::127.0.0.1]/');
    }

    public function testMetadataStaysBlockedEvenWhenLoopbackIsAllowed(): void
    {
        $this->expectException(UnsafeUrlException::class);
        $this->guard(allowHttp: true, allowLoopback: true)->validateUrl('http://169.254.169.254');
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function redirects(): iterable
    {
        yield 'relative path' => ['/services/5', '/services/5'];
        yield 'with query' => ['/invoices?page=2', '/invoices?page=2'];
        yield 'absolute url' => ['https://evil.example/', '/'];
        yield 'protocol relative' => ['//evil.example/', '/'];
        yield 'backslash trick' => ['/\\evil.example', '/'];
        yield 'header injection' => ["/ok\r\nSet-Cookie: x=1", '/'];
        yield 'javascript' => ['javascript:alert(1)', '/'];
        yield 'not a string' => [['/x'], '/'];
    }

    #[DataProvider('redirects')]
    public function testRedirectGuard(mixed $input, string $expected): void
    {
        self::assertSame($expected, RedirectGuard::sanitize($input));
    }

    public function testIpRange(): void
    {
        self::assertTrue(IpRange::contains('10.0.0.0/8', '10.20.30.40'));
        self::assertFalse(IpRange::contains('10.0.0.0/8', '11.0.0.1'));
        self::assertTrue(IpRange::contains('192.168.1.0/25', '192.168.1.127'));
        self::assertFalse(IpRange::contains('192.168.1.0/25', '192.168.1.128'));
        self::assertTrue(IpRange::contains('2001:db8::/32', '2001:db8:1::1'));
        self::assertFalse(IpRange::contains('2001:db8::/32', '10.0.0.1'));
        self::assertTrue(IpRange::contains('203.0.113.7', '203.0.113.7'));
        self::assertFalse(IpRange::contains('not-an-ip', '203.0.113.7'));
    }
}
