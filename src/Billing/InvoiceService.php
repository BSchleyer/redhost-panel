<?php

declare(strict_types=1);

namespace Nebrix\Billing;

use Nebrix\Auth\User;
use Nebrix\Catalog\BillingCycle;
use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Mail\Mailer;
use Nebrix\Queue\JobQueue;
use Nebrix\Repository\UserRepository;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Nebrix\Settings\SettingsRepository;
use RuntimeException;

/**
 * Invoices and what happens when they are paid.
 *
 * markPaid() only performs database changes inside the payment transaction
 * and queues idempotent jobs for remote work (provisioning, unsuspension),
 * so a payment is never lost because an integration is unavailable.
 */
class InvoiceService
{
    public const JOB_FULFIL_ORDER = 'order.fulfil';
    public const JOB_UNSUSPEND_SERVICE = 'service.unsuspend';

    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly OrderRepository $orders,
        private readonly ServiceRepository $services,
        private readonly UserRepository $users,
        private readonly SequenceGenerator $sequences,
        private readonly CreditService $credit,
        private readonly JobQueue $jobs,
        private readonly Mailer $mailer,
        private readonly SettingsRepository $settings,
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly Config $config,
    ) {
    }

    public function nextNumber(string $kind): string
    {
        $year = $this->clock->now()->format('Y');
        $prefix = $kind === 'order' ? $this->settings->string('billing.order_prefix') : $this->settings->string('billing.invoice_prefix');

        return sprintf('%s%s-%06d', $prefix, $year, $this->sequences->next($kind . ':' . $year));
    }

    /**
     * @return array<string, mixed>
     */
    public function billingSnapshot(User $user): array
    {
        $a = $user->attributes;

        return [
            'name' => $user->displayName(),
            'company' => $a['company'] ?? null,
            'vat_id' => $a['vat_id'] ?? null,
            'address_line1' => $a['address_line1'] ?? null,
            'address_line2' => $a['address_line2'] ?? null,
            'postcode' => $a['postcode'] ?? null,
            'city' => $a['city'] ?? null,
            'country' => $a['country'] ?? null,
            'email' => $user->email,
        ];
    }

    /**
     * Creates the invoice for a new order (called inside the order transaction).
     */
    public function createForOrder(int $orderId, User $user): int
    {
        $order = $this->orders->find($orderId) ?? throw new RuntimeException('Order not found.');
        $items = [];
        $subtotal = 0;
        $tax = 0;
        foreach ($this->orders->items($orderId) as $item) {
            $net = (int) $item['unit_price'] + (int) $item['setup_fee'] - (int) $item['discount'];
            $subtotal += $net;
            $tax += Money::tax($net, (int) $item['tax_rate_bp']);
            $items[] = [
                'order_item_id' => (int) $item['id'],
                'description' => (string) $item['description'],
                'quantity' => 1,
                'unit_amount' => $net,
                'tax_rate_bp' => (int) $item['tax_rate_bp'],
                'total' => $net,
            ];
        }

        $id = $this->invoices->create([
            'number' => $this->nextNumber('invoice'),
            'user_id' => $user->id,
            'order_id' => $orderId,
            'type' => InvoiceRepository::TYPE_ORDER,
            'status' => InvoiceRepository::STATUS_UNPAID,
            'currency' => (string) $order['currency'],
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'billing_details' => json_encode($this->billingSnapshot($user), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'due_at' => $this->clock->now()->modify(sprintf('+%d days', max(0, $this->settings->int('billing.invoice_due_days'))))->format('Y-m-d H:i:s'),
        ], $items);

        return $id;
    }

    /**
     * Creates a renewal invoice for a recurring service unless one is open.
     */
    public function createRenewal(Service $service): ?int
    {
        if (!BillingCycle::isRecurring($service->billingCycle) || $service->nextDueAt === null) {
            return null;
        }

        return $this->db->transaction(function () use ($service): ?int {
            // Lock the service row so two scheduler runs cannot create two invoices.
            $this->db->fetchValue('SELECT `id` FROM `nx_services` WHERE `id` = ? FOR UPDATE', [$service->id]);
            if ($this->invoices->openRenewalFor($service->id) !== null) {
                return null;
            }

            $user = $this->users->find($service->userId) ?? throw new RuntimeException('Customer not found.');
            $periodStart = new \DateTimeImmutable($service->nextDueAt, new \DateTimeZone('UTC'));
            $periodEnd = BillingCycle::advance($periodStart, $service->billingCycle);
            $net = $service->recurringAmount;
            $tax = Money::tax($net, $service->taxRateBp);

            $id = $this->invoices->create([
                'number' => $this->nextNumber('invoice'),
                'user_id' => $user->id,
                'service_id' => $service->id,
                'type' => InvoiceRepository::TYPE_RENEWAL,
                'status' => InvoiceRepository::STATUS_UNPAID,
                'currency' => $service->currency,
                'subtotal' => $net,
                'tax' => $tax,
                'total' => $net + $tax,
                'billing_details' => json_encode($this->billingSnapshot($user), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'due_at' => $service->nextDueAt,
            ], [[
                'service_id' => $service->id,
                'description' => sprintf('%s – renewal %s to %s', $service->name, $periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d')),
                'quantity' => 1,
                'unit_amount' => $net,
                'tax_rate_bp' => $service->taxRateBp,
                'total' => $net,
                'period_start' => $periodStart->format('Y-m-d H:i:s'),
                'period_end' => $periodEnd->format('Y-m-d H:i:s'),
            ]]);

            $invoice = $this->invoices->find($id);
            $this->mailer->queue($user->email, $user->displayName(), 'service_expiring', [
                'name' => $user->displayName(),
                'service_name' => $service->name,
                'due_date' => $periodStart->format('Y-m-d'),
                'invoice_url' => $this->url('/invoices/' . $id),
            ]);

            return $invoice !== null ? (int) $invoice['id'] : null;
        });
    }

    /**
     * Credit top-up invoice (the "charge balance" function of the legacy panel).
     */
    public function createCreditTopUp(User $user, int $amount, string $currency): int
    {
        return $this->db->transaction(function () use ($user, $amount, $currency): int {
            return $this->invoices->create([
                'number' => $this->nextNumber('invoice'),
                'user_id' => $user->id,
                'type' => InvoiceRepository::TYPE_CREDIT,
                'status' => InvoiceRepository::STATUS_UNPAID,
                'currency' => $currency,
                'subtotal' => $amount,
                'tax' => 0,
                'total' => $amount,
                'billing_details' => json_encode($this->billingSnapshot($user), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'due_at' => $this->clock->now()->modify('+7 days')->format('Y-m-d H:i:s'),
            ], [[
                'description' => 'Account credit top-up',
                'quantity' => 1,
                'unit_amount' => $amount,
                'tax_rate_bp' => 0,
                'total' => $amount,
            ]]);
        });
    }

    /**
     * Applies the effects of a full payment. Must be called inside the
     * payment transaction with the invoice row locked.
     *
     * @param array<string, mixed> $invoice locked invoice row
     */
    public function markPaid(array $invoice, int $transactionId): void
    {
        if ($invoice['status'] !== InvoiceRepository::STATUS_UNPAID) {
            throw new RuntimeException('Only unpaid invoices can be marked as paid.');
        }

        $id = (int) $invoice['id'];
        $now = $this->clock->nowString();
        $this->invoices->update($id, ['status' => InvoiceRepository::STATUS_PAID, 'paid_at' => $now]);

        switch ($invoice['type']) {
            case InvoiceRepository::TYPE_ORDER:
                if ($invoice['order_id'] !== null) {
                    $this->orders->update((int) $invoice['order_id'], ['status' => OrderRepository::STATUS_PAID, 'paid_at' => $now]);
                    $this->jobs->dispatch(self::JOB_FULFIL_ORDER, ['order_id' => (int) $invoice['order_id']], self::JOB_FULFIL_ORDER . ':' . $invoice['order_id']);
                }
                break;

            case InvoiceRepository::TYPE_RENEWAL:
                $this->applyRenewal($invoice);
                break;

            case InvoiceRepository::TYPE_CREDIT:
                $this->credit->adjust((int) $invoice['user_id'], (int) $invoice['total'], 'Credit top-up ' . $invoice['number'], 'invoice', (string) $id);
                break;
        }

        $user = $this->users->find((int) $invoice['user_id']);
        $transaction = $this->db->fetchOne('SELECT `gateway`, `amount`, `currency` FROM `nx_transactions` WHERE `id` = ?', [$transactionId]);
        if ($user !== null && $transaction !== null) {
            $this->mailer->queue($user->email, $user->displayName(), 'payment_confirmation', [
                'name' => $user->displayName(),
                'invoice_number' => (string) $invoice['number'],
                'amount' => Money::format((int) $transaction['amount'], (string) $transaction['currency']),
                'gateway' => ucfirst((string) $transaction['gateway']),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $invoice
     */
    private function applyRenewal(array $invoice): void
    {
        if ($invoice['service_id'] === null) {
            return;
        }

        $serviceId = (int) $invoice['service_id'];
        $service = $this->db->fetchOne('SELECT `status`, `next_due_at`, `billing_cycle`, `suspension_reason` FROM `nx_services` WHERE `id` = ? FOR UPDATE', [$serviceId]);
        if ($service === null || in_array($service['status'], [Service::STATUS_TERMINATED, Service::STATUS_CANCELLED], true)) {
            return;
        }

        // The new period starts at the invoiced period start, not at the payment date.
        $periodEnd = $this->db->fetchValue('SELECT MAX(`period_end`) FROM `nx_invoice_items` WHERE `invoice_id` = ?', [(int) $invoice['id']]);
        $nextDue = is_string($periodEnd)
            ? $periodEnd
            : BillingCycle::advance(new \DateTimeImmutable((string) $service['next_due_at'], new \DateTimeZone('UTC')), (string) $service['billing_cycle'])->format('Y-m-d H:i:s');

        $this->services->update($serviceId, ['next_due_at' => $nextDue]);

        if ($service['status'] === Service::STATUS_SUSPENDED && $service['suspension_reason'] === 'overdue') {
            $this->jobs->dispatch(self::JOB_UNSUSPEND_SERVICE, ['service_id' => $serviceId, 'invoice_id' => (int) $invoice['id']], self::JOB_UNSUSPEND_SERVICE . ':' . $serviceId . ':' . $invoice['id']);
        }
    }

    public function cancel(int $invoiceId): void
    {
        $this->db->transaction(function () use ($invoiceId): void {
            $invoice = $this->invoices->lock($invoiceId);
            if ($invoice === null || $invoice['status'] !== InvoiceRepository::STATUS_UNPAID) {
                throw new RuntimeException('Only unpaid invoices can be cancelled.');
            }
            $this->invoices->update($invoiceId, ['status' => InvoiceRepository::STATUS_CANCELLED, 'cancelled_at' => $this->clock->nowString()]);
            if ($invoice['order_id'] !== null && $invoice['type'] === InvoiceRepository::TYPE_ORDER) {
                $this->orders->update((int) $invoice['order_id'], ['status' => OrderRepository::STATUS_CANCELLED, 'cancelled_at' => $this->clock->nowString()]);
                foreach ($this->orders->items((int) $invoice['order_id']) as $item) {
                    if ($item['product_id'] !== null) {
                        $this->db->run('UPDATE `nx_products` SET `stock` = `stock` + 1 WHERE `id` = ? AND `stock` IS NOT NULL', [(int) $item['product_id']]);
                    }
                }
            }
        });
    }

    private function url(string $path): string
    {
        return rtrim($this->config->string('app.url'), '/') . $path;
    }
}
