<?php

declare(strict_types=1);

namespace Nebrix\Core;

use Dotenv\Dotenv;

/**
 * Environment variable access.
 *
 * Values come from the real process environment first and from the optional
 * .env file second (phpdotenv writes into $_ENV/$_SERVER, never putenv(), so
 * secrets are not exposed to child processes through getenv()).
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        if (is_file($basePath . '/.env')) {
            Dotenv::createImmutable($basePath)->safeLoad();
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        if ($value === null) {
            $fromProcess = getenv($key);
            $value = $fromProcess === false ? null : $fromProcess;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }
}
