<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Integration\IntegrationManager;

final class IntegrationsCheckCommand implements Command
{
    public function __construct(private readonly IntegrationManager $manager)
    {
    }

    public static function description(): string
    {
        return 'Run a health check against every enabled integration';
    }

    public function run(Input $input, Output $output): int
    {
        $failures = 0;
        foreach ($this->manager->checkAll() as $id => $result) {
            $output->line(sprintf('  #%d %-9s %s', $id, $result->status, $result->message));
            $failures += $result->status === 'down' ? 1 : 0;
        }

        return $failures === 0 ? 0 : 1;
    }
}
