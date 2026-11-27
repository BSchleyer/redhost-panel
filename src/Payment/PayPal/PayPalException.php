<?php

declare(strict_types=1);

namespace Nebrix\Payment\PayPal;

use RuntimeException;
use Throwable;

final class PayPalException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 0, private readonly ?string $issue = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function issue(): ?string
    {
        return $this->issue;
    }
}
