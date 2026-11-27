<?php

declare(strict_types=1);

namespace Nebrix\Tests\Unit\Security;

use Nebrix\Security\PasswordHasher;
use Nebrix\Security\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordTest extends TestCase
{
    public function testUsesArgon2idAndVerifies(): void
    {
        $hasher = new PasswordHasher(['memory_cost' => 8192, 'time_cost' => 1]);
        $hash = $hasher->hash('Correct-Horse-9');

        self::assertSame(PASSWORD_ARGON2ID, $hasher->algorithm(), 'PHP must be built with Argon2 support.');
        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($hasher->verify('Correct-Horse-9', $hash));
        self::assertFalse($hasher->verify('wrong', $hash));
        self::assertFalse($hasher->verify('anything', null));
    }

    public function testLegacyBcryptHashesVerifyAndNeedRehash(): void
    {
        $legacy = password_hash('legacy-password', PASSWORD_BCRYPT, ['cost' => 10]);
        $hasher = new PasswordHasher();

        self::assertTrue($hasher->verify('legacy-password', $legacy));
        self::assertTrue($hasher->needsRehash($legacy));
    }

    public function testPolicy(): void
    {
        self::assertSame([], PasswordPolicy::validate('a-Very-solid-passphrase', ['ada@example.com']));
        self::assertNotSame([], PasswordPolicy::validate('short'));
        self::assertNotSame([], PasswordPolicy::validate('password123'));
        self::assertNotSame([], PasswordPolicy::validate('aaaaaaaaaaaa'));
        self::assertNotSame([], PasswordPolicy::validate('my-adalovelace-pw', ['adalovelace@example.com']));
    }
}
