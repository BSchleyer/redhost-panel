<?php

declare(strict_types=1);

namespace Nebrix\Console;

final class Input
{
    /**
     * @param array<string, string|true> $options
     * @param list<string> $arguments
     */
    public function __construct(
        private readonly array $options,
        private readonly array $arguments,
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public static function fromArgv(array $argv): self
    {
        $options = [];
        $arguments = [];
        foreach ($argv as $token) {
            if (str_starts_with($token, '--')) {
                [$key, $value] = array_pad(explode('=', substr($token, 2), 2), 2, null);
                $options[$key] = $value ?? true;
            } else {
                $arguments[] = $token;
            }
        }

        return new self($options, $arguments);
    }

    public function option(string $name, ?string $default = null): ?string
    {
        $value = $this->options[$name] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function flag(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments[$index] ?? $default;
    }

    /** Reads a line from STDIN; with $hidden the terminal echo is disabled. */
    public function ask(string $question, bool $hidden = false): string
    {
        fwrite(STDOUT, $question . ' ');
        $sttyAvailable = $hidden && function_exists('shell_exec') && stream_isatty(STDIN);
        if ($sttyAvailable) {
            shell_exec('stty -echo');
        }
        $line = fgets(STDIN);
        if ($sttyAvailable) {
            shell_exec('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }

        return $line === false ? '' : trim($line);
    }
}
