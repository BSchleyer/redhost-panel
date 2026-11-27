<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * Invoice documents: correction documents (cancellations, refunds) refer to
 * the original invoice; issued PDF/XML files are archived unchanged and
 * their SHA-256 hashes stored. Additive only.
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $this->addColumn($db, 'nx_invoices', 'reference_invoice_id', 'BIGINT UNSIGNED NULL AFTER `service_id`');
        $this->addColumn($db, 'nx_invoices', 'archived_at', 'DATETIME NULL');
        $this->addColumn($db, 'nx_invoices', 'pdf_sha256', 'CHAR(64) NULL');
        $this->addColumn($db, 'nx_invoices', 'xml_sha256', 'CHAR(64) NULL');
    }

    private function addColumn(Connection $db, string $table, string $column, string $definition): void
    {
        $exists = (int) $db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );
        if ($exists === 0) {
            $db->run(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
        }
    }
};
