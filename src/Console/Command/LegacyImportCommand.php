<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Core\Env;
use Nebrix\Database\Connection;
use Nebrix\Legacy\LegacyDataImporter;
use Nebrix\Legacy\LegacyImporter;
use Nebrix\Settings\SettingsRepository;

/**
 * bin/nebrix legacy:import [--dry-run] [--only=users,services,tickets,history]
 *     [--proxmox-nodes=<legacy node>:<integration>,…] [--teamspeak-hosts=<legacy host>:<integration>,…]
 *     [--pterodactyl=<integration>] [--plesk=<integration>]
 *
 * Reads the RED-Host tables from the Nebrix database (when both schemas share
 * one database) or from LEGACY_DB_HOST/LEGACY_DB_DATABASE/LEGACY_DB_USERNAME/
 * LEGACY_DB_PASSWORD when set. Users must be imported before the other steps.
 */
final class LegacyImportCommand implements Command
{
    private const STEPS = ['users', LegacyDataImporter::STEP_SERVICES, LegacyDataImporter::STEP_TICKETS, LegacyDataImporter::STEP_HISTORY];

    public function __construct(
        private readonly LegacyImporter $importer,
        private readonly LegacyDataImporter $dataImporter,
        private readonly Connection $db,
        private readonly SettingsRepository $settings,
    ) {
    }

    public static function description(): string
    {
        return 'Import accounts, services, tickets and payment history from a RED-Host database [--dry-run] [--only=users,services,tickets,history]';
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

        $steps = array_values(array_filter(array_map('trim', explode(',', (string) ($input->option('only') ?? 'users')))));
        $unknown = array_diff($steps, self::STEPS);
        if ($unknown !== []) {
            $output->error('Unknown step(s): ' . implode(', ', $unknown) . '. Use: ' . implode(', ', self::STEPS));

            return 2;
        }

        $dryRun = $input->flag('dry-run');
        $currency = $this->settings->string('general.default_currency') ?: 'EUR';
        $failed = false;

        foreach ($steps as $step) {
            $report = match ($step) {
                'users' => $this->importer->importUsers($source, $dryRun),
                LegacyDataImporter::STEP_SERVICES => $this->dataImporter->importServices($source, [
                    'proxmox' => $this->pairs($input->option('proxmox-nodes')),
                    'teamspeak' => $this->pairs($input->option('teamspeak-hosts')),
                    'pterodactyl' => $input->option('pterodactyl') !== null ? (int) $input->option('pterodactyl') : null,
                    'plesk' => $input->option('plesk') !== null ? (int) $input->option('plesk') : null,
                ], $currency, $dryRun),
                LegacyDataImporter::STEP_TICKETS => $this->dataImporter->importTickets($source, $dryRun),
                default => $this->dataImporter->importHistory($source, $currency, $dryRun),
            };

            $output->info(sprintf('[%s] %s %d record(s), skipped %d.', $step, $dryRun ? 'Would import' : 'Imported', $report['imported'], $report['skipped']));
            foreach ($report['conflicts'] as $conflict) {
                $output->warning($conflict);
            }
            foreach ($report['errors'] as $error) {
                $output->error($error);
            }
            $failed = $failed || $report['errors'] !== [];
        }

        return $failed ? 1 : 0;
    }

    /**
     * Parses "1:3,2:3" into [1 => 3, 2 => 3].
     *
     * @return array<int, int>
     */
    private function pairs(?string $value): array
    {
        $pairs = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $pair) {
            if (preg_match('/^(\d+):(\d+)$/', $pair, $match) === 1) {
                $pairs[(int) $match[1]] = (int) $match[2];
            }
        }

        return $pairs;
    }
}
