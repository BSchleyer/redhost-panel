<?php

declare(strict_types=1);

namespace Nebrix\Admin;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Queue\JobQueue;
use Nebrix\Repository\ProductRepository;
use Nebrix\Repository\UserRepository;
use Nebrix\Settings\SettingsRepository;

/**
 * Aggregated figures for the admin dashboard.
 */
class DashboardStats
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly UserRepository $users,
        private readonly ProductRepository $products,
        private readonly JobQueue $jobs,
        private readonly SettingsRepository $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $currency = $this->settings->string('general.default_currency') ?: 'EUR';
        $monthStart = $this->clock->now()->modify('first day of this month')->setTime(0, 0)->format('Y-m-d H:i:s');

        $revenue = function (?string $since) use ($currency): int {
            $sql = "SELECT COALESCE(SUM(CASE WHEN `type` = 'payment' THEN `amount` ELSE -`amount` END), 0)
                    FROM `nx_transactions` WHERE `status` = 'completed' AND `currency` = ? AND `gateway` <> 'credit'";
            $params = [$currency];
            if ($since !== null) {
                $sql .= ' AND `created_at` >= ?';
                $params[] = $since;
            }

            return (int) $this->db->fetchValue($sql, $params);
        };

        return [
            'currency' => $currency,
            'customers_total' => $this->users->countCustomers(),
            'customers_active' => $this->users->countByStatus('active'),
            'customers_suspended' => $this->users->countByStatus('suspended'),
            'customers_pending' => $this->users->countByStatus('pending'),
            'orders_total' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_orders`'),
            'orders_pending' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM `nx_orders` WHERE `status` = 'pending'"),
            'products_active' => $this->products->countActive(),
            'services_active' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM `nx_services` WHERE `status` = 'active'"),
            'services_failed' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM `nx_services` WHERE `status` = 'failed'"),
            'invoices_unpaid' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM `nx_invoices` WHERE `status` = 'unpaid'"),
            'revenue_month' => $revenue($monthStart),
            'revenue_total' => $revenue(null),
            'tickets_open' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM `nx_tickets` WHERE `status` IN ('open', 'customer_reply')"),
            'recent_transactions' => $this->db->fetchAll(
                'SELECT t.*, u.`email` FROM `nx_transactions` t JOIN `nx_users` u ON u.`id` = t.`user_id` ORDER BY t.`id` DESC LIMIT 6',
            ),
            'recent_customers' => $this->users->recentCustomers(6),
            'integrations' => $this->db->fetchAll(
                'SELECT `id`, `type`, `name`, `is_enabled`, `health_status`, `health_message`, `last_checked_at` FROM `nx_integrations` ORDER BY `type`, `name`',
            ),
            'jobs' => $this->jobs->counts(),
            'scheduler' => $this->db->fetchAll('SELECT * FROM `nx_scheduler_runs` ORDER BY `task`'),
            'notifications' => $this->notifications(),
        ];
    }

    /**
     * Derived system notifications that need staff attention.
     *
     * @return list<array{level: string, message: string, link: string|null}>
     */
    public function notifications(): array
    {
        $notes = [];
        $jobs = $this->jobs->counts();

        if ($this->settings->bool('system.maintenance_mode')) {
            $notes[] = ['level' => 'warning', 'message' => 'Maintenance mode is enabled. Customers cannot use the panel.', 'link' => '/admin/settings/system'];
        }
        if ($jobs['failed'] > 0) {
            $notes[] = ['level' => 'error', 'message' => sprintf('%d background job(s) failed and need attention.', $jobs['failed']), 'link' => '/admin/jobs?status=failed'];
        }
        $failedServices = (int) $this->db->fetchValue("SELECT COUNT(*) FROM `nx_services` WHERE `status` = 'failed'");
        if ($failedServices > 0) {
            $notes[] = ['level' => 'error', 'message' => sprintf('%d service(s) could not be provisioned.', $failedServices), 'link' => '/admin/services?status=failed'];
        }
        $down = (int) $this->db->fetchValue("SELECT COUNT(*) FROM `nx_integrations` WHERE `is_enabled` = 1 AND `health_status` = 'down'");
        if ($down > 0) {
            $notes[] = ['level' => 'error', 'message' => sprintf('%d integration(s) are unreachable.', $down), 'link' => '/admin/integrations'];
        }
        if (!$this->settings->bool('mail.enabled')) {
            $notes[] = ['level' => 'warning', 'message' => 'SMTP is not enabled. E-mails (verification, password reset, invoices) are only written to the log.', 'link' => '/admin/settings/email'];
        }
        if (\Nebrix\Billing\Documents\Seller::fromSettings($this->settings)->missingForEInvoice() !== []) {
            $notes[] = ['level' => 'warning', 'message' => 'Invoice details are incomplete (company address, VAT ID or tax number, contact person, phone, e-mail). Invoice documents are archived unchanged when they are first created – complete the details before issuing invoices.', 'link' => '/admin/settings/general'];
        }
        if (!$this->settings->bool('payments.paypal.enabled') && !$this->settings->bool('payments.stripe.enabled')) {
            $notes[] = ['level' => 'info', 'message' => 'No online payment provider is enabled.', 'link' => '/admin/settings/payments'];
        }

        $lastRun = $this->db->fetchValue('SELECT MAX(`last_finished_at`) FROM `nx_scheduler_runs`');
        if (!is_string($lastRun) || $lastRun < $this->clock->now()->modify('-10 minutes')->format('Y-m-d H:i:s')) {
            $notes[] = ['level' => 'warning', 'message' => 'The scheduler has not run in the last 10 minutes. Renewals, suspensions and health checks are not processed. Configure the cron job (see INSTALLATION.md).', 'link' => '/admin/settings/system'];
        }

        return $notes;
    }
}
