<?php

declare(strict_types=1);

namespace Nebrix\Security;

use InvalidArgumentException;
use RuntimeException;

/**
 * Authenticated encryption for secrets at rest (integration credentials,
 * payment API keys, SMTP password, 2FA secrets, generated service passwords).
 *
 * XChaCha20-Poly1305 (libsodium) with a random 192-bit nonce. A context
 * string is bound as associated data so a ciphertext copied into another
 * column fails to decrypt. Old keys can be supplied for decryption to support
 * key rotation (APP_PREVIOUS_KEYS).
 */
final class Encrypter
{
    private const PREFIX = 'nx1:';

    /**
     * @param list<string> $previousKeys raw 32-byte keys
     */
    public function __construct(
        #[\SensitiveParameter] private readonly string $key,
        #[\SensitiveParameter] private readonly array $previousKeys = [],
    ) {
        if (strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new InvalidArgumentException('Encryption key must be exactly 32 bytes.');
        }
    }

    /**
     * @param list<string> $previous
     */
    public static function fromAppKey(#[\SensitiveParameter] string $appKey, #[\SensitiveParameter] array $previous = []): self
    {
        return new self(self::decodeKey($appKey), array_map(self::decodeKey(...), $previous));
    }

    public static function decodeKey(#[\SensitiveParameter] string $appKey): string
    {
        $appKey = trim($appKey);
        if (!str_starts_with($appKey, 'base64:')) {
            throw new InvalidArgumentException('APP_KEY must start with "base64:". Generate one with: bin/nebrix key:generate');
        }

        $raw = base64_decode(substr($appKey, 7), true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new InvalidArgumentException('APP_KEY must decode to exactly 32 bytes.');
        }

        return $raw;
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    public function encrypt(#[\SensitiveParameter] string $plaintext, string $context = ''): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, self::PREFIX . $context, $nonce, $this->key);

        return self::PREFIX . sodium_bin2base64($nonce . $cipher, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    public function decrypt(string $payload, string $context = ''): string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            throw new RuntimeException('Unsupported encrypted payload format.');
        }

        try {
            $raw = sodium_base642bin(substr($payload, strlen(self::PREFIX)), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException) {
            throw new RuntimeException('Encrypted payload is not valid base64.');
        }

        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($raw) <= $nonceLength) {
            throw new RuntimeException('Encrypted payload is too short.');
        }

        $nonce = substr($raw, 0, $nonceLength);
        $cipher = substr($raw, $nonceLength);

        foreach ([$this->key, ...$this->previousKeys] as $key) {
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, self::PREFIX . $context, $nonce, $key);
            if ($plain !== false) {
                return $plain;
            }
        }

        throw new RuntimeException('Unable to decrypt payload (wrong key or tampered data).');
    }

    /**
     * @param array<mixed> $data
     */
    public function encryptArray(#[\SensitiveParameter] array $data, string $context = ''): string
    {
        return $this->encrypt(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $context);
    }

    /**
     * @return array<mixed>
     */
    public function decryptArray(string $payload, string $context = ''): array
    {
        $decoded = json_decode($this->decrypt($payload, $context), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }
}
