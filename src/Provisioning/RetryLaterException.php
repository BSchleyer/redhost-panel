<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use RuntimeException;

/**
 * Signals that a remote operation is still in progress. The job is put back
 * into the queue after the given delay without counting as a failure.
 */
final class RetryLaterException extends RuntimeException
{
    public function __construct(string $message, public readonly int $delaySeconds = 15)
    {
        parent::__construct($message);
    }
}
