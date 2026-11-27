<?php

declare(strict_types=1);

namespace Nebrix\Console;

class Output
{
    public function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    public function info(string $message): void
    {
        $this->line('[info] ' . $message);
    }

    public function success(string $message): void
    {
        $this->line('[ok] ' . $message);
    }

    public function warning(string $message): void
    {
        fwrite(STDERR, '[warning] ' . $message . PHP_EOL);
    }

    public function error(string $message): void
    {
        fwrite(STDERR, '[error] ' . $message . PHP_EOL);
    }
}
