<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Scheduler\Scheduler;

final class ScheduleRunCommand implements Command
{
    public function __construct(private readonly Scheduler $scheduler)
    {
    }

    public static function description(): string
    {
        return 'Run due scheduled tasks (call every minute from cron) [--force]';
    }

    public function run(Input $input, Output $output): int
    {
        foreach ($this->scheduler->run($input->flag('force')) as $task => $result) {
            $output->line(sprintf('  %-28s %s', $task, $result));
        }

        return 0;
    }
}
