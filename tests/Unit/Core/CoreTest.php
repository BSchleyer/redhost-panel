<?php

declare(strict_types=1);

namespace Nebrix\Tests\Unit\Core;

use Nebrix\Billing\Money;
use Nebrix\Core\Container;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Http\Router;
use Nebrix\Security\Csrf;
use Nebrix\Session\Session;
use PHPUnit\Framework\TestCase;

final class CoreTest extends TestCase
{
    public function testContainerAutowiresAndShares(): void
    {
        $container = new Container();
        $a = $container->get(\Nebrix\Core\Clock::class);

        self::assertSame($a, $container->get(\Nebrix\Core\Clock::class));
    }

    public function testRouterMatchesParametersAndMethods(): void
    {
        $router = new Router();
        $router->group(['prefix' => '/admin', 'middleware' => ['auth', 'staff']], static function (Router $r): void {
            $r->get('/customers/{id:\d+}', [\stdClass::class, 'show'], ['middleware' => ['permission:customers.view']]);
        });

        [$route, $params] = $router->match('GET', '/admin/customers/42');
        self::assertSame(['id' => '42'], $params);
        self::assertSame(['auth', 'staff', 'permission:customers.view'], $route->middleware);

        try {
            $router->match('POST', '/admin/customers/42');
            self::fail('Expected 405');
        } catch (HttpException $exception) {
            self::assertSame(405, $exception->statusCode());
        }

        $this->expectException(HttpException::class);
        $router->match('GET', '/admin/customers/abc');
    }

    public function testClientIpIgnoresForwardedHeadersFromUntrustedPeers(): void
    {
        $spoofed = new Request('GET', '/', server: ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        self::assertSame('198.51.100.7', $spoofed->clientIp());

        $proxied = new Request(
            'GET',
            '/',
            server: ['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'],
            trustedProxies: ['10.0.0.0/8'],
        );
        self::assertSame('203.0.113.9', $proxied->clientIp());
        self::assertTrue($proxied->isSecure());
    }

    public function testResponseRejectsHeaderInjection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Response())->setHeader('Location', "/x\r\nSet-Cookie: evil=1");
    }

    public function testCsrfTokens(): void
    {
        $session = new Session(Session::generateId(), [], true, null, time(), time());
        $token = Csrf::token($session);

        self::assertSame(64, strlen($token));
        self::assertSame($token, Csrf::token($session));
        self::assertTrue(Csrf::validate($session, $token));
        self::assertFalse(Csrf::validate($session, 'x' . substr($token, 1)));
        self::assertFalse(Csrf::validate($session, null));
        self::assertFalse(Csrf::validate($session, ['array']));

        Csrf::rotate($session);
        self::assertFalse(Csrf::validate($session, $token));
    }

    public function testSessionFlashLifecycleAndRegeneration(): void
    {
        $session = new Session(Session::generateId(), [], true, null, time(), time());
        $originalId = $session->id();
        $session->flash('success', 'Saved');
        self::assertSame('Saved', $session->getFlash('success'));

        $session->ageFlash(); // next request
        self::assertSame('Saved', $session->getFlash('success'));
        $session->ageFlash(); // request after that
        self::assertNull($session->getFlash('success'));

        $session->regenerate();
        self::assertNotSame($originalId, $session->id());
        self::assertSame($originalId, $session->previousId());
    }

    public function testMoney(): void
    {
        self::assertSame(1250, Money::parse('12.50'));
        self::assertSame(1205, Money::parse('12,05'));
        self::assertSame(1200, Money::parse('12'));
        self::assertSame('12.05', Money::toDecimal(1205));
        self::assertSame('-0.99', Money::toDecimal(-99));
        self::assertSame(190, Money::tax(1000, 1900));
        self::assertSame(2, Money::tax(10, 1900)); // 1.9 rounds half up to 2
        self::assertSame(100, Money::discount(1000, 1000));
        self::assertSame('€12.50', Money::format(1250, 'EUR'));
        self::assertSame('12,50 €', Money::format(1250, 'EUR', 'de'));
        self::assertSame('$1,234.56', Money::format(123456, 'USD'));
    }
}
