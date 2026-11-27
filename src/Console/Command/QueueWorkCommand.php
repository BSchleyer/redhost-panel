<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Queue\Worker;

final class QueueWorkCommand implements Command
{
    private bool $stop = false;

    public function __construct(private readonly Worker $worker)
    {
    }

    public static function description(): string
    {
        return 'Process background jobs [--once] [--max-jobs=N] [--max-time=SECONDS] [--sleep=3]';
    }

    public function run(Input $input, Output $output): int
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->stop = true);
            pcntl_signal(SIGINT, fn () => $this->stop = true);
        }

        $workerId = gethostname() . ':' . getmypid();
        if ($input->flag('once')) {
            $output->line($this->worker->runOnce($workerId) ? 'Processed one job.' : 'No job available.');

            return 0;
        }

        // Restart periodically (default: 1 hour) so memory and code updates are picked up.
        $processed = $this->worker->run(
            $workerId,
            (int) ($input->option('max-jobs') ?? 0),
            (int) ($input->option('max-time') ?? 3600),
            max(1, (int) ($input->option('sleep') ?? 3)),
            fn (): bool => $this->stop,
        );
        $output->info(sprintf('Worker stopped after %d job(s).', $processed));

        return 0;
    }
}
