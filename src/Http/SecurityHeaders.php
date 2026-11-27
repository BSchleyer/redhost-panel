<?php

declare(strict_types=1);

namespace Nebrix\Http;

/**
 * Adds security headers to every response (legacy finding M-02).
 *
 * The Content Security Policy does not allow inline scripts or styles; all
 * JavaScript lives in /assets/js and dynamic brand colors are served as a
 * stylesheet. Stripe/PayPal checkout pages are opened by top-level redirects,
 * so no third-party script origins are needed.
 */
final class SecurityHeaders
{
    /**
     * The default Content Security Policy, optionally extended with extra
     * sources for single pages (e.g. the console WebSocket or a CAPTCHA
     * provider). Never adds 'unsafe-inline' or 'unsafe-eval'.
     *
     * @param array<string, list<string>> $extra directive => additional sources
     */
    public static function contentSecurityPolicy(array $extra = []): string
    {
        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'"],
            'style-src' => ["'self'"],
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'"],
            'connect-src' => ["'self'"],
            'frame-src' => [],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'", 'https://www.paypal.com', 'https://www.sandbox.paypal.com', 'https://checkout.stripe.com'],
            'frame-ancestors' => ["'none'"],
        ];
        foreach ($extra as $directive => $sources) {
            if (!array_key_exists($directive, $directives)) {
                continue;
            }
            foreach ($sources as $source) {
                if (preg_match('#^(https|wss|ws)://(\*\.)?[A-Za-z0-9.-]+(:\d{1,5})?$#', $source) === 1 && !in_array($source, $directives[$directive], true)) {
                    $directives[$directive][] = $source;
                }
            }
        }

        $parts = [];
        foreach ($directives as $directive => $sources) {
            if ($sources !== []) {
                $parts[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        return implode('; ', $parts);
    }

    public static function apply(Response $response, Request $request, bool $hsts): Response
    {
        $defaults = [
            'Content-Security-Policy' => self::contentSecurityPolicy(),
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(self)',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        foreach ($defaults as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response->setHeader($name, $value);
            }
        }

        if ($hsts && $request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Authenticated pages must not be cached by shared caches or the back/forward cache.
        if (!$response->hasHeader('Cache-Control')) {
            $response->setHeader('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
