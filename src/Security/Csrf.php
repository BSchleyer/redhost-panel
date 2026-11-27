<?php

declare(strict_types=1);

namespace Nebrix\Security;

use Nebrix\Session\Session;

/**
 * Synchronizer-token CSRF protection (legacy finding H-01).
 */
final class Csrf
{
    public const FIELD = '_token';
    public const HEADER = 'X-CSRF-Token';
    private const SESSION_KEY = '_csrf_token';

    public static function token(Session $session): string
    {
        $token = $session->get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $session->put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function validate(Session $session, mixed $submitted): bool
    {
        $expected = $session->get(self::SESSION_KEY);

        return is_string($expected)
            && is_string($submitted)
            && $submitted !== ''
            && hash_equals($expected, $submitted);
    }

    /** Issues a fresh token, e.g. after login. */
    public static function rotate(Session $session): void
    {
        $session->put(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }
}
