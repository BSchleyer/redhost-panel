<?php

declare(strict_types=1);

namespace Nebrix\Payment;

use Nebrix\Auth\User;
use Nebrix\Billing\CreditService;
use Nebrix\Billing\InsufficientCreditException;
use Nebrix\Billing\InvoiceRepository;
use Nebrix\Database\Connection;
use Nebrix\Http\Request;
use Nebrix\Integration\HealthResult;
use Nebrix\Settings\SettingsRepository;

/**
 * Pays invoices from the customer's account credit (legacy prepaid
 * balance). Debit and payment are recorded in one database transaction.
 */
class CreditGateway implements PaymentGateway
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly CreditService $credit,
        private readonly PaymentService $payments,
        private readonly Connection $db,
    ) {
    }

    public function key(): string
    {
        return 'credit';
    }

    public function label(): string
    {
        return 'Account credit';
    }

    public function isEnabled(): bool
    {
        return $this->settings->bool('payments.credit.enabled');
    }

    public function isAvailableFor(array $invoice, User $user): bool
    {
        return $invoice['type'] !== InvoiceRepository::TYPE_CREDIT
            && strtoupper((string) $invoice['currency']) === ($this->settings->string('general.default_currency') ?: 'EUR')
            && $this->credit->balance($user->id) >= (int) $invoice['total'];
    }

    public function start(array $invoice, User $user, string $returnUrl, string $cancelUrl): string|PaymentOutcome
    {
        if (!$this->isAvailableFor($invoice, $user)) {
            return PaymentOutcome::rejected('Your credit balance is not sufficient.');
        }

        try {
            return $this->db->transaction(function () use ($invoice, $user): PaymentOutcome {
                $reference = 'credit-invoice-' . $invoice['id'];
                // Re-check under lock inside the transaction (no double spending).
                $locked = $this->db->fetchOne('SELECT `status`, `total` FROM `nx_invoices` WHERE `id` = ? FOR UPDATE', [(int) $invoice['id']]);
                if ($locked === null || $locked['status'] !== InvoiceRepository::STATUS_UNPAID) {
                    return PaymentOutcome::rejected('The invoice is no longer open.');
                }

                $this->credit->adjust($user->id, -(int) $locked['total'], 'Payment of invoice ' . $invoice['number'], 'invoice', (string) $invoice['id']);
                $outcome = $this->payments->recordPayment('credit', $reference, (int) $invoice['id'], (int) $locked['total'], (string) $invoice['currency']);
                if (!$outcome->isSuccessful()) {
                    throw new \RuntimeException($outcome->message);
                }

                return $outcome;
            });
        } catch (InsufficientCreditException) {
            return PaymentOutcome::rejected('Your credit balance is not sufficient.');
        }
    }

    public function complete(array $invoice, Request $request): PaymentOutcome
    {
        return PaymentOutcome::rejected('Not applicable.');
    }

    public function refund(array $transaction, int $amount, string $reason, string $idempotencyKey): PaymentOutcome
    {
        return $this->db->transaction(function () use ($transaction, $amount, $reason, $idempotencyKey): PaymentOutcome {
            // A repeated submission reuses the reference and is ignored as a duplicate.
            $reference = 'credit-' . $idempotencyKey;
            $outcome = $this->payments->recordRefund('credit', $reference, (string) $transaction['reference'], $amount, (string) $transaction['currency'], ['reason' => $reason]);
            if ($outcome->status === PaymentOutcome::COMPLETED) {
                $this->credit->adjust((int) $transaction['user_id'], $amount, 'Refund: ' . $reason, 'transaction', (string) $transaction['id']);
            }

            return $outcome;
        });
    }

    public function testConnection(): HealthResult
    {
        return HealthResult::healthy('Account credit is handled locally.');
    }
}
