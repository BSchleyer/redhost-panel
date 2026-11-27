<?php

declare(strict_types=1);

namespace Nebrix\Database;

/**
 * A forward-only schema migration.
 *
 * Migrations must be additive and must never drop or truncate tables that may
 * contain data. Note that MySQL/MariaDB commit DDL statements implicitly, so a
 * migration cannot be rolled back automatically: take a backup before running
 * `bin/nebrix migrate` in production.
 */
interface Migration
{
    public function up(Connection $db): void;
}
