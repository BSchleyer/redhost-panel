<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Security\Encrypter;

final class KeyGenerateCommand implements Command
{
    public static function description(): string
    {
        return 'Print a new random APP_KEY (does not modify any file)';
    }

    public function run(Input $input, Output $output): int
    {
        $output->line(Encrypter::generateKey());
        $output->line('');
        $output->line('Add this value as APP_KEY to your .env file. Keep it secret and back it up:');
        $output->line('it encrypts stored credentials. When rotating, move the old key to APP_PREVIOUS_KEYS.');

        return 0;
    }
}
