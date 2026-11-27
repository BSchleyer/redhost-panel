<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * REST API: personal access tokens (stored as SHA-256 hashes) and
 * idempotency records for POST requests. Additive only; also grants the new
 * permission "api_tokens.manage" to the built-in administrator role.
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->run("CREATE TABLE IF NOT EXISTS `nx_api_tokens` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `token_prefix` CHAR(8) NOT NULL COMMENT 'first characters of the secret, for display only',
            `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 of the full token',
            `scopes` TEXT NOT NULL COMMENT 'JSON list',
            `allowed_ips` TEXT NULL COMMENT 'JSON list of addresses/CIDR ranges',
            `expires_at` DATETIME NOT NULL,
            `last_used_at` DATETIME NULL,
            `last_used_ip` VARCHAR(45) NULL,
            `revoked_at` DATETIME NULL,
            `created_ip` VARCHAR(45) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_api_tokens_hash` (`token_hash`),
            KEY `idx_nx_api_tokens_user` (`user_id`, `revoked_at`),
            CONSTRAINT `fk_nx_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `nx_users` (`id`) ON DELETE CASCADE
        ) {$options}");

        $db->run("CREATE TABLE IF NOT EXISTS `nx_api_idempotency` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `token_id` BIGINT UNSIGNED NOT NULL,
            `key_hash` CHAR(64) NOT NULL,
            `request_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 of method, path and body',
            `status_code` SMALLINT UNSIGNED NULL COMMENT 'NULL while the request is running',
            `response` MEDIUMTEXT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_nx_api_idempotency_key` (`token_id`, `key_hash`),
            KEY `idx_nx_api_idempotency_created` (`created_at`),
            CONSTRAINT `fk_nx_api_idempotency_token` FOREIGN KEY (`token_id`) REFERENCES `nx_api_tokens` (`id`) ON DELETE CASCADE
        ) {$options}");

        $adminRole = $db->fetchValue("SELECT `id` FROM `nx_roles` WHERE `slug` = 'admin'");
        if ($adminRole !== null) {
            $db->run('INSERT IGNORE INTO `nx_role_permissions` (`role_id`, `permission`) VALUES (?, ?)', [(int) $adminRole, 'api_tokens.manage']);
        }
    }
};
