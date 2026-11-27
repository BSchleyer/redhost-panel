<?php

declare(strict_types=1);

use Nebrix\Authorization\Permission;
use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * Core identity, security and configuration tables plus the default roles.
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->run("CREATE TABLE IF NOT EXISTS `nx_roles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(64) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NULL,
            `is_system` TINYINT(1) NOT NULL DEFAULT 0,
            `is_staff` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_roles_slug` (`slug`)
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_role_permissions` (
            `role_id` INT UNSIGNED NOT NULL,
            `permission` VARCHAR(100) NOT NULL,
            PRIMARY KEY (`role_id`, `permission`),
            CONSTRAINT `fk_nx_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `nx_roles` (`id`) ON DELETE CASCADE
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_customer_groups` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NULL,
            `discount_bp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'discount in basis points (100 = 1%)',
            `can_order` TINYINT(1) NOT NULL DEFAULT 1,
            `can_manage_services` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_customer_groups_name` (`name`)
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_users` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `role_id` INT UNSIGNED NOT NULL,
            `customer_group_id` INT UNSIGNED NULL,
            `email` VARCHAR(254) NOT NULL,
            `username` VARCHAR(64) NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `email_verified_at` DATETIME NULL,
            `first_name` VARCHAR(100) NULL,
            `last_name` VARCHAR(100) NULL,
            `company` VARCHAR(150) NULL,
            `vat_id` VARCHAR(50) NULL,
            `address_line1` VARCHAR(255) NULL,
            `address_line2` VARCHAR(255) NULL,
            `postcode` VARCHAR(20) NULL,
            `city` VARCHAR(100) NULL,
            `country` CHAR(2) NULL,
            `phone` VARCHAR(50) NULL,
            `language` VARCHAR(5) NULL,
            `credit_balance` BIGINT NOT NULL DEFAULT 0 COMMENT 'minor units of the default currency',
            `two_factor_secret` TEXT NULL COMMENT 'encrypted',
            `two_factor_enabled_at` DATETIME NULL,
            `two_factor_recovery_codes` TEXT NULL COMMENT 'JSON list of SHA-256 hashes',
            `password_changed_at` DATETIME NULL,
            `last_login_at` DATETIME NULL,
            `last_login_ip` VARCHAR(45) NULL,
            `legal_accepted_at` DATETIME NULL,
            `notes` TEXT NULL,
            `legacy_id` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_users_email` (`email`),
            UNIQUE KEY `uniq_nx_users_username` (`username`),
            UNIQUE KEY `uniq_nx_users_legacy_id` (`legacy_id`),
            KEY `idx_nx_users_status` (`status`),
            KEY `idx_nx_users_created_at` (`created_at`),
            CONSTRAINT `fk_nx_users_role` FOREIGN KEY (`role_id`) REFERENCES `nx_roles` (`id`),
            CONSTRAINT `fk_nx_users_group` FOREIGN KEY (`customer_group_id`) REFERENCES `nx_customer_groups` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_user_tokens` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `type` VARCHAR(30) NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_user_tokens_hash` (`token_hash`),
            KEY `idx_nx_user_tokens_user_type` (`user_id`, `type`),
            CONSTRAINT `fk_nx_user_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`) ON DELETE CASCADE
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_sessions` (
            `id` CHAR(64) NOT NULL COMMENT 'SHA-256 of the session ID',
            `user_id` BIGINT UNSIGNED NULL,
            `ip` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `payload` MEDIUMTEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            `last_activity` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nx_sessions_user` (`user_id`),
            KEY `idx_nx_sessions_last_activity` (`last_activity`),
            CONSTRAINT `fk_nx_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`) ON DELETE CASCADE
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_rate_limits` (
            `key_hash` CHAR(64) NOT NULL,
            `hits` INT UNSIGNED NOT NULL DEFAULT 0,
            `expires_at` DATETIME NOT NULL,
            PRIMARY KEY (`key_hash`),
            KEY `idx_nx_rate_limits_expires` (`expires_at`)
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_login_history` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NULL,
            `identifier` VARCHAR(254) NULL,
            `ip` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `success` TINYINT(1) NOT NULL,
            `reason` VARCHAR(50) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nx_login_history_user` (`user_id`, `created_at`),
            KEY `idx_nx_login_history_created` (`created_at`),
            CONSTRAINT `fk_nx_login_history_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_audit_log` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `actor_id` BIGINT UNSIGNED NULL,
            `action` VARCHAR(100) NOT NULL,
            `target_type` VARCHAR(50) NULL,
            `target_id` VARCHAR(64) NULL,
            `ip` VARCHAR(45) NULL,
            `details` JSON NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nx_audit_action` (`action`),
            KEY `idx_nx_audit_target` (`target_type`, `target_id`),
            KEY `idx_nx_audit_actor` (`actor_id`),
            KEY `idx_nx_audit_created` (`created_at`),
            CONSTRAINT `fk_nx_audit_actor` FOREIGN KEY (`actor_id`) REFERENCES `nx_users` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_settings` (
            `key` VARCHAR(100) NOT NULL,
            `value` MEDIUMTEXT NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`key`),
            CONSTRAINT `fk_nx_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `nx_users` (`id`) ON DELETE SET NULL
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_mail_templates` (
            `key` VARCHAR(100) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `body_html` MEDIUMTEXT NOT NULL,
            `body_text` MEDIUMTEXT NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`key`),
            CONSTRAINT `fk_nx_mail_templates_user` FOREIGN KEY (`updated_by`) REFERENCES `nx_users` (`id`) ON DELETE SET NULL
        ) {$options}");

        $this->seedRoles($db);
    }

    private function seedRoles(Connection $db): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $roles = [
            ['super_admin', 'Super Administrator', 'Full access to every function, including roles and permissions.', 1],
            ['admin', 'Administrator', 'Manages the platform, customers, products, integrations and settings.', 1],
            ['support', 'Support', 'Handles customer requests, tickets and service operations.', 1],
            ['finance', 'Finance', 'Handles orders, invoices, payments and refunds.', 1],
            ['customer', 'Customer', 'Customer account without access to the administration area.', 0],
        ];

        $defaults = Permission::defaults();
        foreach ($roles as [$slug, $name, $description, $isStaff]) {
            $existing = $db->fetchValue('SELECT `id` FROM `nx_roles` WHERE `slug` = ?', [$slug]);
            if ($existing !== null) {
                continue;
            }

            $roleId = $db->insert('nx_roles', [
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'is_system' => 1,
                'is_staff' => $isStaff,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($defaults[$slug] ?? [] as $permission) {
                $db->insert('nx_role_permissions', ['role_id' => $roleId, 'permission' => $permission]);
            }
        }
    }
};
