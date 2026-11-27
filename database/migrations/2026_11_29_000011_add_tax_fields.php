<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * EU tax handling: VAT ID verification results on the customer, tax
 * treatment (category, note, country) on invoices. Additive only.
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $this->addColumn($db, 'nx_users', 'vat_id_valid', 'TINYINT(1) NULL AFTER `vat_id`');
        $this->addColumn($db, 'nx_users', 'vat_id_checked_at', 'DATETIME NULL AFTER `vat_id_valid`');
        $this->addColumn($db, 'nx_users', 'vat_id_name', 'VARCHAR(255) NULL AFTER `vat_id_checked_at`');
        $this->addColumn($db, 'nx_invoices', 'tax_category', "VARCHAR(5) NULL COMMENT 'EN 16931 VAT category: S, AE, E, O' AFTER `tax`");
        $this->addColumn($db, 'nx_invoices', 'tax_note', 'VARCHAR(255) NULL AFTER `tax_category`');
        $this->addColumn($db, 'nx_invoices', 'tax_country', 'CHAR(2) NULL AFTER `tax_note`');
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
