<?php

declare(strict_types=1);

namespace Nebrix\Console;

use Nebrix\Core\Container;
use Throwable;

/**
 * Minimal command runner for bin/nebrix.
 *
 * Usage: bin/nebrix <command> [--option=value] [--flag] [arguments…]
 */
final class Console
{
    /**
     * @param array<string, class-string<Command>> $commands
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $commands,
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $name = $argv[1] ?? 'list';
        $input = Input::fromArgv(array_slice($argv, 2));
        $output = new Output();

        if ($name === 'list' || $name === 'help' || $name === '--help') {
            $this->listCommands($output);

            return 0;
        }

        $class = $this->commands[$name] ?? null;
        if ($class === null) {
            $output->error(sprintf('Unknown command "%s".', $name));
            $this->listCommands($output);

            return 1;
        }

        try {
            /** @var Command $command */
            $command = $this->container->get($class);

            return $command->run($input, $output);
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            $this->container->get(\Psr\Log\LoggerInterface::class)->error('Console command failed', [
                'command' => $name,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return 1;
        }
    }

    private function listCommands(Output $output): void
    {
        $output->line('Nebrix console');
        $output->line('');
        $output->line('Available commands:');
        $commands = $this->commands;
        ksort($commands);
        foreach ($commands as $name => $class) {
            $output->line(sprintf('  %-24s %s', $name, $class::description()));
        }
    }
}
