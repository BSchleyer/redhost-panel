<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * Counters for invoice and order numbers. Incremented with a row lock inside
 * the transaction that creates the document, so numbers are unique and have
 * no gaps (a rolled-back transaction also rolls back the counter).
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $db->run('CREATE TABLE IF NOT EXISTS `nx_sequences` (
            `name` VARCHAR(50) NOT NULL,
            `value` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
};
