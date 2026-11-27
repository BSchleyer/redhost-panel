<?php

declare(strict_types=1);

namespace Nebrix\Security;

use Nebrix\Http\IpRange;

/**
 * Validates outbound URLs/hosts configured by administrators (SSRF defense,
 * legacy finding M-04).
 *
 * - Only http(s) URLs without embedded credentials.
 * - Plain HTTP is rejected unless explicitly allowed (credentials would be
 *   sent in clear text).
 * - Hostnames are resolved; cloud metadata, link-local, multicast and
 *   unspecified ranges are always rejected; loopback only when allowed.
 * - The resolved address is returned so callers can pin the connection to it
 *   (prevents DNS rebinding between validation and request).
 */
class UrlGuard
{
    /** Ranges that are never valid targets. */
    private const ALWAYS_BLOCKED = [
        '0.0.0.0/8',
        '169.254.0.0/16',       // link-local incl. cloud metadata 169.254.169.254
        '100.100.100.200/32',   // Alibaba Cloud metadata
        '224.0.0.0/4',          // multicast
        '240.0.0.0/4',          // reserved / broadcast
        '::/128',
        'fe80::/10',            // IPv6 link-local
        'ff00::/8',             // IPv6 multicast
        'fd00:ec2::254/128',    // AWS IPv6 metadata
        // IPv6 forms that embed an IPv4 address: they can point at blocked or
        // loopback IPv4 targets through translation, so they are never used
        // for integrations (use the plain IPv4 address instead).
        '::ffff:0:0/96',        // IPv4-mapped
        '64:ff9b::/96',         // NAT64 well-known prefix
        '64:ff9b:1::/48',       // NAT64 local-use prefix
        '2002::/16',            // 6to4
        '2001::/32',            // Teredo
    ];

    /** IPv4-compatible IPv6 (::a.b.c.d, deprecated); ::1 is handled as loopback. */
    private const IPV4_COMPATIBLE = '::/96';

    private const LOOPBACK = ['127.0.0.0/8', '::1/128'];

    /** @var \Closure(string): list<string> */
    private \Closure $resolver;

    /**
     * @param (\Closure(string): list<string>)|null $resolver custom DNS resolver (tests)
     */
    public function __construct(
        private readonly bool $allowHttp = false,
        private readonly bool $allowLoopback = false,
        ?\Closure $resolver = null,
    ) {
        $this->resolver = $resolver ?? self::systemResolver(...);
    }

    /**
     * @return array{scheme: string, host: string, port: int, path: string, ip: string, url: string}
     *
     * @throws UnsafeUrlException
     */
    public function validateUrl(string $url): array
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new UnsafeUrlException('The URL is not valid.');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['https', 'http'], true)) {
            throw new UnsafeUrlException('Only http(s) URLs are allowed.');
        }
        if ($scheme === 'http' && !$this->allowHttp) {
            throw new UnsafeUrlException('Plain HTTP is not allowed for API connections; use HTTPS.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException('Credentials must not be embedded in the URL.');
        }
        if (isset($parts['fragment'])) {
            throw new UnsafeUrlException('The URL must not contain a fragment.');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $ip = $this->validateHost($host);

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'path' => rtrim($parts['path'] ?? '', '/'),
            'ip' => $ip,
            'url' => rtrim($url, '/'),
        ];
    }

    /**
     * Validates a bare hostname or IP (used for raw TCP integrations such as
     * TeamSpeak ServerQuery) and returns the resolved IP address.
     *
     * @throws UnsafeUrlException
     */
    public function validateHost(string $host): string
    {
        $host = strtolower(trim($host, "[] \t\n\r"));
        if ($host === '') {
            throw new UnsafeUrlException('A host name is required.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            if (preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/', $host) !== 1) {
                throw new UnsafeUrlException('The host name is not valid.');
            }
            $addresses = ($this->resolver)($host);
            if ($addresses === []) {
                throw new UnsafeUrlException(sprintf('The host "%s" could not be resolved.', $host));
            }
        }

        foreach ($addresses as $address) {
            $this->assertAllowedIp($address);
        }

        return $addresses[0];
    }

    /**
     * @throws UnsafeUrlException
     */
    public function assertAllowedIp(string $ip): void
    {
        foreach (self::ALWAYS_BLOCKED as $range) {
            if (IpRange::contains($range, $ip)) {
                throw new UnsafeUrlException('The target address is in a blocked network range.');
            }
        }
        if (IpRange::contains(self::IPV4_COMPATIBLE, $ip) && !IpRange::contains('::1/128', $ip)) {
            throw new UnsafeUrlException('The target address is in a blocked network range.');
        }

        if (!$this->allowLoopback) {
            foreach (self::LOOPBACK as $range) {
                if (IpRange::contains($range, $ip)) {
                    throw new UnsafeUrlException('Loopback addresses are not allowed for integrations.');
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function systemResolver(string $host): array
    {
        $addresses = [];
        $v4 = gethostbynamel($host);
        if (is_array($v4)) {
            $addresses = $v4;
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
