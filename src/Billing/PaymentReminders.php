<?php

declare(strict_types=1);

namespace Nebrix\Billing;

use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Mail\Mailer;
use Nebrix\Repository\UserRepository;
use Nebrix\Settings\SettingsRepository;

/**
 * Payment reminders for unpaid order and renewal invoices.
 *
 * billing.reminder_offsets lists days relative to the due date, e.g.
 * "-3,1" = three days before and one day after the due date. An invoice
 * receives at most one reminder per run; if several levels are already due
 * (e.g. after the feature was enabled) only one e-mail is sent and the
 * counter jumps to the current level. The counter is advanced with a
 * conditional update, so parallel runs cannot send a reminder twice.
 */
class PaymentReminders
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly SettingsRepository $settings,
        private readonly Mailer $mailer,
        private readonly UserRepository $users,
        private readonly Config $config,
    ) {
    }

    /**
     * @return list<int> configured offsets in days, ascending
     */
    public function offsets(): array
    {
        $offsets = [];
        foreach (explode(',', $this->settings->string('billing.reminder_offsets')) as $part) {
            $part = trim($part);
            if (preg_match('/^-?\d{1,2}$/', $part) === 1) {
                $offsets[] = (int) $part;
            }
        }
        $offsets = array_values(array_unique($offsets));
        sort($offsets);

        return array_slice($offsets, 0, 5);
    }

    public function send(): int
    {
        $offsets = $this->offsets();
        if ($offsets === []) {
            return 0;
        }
        $now = $this->clock->now();
        $earliest = $now->modify(sprintf('%+d days', -$offsets[0]))->format('Y-m-d H:i:s');

        $sent = 0;
        foreach ($this->db->fetchAll(
            // No automatic reminders for services that are cancelled or have ended.
            "SELECT i.* FROM `nx_invoices` i LEFT JOIN `nx_services` s ON s.`id` = i.`service_id`
              WHERE i.`status` = 'unpaid' AND i.`type` IN ('order', 'renewal') AND i.`total` > 0
               AND i.`due_at` <= ? AND i.`reminders_sent` < ?
               AND (i.`service_id` IS NULL OR i.`type` = 'order' OR (s.`status` IN ('active', 'suspended') AND s.`cancel_at` IS NULL))
              ORDER BY i.`id` LIMIT 1000",
            [$earliest, count($offsets)],
        ) as $invoice) {
            $due = new \DateTimeImmutable((string) $invoice['due_at'], new \DateTimeZone('UTC'));
            $level = 0;
            foreach ($offsets as $offset) {
                if ($due->modify(sprintf('%+d days', $offset)) <= $now) {
                    $level++;
                }
            }
            if ($level <= (int) $invoice['reminders_sent']) {
                continue;
            }
            $claimed = $this->db->run(
                'UPDATE `nx_invoices` SET `reminders_sent` = ?, `last_reminder_at` = ? WHERE `id` = ? AND `reminders_sent` = ? AND `status` = ?',
                [$level, $now->format('Y-m-d H:i:s'), (int) $invoice['id'], (int) $invoice['reminders_sent'], InvoiceRepository::STATUS_UNPAID],
            )->rowCount() === 1;
            if (!$claimed) {
                continue;
            }

            $user = $this->users->find((int) $invoice['user_id']);
            if ($user === null || !$user->isActive()) {
                continue;
            }
            $this->mailer->queue($user->email, $user->displayName(), 'invoice_reminder', [
                'name' => $user->displayName(),
                'invoice_number' => (string) $invoice['number'],
                'invoice_total' => Money::format((int) $invoice['total'], (string) $invoice['currency']),
                'due_date' => $due->format('Y-m-d'),
                'invoice_url' => rtrim($this->config->string('app.url'), '/') . '/invoices/' . $invoice['id'],
            ]);
            $sent++;
        }

        return $sent;
    }
}
