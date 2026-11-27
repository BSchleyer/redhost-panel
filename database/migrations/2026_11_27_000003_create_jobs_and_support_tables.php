<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * Background jobs, scheduler bookkeeping and support tickets.
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->run("CREATE TABLE IF NOT EXISTS `nx_jobs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` VARCHAR(100) NOT NULL,
            `payload` JSON NOT NULL,
            `unique_key` VARCHAR(191) NULL COMMENT 'idempotency key; one job per key',
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
            `available_at` DATETIME NOT NULL,
            `reserved_at` DATETIME NULL,
            `reserved_by` VARCHAR(100) NULL,
            `finished_at` DATETIME NULL,
            `last_error` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_jobs_unique_key` (`unique_key`),
            KEY `idx_nx_jobs_status_available` (`status`, `available_at`),
            KEY `idx_nx_jobs_type` (`type`)
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_scheduler_runs` (
            `task` VARCHAR(100) NOT NULL,
            `last_started_at` DATETIME NULL,
            `last_finished_at` DATETIME NULL,
            `last_status` VARCHAR(20) NULL,
            `last_message` TEXT NULL,
            PRIMARY KEY (`task`)
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_tickets` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `service_id` BIGINT UNSIGNED NULL,
            `assigned_to` BIGINT UNSIGNED NULL,
            `subject` VARCHAR(200) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'open',
            `priority` VARCHAR(10) NOT NULL DEFAULT 'normal',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            `closed_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nx_tickets_user` (`user_id`, `status`),
            KEY `idx_nx_tickets_status` (`status`, `updated_at`),
            CONSTRAINT `fk_nx_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`),
            CONSTRAINT `fk_nx_tickets_service` FOREIGN KEY (`service_id`) REFERENCES `nx_services` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_nx_tickets_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `nx_users` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_ticket_messages` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NULL,
            `is_staff` TINYINT(1) NOT NULL DEFAULT 0,
            `message` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nx_ticket_messages_ticket` (`ticket_id`, `created_at`),
            CONSTRAINT `fk_nx_ticket_messages_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `nx_tickets` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_nx_ticket_messages_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`) ON DELETE SET NULL
        ) {$options}");
    }
};
