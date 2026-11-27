<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * Stores the last accepted TOTP time slice so a code cannot be replayed
 * within its validity window. Additive change only.
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $exists = $db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nx_users' AND COLUMN_NAME = 'two_factor_last_slice'",
        );

        if ((int) $exists === 0) {
            $db->run('ALTER TABLE `nx_users` ADD COLUMN `two_factor_last_slice` BIGINT UNSIGNED NULL AFTER `two_factor_recovery_codes`');
        }
    }
};
