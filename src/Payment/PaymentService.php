<?php

declare(strict_types=1);

namespace Nebrix\Payment;

use Nebrix\Billing\InvoiceRepository;
use Nebrix\Billing\InvoiceService;
use Nebrix\Core\Clock;
use Nebrix\Core\LoggerFactory;
use Nebrix\Database\Connection;
use PDOException;
use Psr\Log\LoggerInterface;

/**
 * Gateway-independent payment bookkeeping (AUDIT C-07/C-08).
 *
 * - Only server-verified data reaches this class (webhook with valid
 *   signature, or a server-side capture/retrieve call) – never redirect
 *   parameters alone.
 * - Idempotent: a provider reference is recorded at most once per gateway
 *   and type (unique index); duplicates return the existing transaction.
 * - The amount and currency must match the invoice exactly; otherwise the
 *   payment is recorded as failed and staff are alerted.
 * - Every step is appended to nx_payment_events (immutable history).
 */
class PaymentService
{
    public function __construct(
        private readonly Connection $db,
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceService $invoiceService,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function recordPayment(string $gateway, string $reference, int $invoiceId, int $amount, string $currency, array $metadata = []): PaymentOutcome
    {
        $currency = strtoupper($currency);

        return $this->db->transaction(function () use ($gateway, $reference, $invoiceId, $amount, $currency, $metadata): PaymentOutcome {
            $invoice = $this->invoices->lock($invoiceId);
            if ($invoice === null) {
                $this->event($gateway, 'payment.unknown_invoice', $reference, null, null, $amount, $currency, $metadata);

                return PaymentOutcome::rejected('Unknown invoice.');
            }

            $existing = $this->db->fetchOne(
                "SELECT * FROM `nx_transactions` WHERE `gateway` = ? AND `type` = 'payment' AND `reference` = ?",
                [$gateway, $reference],
            );
            if ($existing !== null && $existing['status'] === 'completed') {
                $this->event($gateway, 'payment.duplicate_ignored', $reference, $invoiceId, (int) $invoice['user_id'], $amount, $currency, []);

                return PaymentOutcome::duplicate((int) $existing['id']);
            }

            if ($currency !== strtoupper((string) $invoice['currency']) || $amount !== (int) $invoice['total']) {
                $transactionId = $this->upsertTransaction($existing, $gateway, $reference, $invoice, $amount, $currency, 'failed', 'Amount or currency does not match the invoice.', $metadata);
                $this->event($gateway, 'payment.amount_mismatch', $reference, $invoiceId, (int) $invoice['user_id'], $amount, $currency, $metadata + ['expected_amount' => (int) $invoice['total'], 'expected_currency' => $invoice['currency']]);
                $this->logger->warning('Payment amount/currency mismatch', ['gateway' => $gateway, 'invoice' => $invoiceId, 'reference' => $reference]);

                return PaymentOutcome::rejected('The paid amount does not match the invoice. Our team has been notified.', $transactionId);
            }

            $transactionId = $this->upsertTransaction($existing, $gateway, $reference, $invoice, $amount, $currency, 'completed', null, $metadata);

            if ($invoice['status'] !== InvoiceRepository::STATUS_UNPAID) {
                // Paid twice (e.g. two providers in parallel) – keep the money traceable for a refund.
                $this->event($gateway, 'payment.overpayment', $reference, $invoiceId, (int) $invoice['user_id'], $amount, $currency, ['invoice_status' => $invoice['status']]);
                $this->logger->warning('Invoice paid more than once', ['invoice' => $invoiceId, 'gateway' => $gateway]);

                return PaymentOutcome::completed($transactionId, false);
            }

            $this->invoiceService->markPaid($invoice, $transactionId);
            $this->event($gateway, 'payment.completed', $reference, $invoiceId, (int) $invoice['user_id'], $amount, $currency, $metadata);

            return PaymentOutcome::completed($transactionId, true);
        });
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function recordFailure(string $gateway, string $reference, ?int $invoiceId, string $reason, array $metadata = []): void
    {
        $invoice = $invoiceId !== null ? $this->invoices->find($invoiceId) : null;
        $this->event($gateway, 'payment.failed', $reference, $invoiceId, $invoice !== null ? (int) $invoice['user_id'] : null, null, null, $metadata + ['reason' => $reason]);

        if ($invoice === null) {
            return;
        }

        try {
            $this->db->insert('nx_transactions', [
                'invoice_id' => $invoiceId,
                'user_id' => (int) $invoice['user_id'],
                'gateway' => $gateway,
                'type' => 'payment',
                'status' => 'failed',
                'amount' => (int) $invoice['total'],
                'currency' => (string) $invoice['currency'],
                'reference' => $reference,
                'failure_reason' => mb_substr($reason, 0, 255),
                'metadata' => $metadata === [] ? null : json_encode(LoggerFactory::redact($metadata), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'created_at' => $this->clock->nowString(),
                'updated_at' => $this->clock->nowString(),
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
            // Already recorded for this reference.
        }
    }

    /**
     * Records a completed refund (idempotent per refund reference).
     *
     * @param array<string, mixed> $metadata
     */
    public function recordRefund(string $gateway, string $refundReference, string $paymentReference, int $amount, string $currency, array $metadata = []): PaymentOutcome
    {
        return $this->db->transaction(function () use ($gateway, $refundReference, $paymentReference, $amount, $currency, $metadata): PaymentOutcome {
            $payment = $this->db->fetchOne(
                "SELECT * FROM `nx_transactions` WHERE `gateway` = ? AND `type` = 'payment' AND `reference` = ? AND `status` = 'completed' FOR UPDATE",
                [$gateway, $paymentReference],
            );
            if ($payment === null) {
                $this->event($gateway, 'refund.unknown_payment', $refundReference, null, null, $amount, $currency, $metadata + ['payment_reference' => $paymentReference]);

                return PaymentOutcome::rejected('The refunded payment is unknown.');
            }

            $existing = $this->db->fetchValue("SELECT `id` FROM `nx_transactions` WHERE `gateway` = ? AND `type` = 'refund' AND `reference` = ?", [$gateway, $refundReference]);
            if ($existing !== null) {
                return PaymentOutcome::duplicate((int) $existing);
            }

            $alreadyRefunded = (int) $this->db->fetchValue("SELECT COALESCE(SUM(`amount`), 0) FROM `nx_transactions` WHERE `parent_id` = ? AND `type` = 'refund' AND `status` = 'completed'", [(int) $payment['id']]);
            if (strtoupper($currency) !== $payment['currency'] || $amount <= 0 || $alreadyRefunded + $amount > (int) $payment['amount']) {
                $this->event($gateway, 'refund.invalid_amount', $refundReference, $payment['invoice_id'] !== null ? (int) $payment['invoice_id'] : null, (int) $payment['user_id'], $amount, $currency, $metadata);

                return PaymentOutcome::rejected('The refund exceeds the payment.');
            }

            $now = $this->clock->nowString();
            $id = $this->db->insert('nx_transactions', [
                'invoice_id' => $payment['invoice_id'],
                'user_id' => (int) $payment['user_id'],
                'parent_id' => (int) $payment['id'],
                'gateway' => $gateway,
                'type' => 'refund',
                'status' => 'completed',
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'reference' => $refundReference,
                'metadata' => $metadata === [] ? null : json_encode(LoggerFactory::redact($metadata), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($payment['invoice_id'] !== null) {
                $invoice = $this->invoices->lock((int) $payment['invoice_id']);
                if ($invoice !== null) {
                    $refunded = (int) $invoice['amount_refunded'] + $amount;
                    $this->invoices->update((int) $invoice['id'], [
                        'amount_refunded' => $refunded,
                        'status' => $refunded >= (int) $invoice['total'] ? InvoiceRepository::STATUS_REFUNDED : $invoice['status'],
                    ]);
                }
            }

            $this->event($gateway, 'refund.completed', $refundReference, $payment['invoice_id'] !== null ? (int) $payment['invoice_id'] : null, (int) $payment['user_id'], $amount, $currency, $metadata + ['payment_reference' => $paymentReference]);

            return PaymentOutcome::completed($id, true);
        });
    }

    /**
     * Appends to the immutable payment history.
     *
     * @param array<string, mixed> $payload
     */
    public function event(string $gateway, string $type, ?string $reference, ?int $invoiceId, ?int $userId, ?int $amount, ?string $currency, array $payload = []): void
    {
        $this->db->insert('nx_payment_events', [
            'gateway' => $gateway,
            'event_type' => $type,
            'reference' => $reference !== null ? mb_substr($reference, 0, 191) : null,
            'invoice_id' => $invoiceId,
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => $currency !== null ? strtoupper($currency) : null,
            'payload' => $payload === [] ? null : json_encode(LoggerFactory::redact($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'created_at' => $this->clock->nowString(),
        ]);
    }

    /**
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $invoice
     * @param array<string, mixed> $metadata
     */
    private function upsertTransaction(?array $existing, string $gateway, string $reference, array $invoice, int $amount, string $currency, string $status, ?string $failure, array $metadata): int
    {
        $now = $this->clock->nowString();
        $data = [
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'failure_reason' => $failure,
            'metadata' => $metadata === [] ? null : json_encode(LoggerFactory::redact($metadata), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            $this->db->update('nx_transactions', $data, ['id' => (int) $existing['id']]);

            return (int) $existing['id'];
        }

        return $this->db->insert('nx_transactions', $data + [
            'invoice_id' => (int) $invoice['id'],
            'user_id' => (int) $invoice['user_id'],
            'gateway' => $gateway,
            'type' => 'payment',
            'reference' => $reference,
            'created_at' => $now,
        ]);
    }
}
