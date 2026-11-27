<?php

declare(strict_types=1);

namespace Nebrix\Core;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    /** Context keys whose values must never reach a log file. */
    private const SENSITIVE_KEYS = [
        'password', 'pass', 'secret', 'token', 'authorization', 'api_key', 'apikey',
        'client_secret', 'webhook_secret', 'private_key', 'cookie', 'credentials',
        'two_factor_secret', 'recovery_codes', 'card', 'cvc',
    ];

    public static function create(Config $config): LoggerInterface
    {
        $logger = new Logger('nebrix');
        $level = Level::fromName(ucfirst(strtolower($config->string('log.level', 'info'))));
        $path = $config->string('log.path');

        if ($path === 'php://stderr' || $path === 'php://stdout') {
            $logger->pushHandler(new StreamHandler($path, $level));
        } else {
            $logger->pushHandler(new RotatingFileHandler($path, $config->int('log.retention_days', 30), $level, true, 0640));
        }

        $logger->pushProcessor(static function (LogRecord $record): LogRecord {
            return $record->with(context: self::redact($record->context));
        });

        return $logger;
    }

    /**
     * @param array<mixed> $context
     * @return array<mixed>
     */
    public static function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = self::redact($value);
            }
        }

        return $context;
    }

    private static function isSensitive(string $key): bool
    {
        $normalized = strtolower(str_replace('-', '_', $key));
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
