<?php

declare(strict_types=1);

namespace Nebrix\Scheduler;

use Nebrix\Auth\LoginHistory;
use Nebrix\Auth\TokenService;
use Nebrix\Billing\InvoiceService;
use Nebrix\Catalog\BillingCycle;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Integration\IntegrationLogger;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Payment\PaymentService;
use Nebrix\Provisioning\ServiceManager;
use Nebrix\Queue\JobQueue;
use Nebrix\RemoteConsole\ConsoleSessions;
use Nebrix\Security\RateLimiter;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Nebrix\Session\SessionStore;
use Nebrix\Settings\SettingsRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Periodic tasks, run every minute by cron: `* * * * * php bin/nebrix schedule:run`.
 * A database lock prevents overlapping runs; each task records its last run
 * in nx_scheduler_runs (shown on the admin dashboard).
 */
class Scheduler
{
    private const LOCK = 'nebrix_scheduler';

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly SettingsRepository $settings,
        private readonly ServiceRepository $services,
        private readonly InvoiceService $invoices,
        private readonly ServiceManager $serviceManager,
        private readonly IntegrationManager $integrations,
        private readonly JobQueue $jobs,
        private readonly SessionStore $sessions,
        private readonly RateLimiter $limiter,
        private readonly TokenService $tokens,
        private readonly LoginHistory $loginHistory,
        private readonly IntegrationLogger $integrationLogs,
        private readonly LoggerInterface $logger,
        private readonly PaymentService $payments,
        private readonly ConsoleSessions $consoleSessions,
        private readonly \Nebrix\Api\ApiTokenService $apiTokens,
        private readonly \Nebrix\Billing\AutoPayment $autoPayment,
        private readonly \Nebrix\Billing\PaymentReminders $reminders,
        private readonly \Nebrix\Contract\CancellationService $cancellations,
        private readonly \Nebrix\Billing\Tax\TaxService $taxes,
    ) {
    }

    /**
     * @return array<string, array{0: int, 1: callable(): string}> task => [interval minutes, callback]
     */
    private function tasks(): array
    {
        return [
            'queue.release_stale' => [5, fn (): string => sprintf('%d job(s) released', $this->jobs->releaseStale())],
            'billing.renewal_invoices' => [15, $this->createRenewalInvoices(...)],
            // Before the overdue check: credit topped up in the meantime still pays the renewal.
            'billing.auto_pay_credit' => [15, fn (): string => sprintf('%d invoice(s) paid from credit', $this->autoPayment->run())],
            'billing.payment_reminders' => [60, fn (): string => sprintf('%d reminder(s) sent', $this->reminders->send())],
            'billing.suspend_overdue' => [15, $this->suspendOverdue(...)],
            'billing.terminate_suspended' => [60, $this->terminateLongSuspended(...)],
            'services.cancellations' => [60, fn (): string => sprintf('%d cancelled service(s) terminated', $this->cancellations->executeDue())],
            'billing.cancel_stale_orders' => [60, $this->cancelStaleOrders(...)],
            'integrations.health' => [30, fn (): string => sprintf('%d integration(s) checked', count($this->integrations->checkAll()))],
            'tax.recheck_vat_ids' => [60, fn (): string => sprintf('%d VAT ID(s) checked', $this->taxes->recheckStale())],
            'maintenance.cleanup' => [1440, $this->cleanup(...)],
        ];
    }

    /**
     * @param bool $force run every task regardless of its interval
     * @return array<string, string> task => result
     */
    public function run(bool $force = false): array
    {
        if ((int) $this->db->fetchValue('SELECT GET_LOCK(?, 0)', [self::LOCK]) !== 1) {
            return ['scheduler' => 'skipped – another run is in progress'];
        }

        $results = [];
        try {
            foreach ($this->tasks() as $name => [$interval, $callback]) {
                if (!$force && !$this->isDue($name, $interval)) {
                    continue;
                }

                $this->record($name, 'running', null, true);
                try {
                    $message = $callback();
                    $this->record($name, 'success', $message);
                    $results[$name] = $message;
                } catch (Throwable $exception) {
                    $this->record($name, 'failed', mb_substr($exception->getMessage(), 0, 500));
                    $this->logger->error('Scheduled task failed', ['task' => $name, 'message' => $exception->getMessage()]);
                    $results[$name] = 'failed: ' . $exception->getMessage();
                }
            }
        } finally {
            $this->db->fetchValue('SELECT RELEASE_LOCK(?)', [self::LOCK]);
        }

        return $results;
    }

    private function isDue(string $task, int $intervalMinutes): bool
    {
        $last = $this->db->fetchValue('SELECT `last_started_at` FROM `nx_scheduler_runs` WHERE `task` = ?', [$task]);

        return !is_string($last) || $last <= $this->clock->now()->modify(sprintf('-%d minutes', $intervalMinutes))->modify('+30 seconds')->format('Y-m-d H:i:s');
    }

    private function record(string $task, string $status, ?string $message, bool $started = false): void
    {
        $now = $this->clock->nowString();
        if ($started) {
            $this->db->run(
                'INSERT INTO `nx_scheduler_runs` (`task`, `last_started_at`, `last_status`) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `last_started_at` = ?, `last_status` = ?',
                [$task, $now, $status, $now, $status],
            );

            return;
        }

        $this->db->run('UPDATE `nx_scheduler_runs` SET `last_finished_at` = ?, `last_status` = ?, `last_message` = ? WHERE `task` = ?', [$now, $status, $message, $task]);
    }

    /** Renewal invoices for services due within the notice period. */
    public function createRenewalInvoices(): string
    {
        $horizon = $this->clock->now()->modify(sprintf('+%d days', max(1, $this->settings->int('billing.renewal_notice_days'))))->format('Y-m-d H:i:s');
        $rows = $this->db->fetchAll(
            "SELECT s.`id` FROM `nx_services` s
             WHERE s.`status` IN ('active', 'suspended') AND s.`next_due_at` IS NOT NULL AND s.`next_due_at` <= ?
               AND s.`billing_cycle` <> 'one_time'
               AND (s.`cancel_at` IS NULL OR s.`cancel_at` > s.`next_due_at`)
               AND NOT EXISTS (SELECT 1 FROM `nx_invoices` i WHERE i.`service_id` = s.`id` AND i.`type` = 'renewal' AND i.`status` = 'unpaid')",
            [$horizon],
        );

        $created = 0;
        foreach ($rows as $row) {
            $service = $this->services->find((int) $row['id']);
            if ($service === null || !BillingCycle::isRecurring($service->billingCycle)) {
                continue;
            }
            $invoiceId = $this->invoices->createRenewal($service);
            if ($invoiceId === null) {
                continue;
            }
            $created++;

            // Free renewals (e.g. 100 % group discount) cannot be paid by any
            // gateway; settle them like free orders so the service is not
            // suspended as overdue.
            $invoice = $this->db->fetchOne('SELECT `total`, `currency` FROM `nx_invoices` WHERE `id` = ?', [$invoiceId]);
            if ($invoice !== null && (int) $invoice['total'] === 0) {
                $this->payments->recordPayment('free', 'free-invoice-' . $invoiceId, $invoiceId, 0, (string) $invoice['currency']);
            } else {
                $this->autoPayment->run($invoiceId);
            }
        }

        return sprintf('%d renewal invoice(s) created', $created);
    }

    /** Suspends active services whose renewal invoice is overdue beyond the grace period. */
    public function suspendOverdue(): string
    {
        $cutoff = $this->clock->now()->modify(sprintf('-%d days', max(0, $this->settings->int('billing.suspend_grace_days'))))->format('Y-m-d H:i:s');
        $rows = $this->db->fetchAll(
            "SELECT s.`id` FROM `nx_services` s
             WHERE s.`status` = 'active' AND s.`next_due_at` IS NOT NULL AND s.`next_due_at` < ?
               AND EXISTS (SELECT 1 FROM `nx_invoices` i WHERE i.`service_id` = s.`id` AND i.`type` = 'renewal' AND i.`status` = 'unpaid')",
            [$cutoff],
        );

        $count = 0;
        foreach ($rows as $row) {
            $service = $this->services->find((int) $row['id']);
            if ($service !== null && $service->status === Service::STATUS_ACTIVE) {
                $this->serviceManager->suspend($service, 'overdue', null);
                $count++;
            }
        }

        return sprintf('%d service(s) suspended', $count);
    }

    /** Terminates services suspended for non-payment longer than configured (opt-in). */
    public function terminateLongSuspended(): string
    {
        $days = $this->settings->int('billing.terminate_after_days');
        if ($days <= 0) {
            return 'automatic termination disabled';
        }

        $cutoff = $this->clock->now()->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
        $rows = $this->db->fetchAll(
            "SELECT `id` FROM `nx_services` WHERE `status` = 'suspended' AND `suspension_reason` = 'overdue' AND `next_due_at` < ?",
            [$cutoff],
        );

        $count = 0;
        foreach ($rows as $row) {
            $service = $this->services->find((int) $row['id']);
            if ($service !== null) {
                $this->serviceManager->terminate($service, null, 'unpaid for ' . $days . ' days');
                $count++;
            }
        }

        return sprintf('%d service(s) queued for termination', $count);
    }

    /** Cancels unpaid new orders two weeks after the due date (releases stock). */
    public function cancelStaleOrders(): string
    {
        $cutoff = $this->clock->now()->modify('-14 days')->format('Y-m-d H:i:s');
        $rows = $this->db->fetchAll("SELECT `id` FROM `nx_invoices` WHERE `type` = 'order' AND `status` = 'unpaid' AND `due_at` < ?", [$cutoff]);
        $cancelled = 0;
        foreach ($rows as $row) {
            try {
                $this->invoices->cancel((int) $row['id']);
                $cancelled++;
            } catch (\RuntimeException $exception) {
                // Paid in the meantime – nothing to do.
                $this->logger->info('Stale order not cancelled', ['invoice' => $row['id'], 'reason' => $exception->getMessage()]);
            }
        }

        return sprintf('%d stale order(s) cancelled', $cancelled);
    }

    public function cleanup(): string
    {
        $removed = $this->sessions->garbageCollect()
            + $this->limiter->garbageCollect()
            + $this->tokens->prune()
            + $this->loginHistory->prune($this->settings->int('system.login_history_retention_days') ?: 180)
            + $this->integrationLogs->prune($this->settings->int('system.integration_log_retention_days') ?: 30)
            + $this->jobs->pruneCompleted(30)
            + $this->consoleSessions->prune()
            + $this->apiTokens->pruneIdempotency();

        return sprintf('%d expired record(s) removed', $removed);
    }
}
