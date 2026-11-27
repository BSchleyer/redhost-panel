<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Core\Env;
use Nebrix\Database\Connection;
use Nebrix\Legacy\LegacyImporter;

/**
 * bin/nebrix legacy:import [--dry-run]
 *
 * Reads the RED-Host `users` table from the Nebrix database (when both
 * schemas share one database) or from LEGACY_DB_HOST/LEGACY_DB_DATABASE/
 * LEGACY_DB_USERNAME/LEGACY_DB_PASSWORD when set.
 */
final class LegacyImportCommand implements Command
{
    public function __construct(
        private readonly LegacyImporter $importer,
        private readonly Connection $db,
    ) {
    }

    public static function description(): string
    {
        return 'Import customer accounts and balances from a RED-Host database [--dry-run]';
    }

    public function run(Input $input, Output $output): int
    {
        $source = $this->db;
        if (Env::get('LEGACY_DB_HOST') !== null) {
            $source = new Connection([
                'host' => Env::get('LEGACY_DB_HOST'),
                'port' => Env::int('LEGACY_DB_PORT', 3306),
                'database' => Env::get('LEGACY_DB_DATABASE', ''),
                'username' => Env::get('LEGACY_DB_USERNAME', ''),
                'password' => Env::get('LEGACY_DB_PASSWORD', ''),
            ]);
        }

        $dryRun = $input->flag('dry-run');
        $report = $this->importer->importUsers($source, $dryRun);

        $output->info(sprintf('%s %d user(s), skipped %d already imported.', $dryRun ? 'Would import' : 'Imported', $report['imported'], $report['skipped']));
        foreach ($report['conflicts'] as $conflict) {
            $output->warning($conflict);
        }
        foreach ($report['errors'] as $error) {
            $output->error($error);
        }

        return $report['errors'] === [] ? 0 : 1;
    }
}
