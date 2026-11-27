<?php

declare(strict_types=1);

namespace Nebrix\Tests\Unit\Security;

use Nebrix\Security\Encrypter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncrypterTest extends TestCase
{
    public function testRoundTripWithContext(): void
    {
        $encrypter = Encrypter::fromAppKey(Encrypter::generateKey());
        $cipher = $encrypter->encrypt('api-token-value', 'integration:secrets');

        self::assertStringStartsWith('nx1:', $cipher);
        self::assertStringNotContainsString('api-token-value', $cipher);
        self::assertSame('api-token-value', $encrypter->decrypt($cipher, 'integration:secrets'));
    }

    public function testNonceIsRandom(): void
    {
        $encrypter = Encrypter::fromAppKey(Encrypter::generateKey());

        self::assertNotSame($encrypter->encrypt('same'), $encrypter->encrypt('same'));
    }

    public function testCiphertextIsBoundToContext(): void
    {
        $encrypter = Encrypter::fromAppKey(Encrypter::generateKey());
        $cipher = $encrypter->encrypt('secret', 'setting:mail.password');

        $this->expectException(RuntimeException::class);
        $encrypter->decrypt($cipher, 'setting:payments.stripe.secret_key');
    }

    public function testTamperingIsDetected(): void
    {
        $encrypter = Encrypter::fromAppKey(Encrypter::generateKey());
        $cipher = $encrypter->encrypt('secret');
        // Flip one character inside the authentication tag (always different from the original).
        $position = strlen($cipher) - 5;
        $tampered = substr_replace($cipher, $cipher[$position] === 'A' ? 'B' : 'A', $position, 1);
        self::assertNotSame($cipher, $tampered);

        $this->expectException(RuntimeException::class);
        $encrypter->decrypt($tampered);
    }

    public function testPreviousKeysAllowRotation(): void
    {
        $oldKey = Encrypter::generateKey();
        $cipher = Encrypter::fromAppKey($oldKey)->encrypt('legacy');

        $rotated = Encrypter::fromAppKey(Encrypter::generateKey(), [$oldKey]);
        self::assertSame('legacy', $rotated->decrypt($cipher));

        $this->expectException(RuntimeException::class);
        Encrypter::fromAppKey(Encrypter::generateKey())->decrypt($cipher);
    }

    public function testArrayHelpers(): void
    {
        $encrypter = Encrypter::fromAppKey(Encrypter::generateKey());
        $payload = ['token' => 'abc', 'nested' => ['x' => 1]];

        self::assertSame($payload, $encrypter->decryptArray($encrypter->encryptArray($payload, 'ctx'), 'ctx'));
    }

    public function testRejectsInvalidKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Encrypter::fromAppKey('not-a-key');
    }
}
