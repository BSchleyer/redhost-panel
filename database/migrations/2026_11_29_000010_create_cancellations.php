<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * Contract cancellations and withdrawals (§ 312k BGB cancellation button,
 * EU withdrawal function): every declaration is stored with its receipt
 * time; services get the date their cancellation takes effect. Additive only.
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->run("CREATE TABLE IF NOT EXISTS `nx_cancellations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(20) NOT NULL,
            `kind` VARCHAR(20) NOT NULL COMMENT 'ordinary, extraordinary, withdrawal',
            `user_id` BIGINT UNSIGNED NULL,
            `service_id` BIGINT UNSIGNED NULL,
            `name` VARCHAR(200) NOT NULL,
            `email` VARCHAR(254) NOT NULL,
            `customer_reference` VARCHAR(255) NULL COMMENT 'customer number or account e-mail entered by a guest',
            `contract` VARCHAR(500) NOT NULL COMMENT 'contract identification as entered',
            `reason` TEXT NULL,
            `requested_date` DATE NULL COMMENT 'NULL = earliest possible date',
            `effective_at` DATETIME NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'received' COMMENT 'received, scheduled, completed, rejected, withdrawn',
            `ip` VARCHAR(45) NULL,
            `received_at` DATETIME NOT NULL,
            `processed_by` BIGINT UNSIGNED NULL,
            `processed_at` DATETIME NULL,
            `staff_note` TEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_cancellations_reference` (`reference`),
            KEY `idx_nx_cancellations_status` (`status`, `received_at`),
            KEY `idx_nx_cancellations_user` (`user_id`),
            KEY `idx_nx_cancellations_service` (`service_id`),
            CONSTRAINT `fk_nx_cancellations_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_nx_cancellations_service` FOREIGN KEY (`service_id`) REFERENCES `nx_services` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_nx_cancellations_staff` FOREIGN KEY (`processed_by`) REFERENCES `nx_users` (`id`) ON DELETE SET NULL
        ) {$options}");

        $exists = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nx_services' AND COLUMN_NAME = 'cancel_at'",
        );
        if ($exists === 0) {
            $db->run('ALTER TABLE `nx_services` ADD COLUMN `cancel_at` DATETIME NULL AFTER `next_due_at`, ADD COLUMN `cancellation_id` BIGINT UNSIGNED NULL AFTER `cancel_at`, ADD KEY `idx_nx_services_cancel_at` (`cancel_at`)');
        }
    }
};
