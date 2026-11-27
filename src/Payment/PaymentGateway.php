<?php

declare(strict_types=1);

namespace Nebrix\Payment;

use Nebrix\Auth\User;
use Nebrix\Http\Request;
use Nebrix\Integration\HealthResult;

interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    public function isEnabled(): bool;

    /**
     * @param array<string, mixed> $invoice
     */
    public function isAvailableFor(array $invoice, User $user): bool;

    /**
     * Starts a payment. Returns a provider URL to redirect the customer to,
     * or a final outcome for gateways that settle immediately.
     *
     * @param array<string, mixed> $invoice
     */
    public function start(array $invoice, User $user, string $returnUrl, string $cancelUrl): string|PaymentOutcome;

    /**
     * Handles the customer's return from the provider. Implementations must
     * verify the payment server-side and never trust query parameters.
     *
     * @param array<string, mixed> $invoice
     */
    public function complete(array $invoice, Request $request): PaymentOutcome;

    /**
     * @param array<string, mixed> $transaction completed payment transaction
     */
    public function refund(array $transaction, int $amount, string $reason): PaymentOutcome;

    public function testConnection(): HealthResult;
}
