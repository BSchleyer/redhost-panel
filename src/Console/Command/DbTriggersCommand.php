<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Database\AppendOnlyTriggers;

/**
 * bin/nebrix db:triggers [--install|--remove]
 */
final class DbTriggersCommand implements Command
{
    public function __construct(private readonly AppendOnlyTriggers $triggers)
    {
    }

    public static function description(): string
    {
        return 'Show, install or remove the append-only triggers for payment events, audit log and credit ledger [--install|--remove]';
    }

    public function run(Input $input, Output $output): int
    {
        try {
            if ($input->flag('install')) {
                $output->info(sprintf('%d trigger(s) created.', $this->triggers->install()));
            } elseif ($input->flag('remove')) {
                $output->info(sprintf('%d trigger(s) removed.', $this->triggers->remove()));
            }
        } catch (\PDOException $exception) {
            $output->error('The triggers could not be changed: ' . $exception->getMessage());
            $output->line('The database user needs the TRIGGER privilege (see DATABASE.md).');

            return 1;
        }

        foreach ($this->triggers->status() as $name => $exists) {
            $output->line(sprintf('%-32s %s', $name, $exists ? 'installed' : 'missing'));
        }

        return 0;
    }
}
