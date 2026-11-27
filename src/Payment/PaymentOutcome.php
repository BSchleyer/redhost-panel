<?php

declare(strict_types=1);

namespace Nebrix\Payment;

final class PaymentOutcome
{
    public const COMPLETED = 'completed';
    public const DUPLICATE = 'duplicate';
    public const PENDING = 'pending';
    public const REJECTED = 'rejected';

    private function __construct(
        public readonly string $status,
        public readonly ?int $transactionId,
        public readonly string $message,
        public readonly bool $invoiceSettled,
    ) {
    }

    public static function completed(int $transactionId, bool $invoiceSettled): self
    {
        return new self(self::COMPLETED, $transactionId, 'Payment received.', $invoiceSettled);
    }

    public static function duplicate(int $transactionId): self
    {
        return new self(self::DUPLICATE, $transactionId, 'Payment already recorded.', true);
    }

    public static function pending(string $message): self
    {
        return new self(self::PENDING, null, $message, false);
    }

    public static function rejected(string $message, ?int $transactionId = null): self
    {
        return new self(self::REJECTED, $transactionId, $message, false);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::COMPLETED || $this->status === self::DUPLICATE;
    }
}
