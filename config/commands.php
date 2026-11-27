<?php

declare(strict_types=1);

use Nebrix\Console\Command;

/*
 * CLI command registry for bin/nebrix.
 *
 * @return array<string, class-string<\Nebrix\Console\Command>>
 */
return [
    'key:generate' => Command\KeyGenerateCommand::class,
    'migrate' => Command\MigrateCommand::class,
    'migrate:status' => Command\MigrateStatusCommand::class,
    'user:create-admin' => Command\CreateAdminCommand::class,
    'integrations:check' => Command\IntegrationsCheckCommand::class,
    'queue:work' => Command\QueueWorkCommand::class,
    'schedule:run' => Command\ScheduleRunCommand::class,
    'legacy:import' => Command\LegacyImportCommand::class,
    'console:proxy' => Command\ConsoleProxyCommand::class,
    'db:triggers' => Command\DbTriggersCommand::class,
];
