<?php

declare(strict_types=1);

namespace Nebrix\Http;

use RuntimeException;
use Throwable;

/**
 * Exception that maps to an HTTP status code. The message is shown to the
 * user and therefore must never contain internal details.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public static function notFound(string $message = 'The requested page could not be found.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'You are not allowed to perform this action.'): self
    {
        return new self(403, $message);
    }

    public static function badRequest(string $message = 'The request could not be processed.'): self
    {
        return new self(400, $message);
    }

    public static function tooManyRequests(string $message = 'Too many requests. Please try again later.'): self
    {
        return new self(429, $message);
    }
}
