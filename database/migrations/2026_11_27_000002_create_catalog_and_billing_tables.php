<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * Integrations, products, orders, invoices, payments and the credit ledger.
 * All monetary columns are BIGINT minor units (cents).
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->run("CREATE TABLE IF NOT EXISTS `nx_integrations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` VARCHAR(30) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `config` JSON NULL COMMENT 'non-secret configuration',
            `secrets` TEXT NULL COMMENT 'encrypted JSON',
            `health_status` VARCHAR(20) NOT NULL DEFAULT 'unknown',
            `health_message` VARCHAR(500) NULL,
            `last_checked_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_integrations_type_name` (`type`, `name`),
            KEY `idx_nx_integrations_type` (`type`, `is_enabled`)
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_integration_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `integration_id` INT UNSIGNED NOT NULL,
            `level` VARCHAR(10) NOT NULL,
            `action` VARCHAR(100) NOT NULL,
            `message` TEXT NOT NULL,
            `context` JSON NULL,
            `duration_ms` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nx_integration_logs_integration` (`integration_id`, `created_at`),
            KEY `idx_nx_integration_logs_created` (`created_at`),
            CONSTRAINT `fk_nx_integration_logs_integration` FOREIGN KEY (`integration_id`) REFERENCES `nx_integrations` (`id`) ON DELETE CASCADE
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_products` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `category` VARCHAR(30) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `slug` VARCHAR(160) NOT NULL,
            `description` TEXT NULL,
            `features` TEXT NULL COMMENT 'one feature per line',
            `price` BIGINT NOT NULL DEFAULT 0,
            `setup_fee` BIGINT NOT NULL DEFAULT 0,
            `currency` CHAR(3) NOT NULL DEFAULT 'EUR',
            `billing_cycle` VARCHAR(20) NOT NULL DEFAULT 'monthly',
            `tax_rate_bp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'basis points, 1900 = 19%',
            `stock` INT NULL COMMENT 'NULL = unlimited',
            `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `auto_provision` TINYINT(1) NOT NULL DEFAULT 1,
            `integration_id` INT UNSIGNED NULL,
            `provisioning_config` JSON NULL,
            `customer_group_id` INT UNSIGNED NULL COMMENT 'restrict to this group',
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_products_slug` (`slug`),
            KEY `idx_nx_products_category` (`category`, `is_active`, `is_visible`),
            CONSTRAINT `fk_nx_products_integration` FOREIGN KEY (`integration_id`) REFERENCES `nx_integrations` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_nx_products_group` FOREIGN KEY (`customer_group_id`) REFERENCES `nx_customer_groups` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_orders` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `number` VARCHAR(32) NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `currency` CHAR(3) NOT NULL,
            `subtotal` BIGINT NOT NULL DEFAULT 0,
            `discount` BIGINT NOT NULL DEFAULT 0,
            `tax` BIGINT NOT NULL DEFAULT 0,
            `total` BIGINT NOT NULL DEFAULT 0,
            `ip` VARCHAR(45) NULL,
            `notes` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            `paid_at` DATETIME NULL,
            `cancelled_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_orders_number` (`number`),
            KEY `idx_nx_orders_user_status` (`user_id`, `status`),
            KEY `idx_nx_orders_status_created` (`status`, `created_at`),
            CONSTRAINT `fk_nx_orders_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`)
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_order_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` BIGINT UNSIGNED NOT NULL,
            `product_id` INT UNSIGNED NULL,
            `description` VARCHAR(255) NOT NULL,
            `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
            `unit_price` BIGINT NOT NULL,
            `setup_fee` BIGINT NOT NULL DEFAULT 0,
            `discount` BIGINT NOT NULL DEFAULT 0,
            `tax_rate_bp` INT UNSIGNED NOT NULL DEFAULT 0,
            `billing_cycle` VARCHAR(20) NOT NULL,
            `options` JSON NULL COMMENT 'validated customer options (e.g. hostname)',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nx_order_items_order` (`order_id`),
            CONSTRAINT `fk_nx_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `nx_orders` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_nx_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `nx_products` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_services` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `product_id` INT UNSIGNED NULL,
            `order_item_id` BIGINT UNSIGNED NULL,
            `integration_id` INT UNSIGNED NULL,
            `category` VARCHAR(30) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `billing_cycle` VARCHAR(20) NOT NULL,
            `recurring_amount` BIGINT NOT NULL DEFAULT 0 COMMENT 'net, per cycle',
            `tax_rate_bp` INT UNSIGNED NOT NULL DEFAULT 0,
            `currency` CHAR(3) NOT NULL,
            `next_due_at` DATETIME NULL,
            `external_id` VARCHAR(191) NULL,
            `remote` JSON NULL COMMENT 'remote identifiers (node, vmid, sid, ...)',
            `config` JSON NULL COMMENT 'provisioning configuration snapshot',
            `credentials` TEXT NULL COMMENT 'encrypted JSON',
            `suspension_reason` VARCHAR(255) NULL,
            `last_error` TEXT NULL,
            `provisioned_at` DATETIME NULL,
            `suspended_at` DATETIME NULL,
            `terminated_at` DATETIME NULL,
            `cancelled_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_services_order_item` (`order_item_id`),
            KEY `idx_nx_services_user_status` (`user_id`, `status`),
            KEY `idx_nx_services_status_due` (`status`, `next_due_at`),
            KEY `idx_nx_services_integration` (`integration_id`),
            CONSTRAINT `fk_nx_services_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`),
            CONSTRAINT `fk_nx_services_product` FOREIGN KEY (`product_id`) REFERENCES `nx_products` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_nx_services_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `nx_order_items` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_nx_services_integration` FOREIGN KEY (`integration_id`) REFERENCES `nx_integrations` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_invoices` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `number` VARCHAR(32) NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `order_id` BIGINT UNSIGNED NULL,
            `service_id` BIGINT UNSIGNED NULL,
            `type` VARCHAR(20) NOT NULL DEFAULT 'order',
            `status` VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            `currency` CHAR(3) NOT NULL,
            `subtotal` BIGINT NOT NULL DEFAULT 0,
            `tax` BIGINT NOT NULL DEFAULT 0,
            `total` BIGINT NOT NULL DEFAULT 0,
            `amount_refunded` BIGINT NOT NULL DEFAULT 0,
            `billing_details` JSON NULL COMMENT 'address snapshot at creation',
            `due_at` DATETIME NOT NULL,
            `paid_at` DATETIME NULL,
            `cancelled_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_invoices_number` (`number`),
            KEY `idx_nx_invoices_user_status` (`user_id`, `status`),
            KEY `idx_nx_invoices_status_due` (`status`, `due_at`),
            KEY `idx_nx_invoices_service` (`service_id`, `status`),
            CONSTRAINT `fk_nx_invoices_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`),
            CONSTRAINT `fk_nx_invoices_order` FOREIGN KEY (`order_id`) REFERENCES `nx_orders` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_nx_invoices_service` FOREIGN KEY (`service_id`) REFERENCES `nx_services` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_invoice_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `invoice_id` BIGINT UNSIGNED NOT NULL,
            `order_item_id` BIGINT UNSIGNED NULL,
            `service_id` BIGINT UNSIGNED NULL,
            `description` VARCHAR(255) NOT NULL,
            `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
            `unit_amount` BIGINT NOT NULL,
            `tax_rate_bp` INT UNSIGNED NOT NULL DEFAULT 0,
            `total` BIGINT NOT NULL COMMENT 'net line total',
            `period_start` DATETIME NULL,
            `period_end` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nx_invoice_items_invoice` (`invoice_id`),
            CONSTRAINT `fk_nx_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `nx_invoices` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_nx_invoice_items_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `nx_order_items` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_nx_invoice_items_service` FOREIGN KEY (`service_id`) REFERENCES `nx_services` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_transactions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `invoice_id` BIGINT UNSIGNED NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `parent_id` BIGINT UNSIGNED NULL COMMENT 'refund -> original payment',
            `gateway` VARCHAR(30) NOT NULL,
            `type` VARCHAR(20) NOT NULL DEFAULT 'payment',
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `amount` BIGINT NOT NULL,
            `currency` CHAR(3) NOT NULL,
            `reference` VARCHAR(191) NULL COMMENT 'provider transaction id',
            `failure_reason` VARCHAR(255) NULL,
            `metadata` JSON NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_transactions_reference` (`gateway`, `type`, `reference`),
            KEY `idx_nx_transactions_invoice` (`invoice_id`),
            KEY `idx_nx_transactions_user` (`user_id`, `created_at`),
            KEY `idx_nx_transactions_status` (`status`, `created_at`),
            CONSTRAINT `fk_nx_transactions_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `nx_invoices` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_nx_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`),
            CONSTRAINT `fk_nx_transactions_parent` FOREIGN KEY (`parent_id`) REFERENCES `nx_transactions` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_payment_events` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `gateway` VARCHAR(30) NOT NULL,
            `event_type` VARCHAR(100) NOT NULL,
            `reference` VARCHAR(191) NULL,
            `invoice_id` BIGINT UNSIGNED NULL,
            `user_id` BIGINT UNSIGNED NULL,
            `amount` BIGINT NULL,
            `currency` CHAR(3) NULL,
            `payload` JSON NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nx_payment_events_reference` (`reference`),
            KEY `idx_nx_payment_events_invoice` (`invoice_id`),
            KEY `idx_nx_payment_events_created` (`created_at`)
        ) {$options} COMMENT='append-only payment history'");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_webhook_events` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `gateway` VARCHAR(30) NOT NULL,
            `event_id` VARCHAR(191) NOT NULL,
            `event_type` VARCHAR(100) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'received',
            `payload` MEDIUMTEXT NOT NULL,
            `error` TEXT NULL,
            `received_at` DATETIME NOT NULL,
            `processed_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_webhook_events_event` (`gateway`, `event_id`),
            KEY `idx_nx_webhook_events_status` (`status`, `received_at`)
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_credit_ledger` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `amount` BIGINT NOT NULL COMMENT 'positive = credit, negative = debit',
            `balance_after` BIGINT NOT NULL,
            `reason` VARCHAR(255) NOT NULL,
            `reference_type` VARCHAR(30) NULL,
            `reference_id` VARCHAR(64) NULL,
            `actor_id` BIGINT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nx_credit_ledger_user` (`user_id`, `created_at`),
            CONSTRAINT `fk_nx_credit_ledger_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`),
            CONSTRAINT `fk_nx_credit_ledger_actor` FOREIGN KEY (`actor_id`) REFERENCES `nx_users` (`id`) ON DELETE SET NULL
        ) {$options} COMMENT='append-only credit balance history'");
    }
};
