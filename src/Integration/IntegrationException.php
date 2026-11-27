<?php

declare(strict_types=1);

namespace Nebrix\Integration;

use RuntimeException;
use Throwable;

/**
 * Failure while talking to an external system. The message is safe to show
 * to staff (no credentials); customers only ever see generic messages.
 */
class IntegrationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function isNotFound(): bool
    {
        return $this->httpStatus === 404;
    }
}
