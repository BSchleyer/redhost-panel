<?php

declare(strict_types=1);

namespace Nebrix\Security;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

/**
 * Fixed-window rate limiter backed by nx_rate_limits.
 *
 * The counter is incremented atomically with INSERT … ON DUPLICATE KEY UPDATE,
 * so concurrent requests cannot bypass the limit (legacy finding H-06).
 * Keys are hashed before storage (they may contain e-mail addresses / IPs).
 */
class RateLimiter
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Registers a hit and returns the number of hits in the current window.
     */
    public function hit(string $key, int $decaySeconds): int
    {
        $now = $this->clock->now();
        $nowString = $now->format('Y-m-d H:i:s');
        $expiresAt = $now->modify(sprintf('+%d seconds', $decaySeconds))->format('Y-m-d H:i:s');
        $hash = hash('sha256', $key);

        $this->db->run(
            'INSERT INTO `nx_rate_limits` (`key_hash`, `hits`, `expires_at`) VALUES (:k, 1, :expires)
             ON DUPLICATE KEY UPDATE
                `hits` = IF(`expires_at` <= :now1, 1, `hits` + 1),
                `expires_at` = IF(`expires_at` <= :now2, :expires2, `expires_at`)',
            ['k' => $hash, 'expires' => $expiresAt, 'now1' => $nowString, 'now2' => $nowString, 'expires2' => $expiresAt],
        );

        return (int) $this->db->fetchValue('SELECT `hits` FROM `nx_rate_limits` WHERE `key_hash` = ?', [$hash]);
    }

    public function attempts(string $key): int
    {
        $row = $this->db->fetchOne(
            'SELECT `hits` FROM `nx_rate_limits` WHERE `key_hash` = ? AND `expires_at` > ?',
            [hash('sha256', $key), $this->clock->nowString()],
        );

        return $row === null ? 0 : (int) $row['hits'];
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    /** Seconds until the key's window resets. */
    public function availableIn(string $key): int
    {
        $expires = $this->db->fetchValue(
            'SELECT `expires_at` FROM `nx_rate_limits` WHERE `key_hash` = ?',
            [hash('sha256', $key)],
        );
        if (!is_string($expires)) {
            return 0;
        }

        return max(0, (strtotime($expires . ' UTC') ?: 0) - $this->clock->now()->getTimestamp());
    }

    /**
     * Counts an attempt and reports whether it is still within the limit.
     *
     * The atomic increment decides, not a preceding read: concurrent requests
     * cannot all pass a check before any of them is counted.
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return $this->hit($key, $decaySeconds) <= $maxAttempts;
    }

    /** Takes back one counted attempt (e.g. a successful sign-in from an IP). */
    public function release(string $key): void
    {
        $this->db->run('UPDATE `nx_rate_limits` SET `hits` = IF(`hits` > 0, `hits` - 1, 0) WHERE `key_hash` = ?', [hash('sha256', $key)]);
    }

    public function clear(string $key): void
    {
        $this->db->run('DELETE FROM `nx_rate_limits` WHERE `key_hash` = ?', [hash('sha256', $key)]);
    }

    public function garbageCollect(): int
    {
        return $this->db->run('DELETE FROM `nx_rate_limits` WHERE `expires_at` < ?', [$this->clock->nowString()])->rowCount();
    }
}
