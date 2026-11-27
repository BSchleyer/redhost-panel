<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Database\Migrator;

final class MigrateStatusCommand implements Command
{
    public function __construct(private readonly Migrator $migrator)
    {
    }

    public static function description(): string
    {
        return 'Show applied and pending migrations';
    }

    public function run(Input $input, Output $output): int
    {
        $applied = $this->migrator->applied();
        foreach ($this->migrator->available() as $name) {
            $output->line(sprintf('  [%s] %s', in_array($name, $applied, true) ? 'x' : ' ', $name));
        }

        return 0;
    }
}
