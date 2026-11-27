<?php

declare(strict_types=1);

namespace Nebrix\Database;

use Nebrix\Core\Clock;
use RuntimeException;

/**
 * Applies migrations from database/migrations in filename order.
 *
 * - Each file returns an object implementing Migration.
 * - Applied migrations are recorded in nx_migrations.
 * - A named database lock prevents two deploys from migrating concurrently.
 * - There is intentionally no "down"/rollback command: destructive schema
 *   changes require an explicit, reviewed migration and a backup.
 */
final class Migrator
{
    private const LOCK_NAME = 'nebrix_migrations';

    public function __construct(
        private readonly Connection $db,
        private readonly string $path,
        private readonly Clock $clock,
    ) {
    }

    public function ensureRepository(): void
    {
        $this->db->run(
            'CREATE TABLE IF NOT EXISTS `nx_migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(191) NOT NULL,
                `batch` INT UNSIGNED NOT NULL,
                `applied_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_nx_migrations_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /**
     * @return list<string> migration names (file names without extension)
     */
    public function available(): array
    {
        $files = glob(rtrim($this->path, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);

        return array_map(static fn (string $file): string => basename($file, '.php'), $files);
    }

    /**
     * @return list<string>
     */
    public function applied(): array
    {
        $this->ensureRepository();

        /** @var list<string> */
        return array_map(
            static fn (array $row): string => (string) $row['migration'],
            $this->db->fetchAll('SELECT `migration` FROM `nx_migrations` ORDER BY `id`'),
        );
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        return array_values(array_diff($this->available(), $this->applied()));
    }

    /**
     * @param (callable(string): void)|null $output
     * @return list<string> names of migrations that were applied
     */
    public function migrate(?callable $output = null): array
    {
        $this->ensureRepository();

        $locked = (int) $this->db->fetchValue('SELECT GET_LOCK(?, 30)', [self::LOCK_NAME]);
        if ($locked !== 1) {
            throw new RuntimeException('Another migration process holds the migration lock.');
        }

        try {
            $pending = $this->pending();
            if ($pending === []) {
                return [];
            }

            $batch = (int) $this->db->fetchValue('SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `nx_migrations`');
            foreach ($pending as $name) {
                $migration = require rtrim($this->path, '/') . '/' . $name . '.php';
                if (!$migration instanceof Migration) {
                    throw new RuntimeException(sprintf('Migration %s must return an instance of %s.', $name, Migration::class));
                }

                if ($output !== null) {
                    $output($name);
                }

                $migration->up($this->db);
                $this->db->insert('nx_migrations', [
                    'migration' => $name,
                    'batch' => $batch,
                    'applied_at' => $this->clock->nowString(),
                ]);
            }

            return $pending;
        } finally {
            $this->db->fetchValue('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        }
    }
}
