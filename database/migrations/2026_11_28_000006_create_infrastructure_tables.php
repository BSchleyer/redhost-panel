<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * Infrastructure features: IP address pools (Proxmox static addressing),
 * single-use console sessions, registrar contacts (domains) and the mapping
 * of imported legacy records. Additive only.
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->run("CREATE TABLE IF NOT EXISTS `nx_ip_pools` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `integration_id` INT UNSIGNED NULL,
            `network` VARCHAR(50) NOT NULL COMMENT 'CIDR, e.g. 203.0.113.0/24',
            `gateway` VARCHAR(45) NOT NULL,
            `dns` VARCHAR(255) NULL COMMENT 'comma-separated resolvers',
            `note` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_ip_pools_name` (`name`),
            CONSTRAINT `fk_nx_ip_pools_integration` FOREIGN KEY (`integration_id`) REFERENCES `nx_integrations` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_ip_addresses` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `pool_id` INT UNSIGNED NOT NULL,
            `address` VARCHAR(45) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'free' COMMENT 'free, assigned, reserved',
            `service_id` BIGINT UNSIGNED NULL,
            `note` VARCHAR(255) NULL,
            `assigned_at` DATETIME NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_ip_addresses_pool_address` (`pool_id`, `address`),
            KEY `idx_nx_ip_addresses_pool_status` (`pool_id`, `status`),
            KEY `idx_nx_ip_addresses_service` (`service_id`),
            CONSTRAINT `fk_nx_ip_addresses_pool` FOREIGN KEY (`pool_id`) REFERENCES `nx_ip_pools` (`id`),
            CONSTRAINT `fk_nx_ip_addresses_service` FOREIGN KEY (`service_id`) REFERENCES `nx_services` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_console_sessions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `token_hash` CHAR(64) NOT NULL,
            `service_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `integration_id` INT UNSIGNED NOT NULL,
            `target` TEXT NOT NULL COMMENT 'encrypted JSON: node, type, vmid, port, ticket',
            `ip` VARCHAR(45) NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_console_sessions_token` (`token_hash`),
            KEY `idx_nx_console_sessions_expires` (`expires_at`),
            CONSTRAINT `fk_nx_console_sessions_service` FOREIGN KEY (`service_id`) REFERENCES `nx_services` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_nx_console_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`) ON DELETE CASCADE
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_registrar_contacts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `integration_id` INT UNSIGNED NOT NULL,
            `contact_hash` CHAR(64) NOT NULL COMMENT 'hash of the submitted contact data',
            `remote_id` VARCHAR(64) NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_registrar_contacts` (`user_id`, `integration_id`, `contact_hash`),
            CONSTRAINT `fk_nx_registrar_contacts_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`),
            CONSTRAINT `fk_nx_registrar_contacts_integration` FOREIGN KEY (`integration_id`) REFERENCES `nx_integrations` (`id`) ON DELETE CASCADE
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_legacy_map` (
            `legacy_table` VARCHAR(64) NOT NULL,
            `legacy_id` BIGINT NOT NULL,
            `entity` VARCHAR(30) NOT NULL,
            `entity_id` BIGINT UNSIGNED NOT NULL,
            `imported_at` DATETIME NOT NULL,
            PRIMARY KEY (`legacy_table`, `legacy_id`),
            KEY `idx_nx_legacy_map_entity` (`entity`, `entity_id`)
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_legacy_records` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NULL,
            `kind` VARCHAR(30) NOT NULL COMMENT 'payment, balance_change',
            `legacy_id` BIGINT NOT NULL,
            `amount` BIGINT NOT NULL,
            `currency` CHAR(3) NOT NULL,
            `description` VARCHAR(255) NULL,
            `data` JSON NULL,
            `occurred_at` DATETIME NULL,
            `imported_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_legacy_records` (`kind`, `legacy_id`),
            KEY `idx_nx_legacy_records_user` (`user_id`, `occurred_at`),
            CONSTRAINT `fk_nx_legacy_records_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`) ON DELETE SET NULL
        ) {$options}");
    }
};
