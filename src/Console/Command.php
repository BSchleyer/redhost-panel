<?php

declare(strict_types=1);

namespace Nebrix\Console;

interface Command
{
    public static function description(): string;

    /** @return int exit code */
    public function run(Input $input, Output $output): int;
}
