<?php

declare(strict_types=1);

namespace Nebrix\Security;

/**
 * Prevents open redirects (legacy finding M-03): user-supplied redirect
 * targets are reduced to same-origin relative paths.
 */
final class RedirectGuard
{
    public static function sanitize(mixed $target, string $fallback = '/'): string
    {
        if (!is_string($target) || $target === '') {
            return $fallback;
        }

        // Must be an absolute path on this host: "/foo", never "//evil", "/\evil", "https://…" or control chars.
        if (preg_match('#^/(?![/\\\\])[^\x00-\x1F\x7F\\\\]*$#', $target) !== 1) {
            return $fallback;
        }

        $parts = parse_url($target);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return $fallback;
        }

        return $target;
    }
}
