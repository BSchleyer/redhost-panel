<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * Automatic payment of renewal invoices from account credit (per-user
 * preference) and payment reminder tracking on invoices. Additive only.
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        // New customers pay renewals from credit by default (they can switch it
        // off); existing customers keep paying manually until they switch it on.
        if ($this->addColumn($db, 'nx_users', 'auto_pay_credit', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `credit_balance`')) {
            $db->run('UPDATE `nx_users` SET `auto_pay_credit` = 0');
        }
        $this->addColumn($db, 'nx_invoices', 'reminders_sent', 'TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `amount_refunded`');
        $this->addColumn($db, 'nx_invoices', 'last_reminder_at', 'DATETIME NULL AFTER `reminders_sent`');
    }

    /** @return bool whether the column was added now */
    private function addColumn(Connection $db, string $table, string $column, string $definition): bool
    {
        $exists = (int) $db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );
        if ($exists === 0) {
            $db->run(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));

            return true;
        }

        return false;
    }
};
