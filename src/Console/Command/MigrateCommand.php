<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Database\Migrator;

final class MigrateCommand implements Command
{
    public function __construct(private readonly Migrator $migrator)
    {
    }

    public static function description(): string
    {
        return 'Apply pending database migrations (forward-only; back up first)';
    }

    public function run(Input $input, Output $output): int
    {
        $pending = $this->migrator->pending();
        if ($pending === []) {
            $output->success('Database is up to date.');

            return 0;
        }

        if ($input->flag('pretend')) {
            foreach ($pending as $name) {
                $output->line('  would apply: ' . $name);
            }

            return 0;
        }

        $applied = $this->migrator->migrate(static fn (string $name) => $output->info('Applying ' . $name));
        $output->success(sprintf('Applied %d migration(s).', count($applied)));

        return 0;
    }
}
