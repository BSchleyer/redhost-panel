<?php

declare(strict_types=1);

namespace Nebrix\Api;

use Nebrix\Http\HttpException;
use Nebrix\Http\Response;

/**
 * An API error with a stable machine-readable code. The message is shown to
 * API clients and must not contain internal details.
 */
final class ApiException extends HttpException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $headers = [],
    ) {
        parent::__construct($status, $message);
    }

    public static function unauthenticated(string $message = 'A valid API token is required.'): self
    {
        return new self(401, 'unauthenticated', $message, ['WWW-Authenticate' => 'Bearer realm="nebrix"']);
    }

    public static function forbidden(string $message = 'The token is not allowed to perform this action.'): self
    {
        return new self(403, 'forbidden', $message);
    }

    public static function notFound(string $message = 'The resource was not found.'): self
    {
        return new self(404, 'not_found', $message);
    }

    public static function conflict(string $message): self
    {
        return new self(409, 'conflict', $message);
    }

    public static function unprocessable(string $message): self
    {
        return new self(422, 'unprocessable', $message);
    }

    public static function rateLimited(int $retryAfter, string $message = 'Too many requests. Please slow down.'): self
    {
        return new self(429, 'rate_limited', $message, ['Retry-After' => (string) max(1, $retryAfter)]);
    }

    public static function unavailable(string $message): self
    {
        return new self(503, 'unavailable', $message, ['Retry-After' => '600']);
    }

    /** Default error codes for plain HTTP exceptions. */
    public static function codeFor(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            413 => 'payload_too_large',
            415 => 'unsupported_media_type',
            422 => 'unprocessable',
            429 => 'rate_limited',
            502 => 'upstream_error',
            503 => 'unavailable',
            default => $status >= 500 ? 'server_error' : 'error',
        };
    }

    /**
     * Standard error envelope: {"error": {"code": …, "message": …, …}}.
     *
     * @param array<string, mixed> $extra
     */
    public static function response(int $status, string $code, string $message, array $extra = []): Response
    {
        return Response::json(['error' => ['code' => $code, 'message' => $message] + $extra], $status);
    }
}
