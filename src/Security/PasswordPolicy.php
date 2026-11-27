<?php

declare(strict_types=1);

namespace Nebrix\Security;

final class PasswordPolicy
{
    public const MIN_LENGTH = 10;
    public const MAX_LENGTH = 256;

    /** Small built-in deny list of the most common passwords. */
    private const COMMON = [
        'password', 'password1', 'password123', '1234567890', '12345678910', 'qwertzuiop', 'qwertyuiop',
        'iloveyou12', 'passwort123', 'passwort1', 'hallo12345', 'letmein123', 'welcome123', 'admin12345',
        'administrator', 'changeme123', '0123456789', 'abcdefghij', 'aaaaaaaaaa', 'football12', 'sommer2024',
        'sommer2025', 'sommer2026', 'winter2025', 'winter2026', 'nebrix1234', 'nebrixpanel', 'qwerty1234',
    ];

    /**
     * @param list<string> $userInputs values the password must not contain (e-mail, username, name)
     * @return list<string> error messages; empty when the password is acceptable
     */
    public static function validate(#[\SensitiveParameter] string $password, array $userInputs = []): array
    {
        $errors = [];
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH) {
            $errors[] = sprintf('The password must be at least %d characters long.', self::MIN_LENGTH);
        }
        if ($length > self::MAX_LENGTH) {
            $errors[] = sprintf('The password must not be longer than %d characters.', self::MAX_LENGTH);
        }

        $lower = mb_strtolower($password);
        if (in_array($lower, self::COMMON, true)) {
            $errors[] = 'This password is too common. Please choose another one.';
        }

        if ($length >= self::MIN_LENGTH && count(array_unique(mb_str_split($lower))) < 4) {
            $errors[] = 'The password must contain more different characters.';
        }

        foreach ($userInputs as $input) {
            $input = mb_strtolower(trim(explode('@', $input)[0]));
            if (mb_strlen($input) >= 4 && str_contains($lower, $input)) {
                $errors[] = 'The password must not contain your e-mail address or username.';
                break;
            }
        }

        return $errors;
    }
}
