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
    public static function apply(Response $response, Request $request, bool $hsts): Response
    {
        $defaults = [
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self'",
                "img-src 'self' data:",
                "font-src 'self'",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self' https://www.paypal.com https://www.sandbox.paypal.com https://checkout.stripe.com",
                "frame-ancestors 'none'",
            ]),
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
