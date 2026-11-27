<?php

declare(strict_types=1);

namespace Nebrix\Core;

use ErrorException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Converts PHP warnings/notices into exceptions (so nothing fails silently)
 * and makes sure uncaught exceptions are logged and never rendered with
 * internal details (legacy finding H-09).
 */
final class ErrorHandler
{
    private static bool $registered = false;

    public static function register(Container $container): void
    {
        $config = $container->get(Config::class);
        error_reporting(E_ALL);
        ini_set('display_errors', $config->bool('app.debug') && $config->string('app.env') === 'local' ? '1' : '0');
        ini_set('log_errors', '1');

        if (self::$registered) {
            return;
        }
        self::$registered = true;

        set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($container): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            // Deprecations (often raised by third-party code on newer PHP versions) are logged, not fatal.
            if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                try {
                    $container->get(LoggerInterface::class)->notice('PHP deprecation', ['message' => $message, 'file' => $file . ':' . $line]);
                } catch (Throwable) {
                    error_log(sprintf('[nebrix] Deprecated: %s in %s:%d', $message, $file, $line));
                }

                return true;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $exception) use ($container): void {
            $reference = bin2hex(random_bytes(6));
            try {
                $container->get(LoggerInterface::class)->critical('Uncaught exception', [
                    'reference' => $reference,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile() . ':' . $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ]);
            } catch (Throwable) {
                error_log(sprintf('[nebrix] %s: %s (ref %s)', $exception::class, $exception->getMessage(), $reference));
            }

            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, sprintf("Error: %s (reference %s)\n", $exception->getMessage(), $reference));

                return;
            }

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
            }
            echo 'An unexpected error occurred. Reference: ' . $reference;
        });
    }
}
