<?php

declare(strict_types=1);

namespace Nebrix\Auth;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

/**
 * Single-use, expiring tokens for e-mail verification and password resets
 * (legacy finding H-05). Only the SHA-256 hash of a token is stored.
 */
class TokenService
{
    public const EMAIL_VERIFICATION = 'email_verification';
    public const PASSWORD_RESET = 'password_reset';

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /** Creates a token and invalidates older unused tokens of the same type. */
    public function create(int $userId, string $type, int $ttlMinutes): string
    {
        $this->invalidate($userId, $type);

        $token = bin2hex(random_bytes(32));
        $now = $this->clock->now();
        $this->db->insert('nx_user_tokens', [
            'user_id' => $userId,
            'type' => $type,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $now->modify(sprintf('+%d minutes', $ttlMinutes))->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * Validates and consumes a token atomically.
     *
     * @return int|null the user ID, or null when the token is unknown, used or expired
     */
    public function consume(string $token, string $type): ?int
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        return $this->db->transaction(function () use ($token, $type): ?int {
            $row = $this->db->fetchOne(
                'SELECT `id`, `user_id`, `expires_at`, `used_at` FROM `nx_user_tokens` WHERE `token_hash` = ? AND `type` = ? FOR UPDATE',
                [hash('sha256', $token), $type],
            );

            if ($row === null || $row['used_at'] !== null || (string) $row['expires_at'] < $this->clock->nowString()) {
                return null;
            }

            $this->db->update('nx_user_tokens', ['used_at' => $this->clock->nowString()], ['id' => (int) $row['id']]);

            return (int) $row['user_id'];
        });
    }

    /** Checks a token without consuming it (to render the reset form). */
    public function isValid(string $token, string $type): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return false;
        }

        return $this->db->fetchValue(
            'SELECT COUNT(*) FROM `nx_user_tokens` WHERE `token_hash` = ? AND `type` = ? AND `used_at` IS NULL AND `expires_at` >= ?',
            [hash('sha256', $token), $type, $this->clock->nowString()],
        ) > 0;
    }

    public function invalidate(int $userId, string $type): void
    {
        $this->db->run(
            'UPDATE `nx_user_tokens` SET `used_at` = ? WHERE `user_id` = ? AND `type` = ? AND `used_at` IS NULL',
            [$this->clock->nowString(), $userId, $type],
        );
    }

    public function prune(): int
    {
        return $this->db->run(
            'DELETE FROM `nx_user_tokens` WHERE `expires_at` < ?',
            [$this->clock->now()->modify('-7 days')->format('Y-m-d H:i:s')],
        )->rowCount();
    }
}
