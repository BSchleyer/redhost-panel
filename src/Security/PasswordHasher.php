<?php

declare(strict_types=1);

namespace Nebrix\Security;

/**
 * Password hashing with Argon2id (falls back to bcrypt only where PHP was
 * built without Argon2 support). Legacy bcrypt hashes from RED-Host verify
 * normally and are upgraded on the next successful login via needsRehash().
 */
final class PasswordHasher
{
    /** A valid hash of a random string, used to equalize timing for unknown users. */
    private ?string $dummyHash = null;

    /**
     * @param array{memory_cost?: int, time_cost?: int, threads?: int} $argonOptions
     */
    public function __construct(private readonly array $argonOptions = [])
    {
    }

    public function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /**
     * @return array<string, int>
     */
    private function options(): array
    {
        if ($this->algorithm() === PASSWORD_BCRYPT) {
            return ['cost' => 12];
        }

        return [
            'memory_cost' => $this->argonOptions['memory_cost'] ?? 65536,
            'time_cost' => $this->argonOptions['time_cost'] ?? 4,
            'threads' => $this->argonOptions['threads'] ?? 1,
        ];
    }

    public function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, $this->algorithm(), $this->options());
    }

    public function verify(#[\SensitiveParameter] string $password, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            // Spend comparable time so that response times do not reveal whether an account exists.
            $this->dummyHash ??= $this->hash(bin2hex(random_bytes(16)));
            password_verify($password, $this->dummyHash);

            return false;
        }

        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm(), $this->options());
    }
}
