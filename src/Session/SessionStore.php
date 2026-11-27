<?php

declare(strict_types=1);

namespace Nebrix\Session;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

/**
 * Database-backed session persistence.
 *
 * Only the SHA-256 hash of a session ID is stored, so a database leak does
 * not allow session hijacking (legacy finding C-05). Sessions expire after an
 * idle timeout and after an absolute lifetime.
 */
class SessionStore
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly int $idleTimeoutSeconds,
        private readonly int $absoluteLifetimeSeconds,
    ) {
    }

    public static function hashId(string $id): string
    {
        return hash('sha256', $id);
    }

    public function create(): Session
    {
        $now = $this->clock->now()->getTimestamp();

        return new Session(Session::generateId(), [], true, null, $now, $now);
    }

    public function load(string $id): ?Session
    {
        if (preg_match('/^[a-f0-9]{64}$/', $id) !== 1) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT `user_id`, `payload`, `created_at`, `last_activity` FROM `nx_sessions` WHERE `id` = ?',
            [self::hashId($id)],
        );
        if ($row === null) {
            return null;
        }

        $now = $this->clock->now()->getTimestamp();
        $createdAt = strtotime($row['created_at'] . ' UTC') ?: 0;
        $lastActivity = strtotime($row['last_activity'] . ' UTC') ?: 0;

        if ($now - $lastActivity > $this->idleTimeoutSeconds || $now - $createdAt > $this->absoluteLifetimeSeconds) {
            $this->destroy($id);

            return null;
        }

        $data = json_decode((string) $row['payload'], true);

        return new Session(
            $id,
            is_array($data) ? $data : [],
            false,
            $row['user_id'] !== null ? (int) $row['user_id'] : null,
            $createdAt,
            $lastActivity,
        );
    }

    /**
     * Persists the session.
     *
     * New and regenerated IDs are inserted; an existing session is only
     * updated, never re-inserted. A request that loaded a session which was
     * deleted while the request ran (logout elsewhere, password change,
     * administrative revocation) therefore cannot bring it back.
     */
    public function save(Session $session, string $ip, string $userAgent): void
    {
        $previous = $session->previousId();
        if ($previous !== null) {
            $deleted = $this->db->run('DELETE FROM `nx_sessions` WHERE `id` = ?', [self::hashId($previous)])->rowCount();
            if ($deleted === 0 && !$session->isNew()) {
                return; // revoked during this request – stays revoked under the new ID as well
            }
        }

        if ($session->isEmpty() && ($session->isNew() || $previous !== null)) {
            // Do not persist empty anonymous sessions (bots, static pages).
            return;
        }

        $now = $this->clock->now();
        $mustTouch = $now->getTimestamp() - $session->lastActivity() >= 60;
        if (!$session->isDirty() && !$session->isNew() && $previous === null && !$mustTouch) {
            return;
        }

        $payload = json_encode($session->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($session->isNew() || $previous !== null) {
            // A freshly generated ID is unknown to anyone else, so an upsert is
            // safe here (it only matters if the same request saves twice).
            $this->db->run(
                'INSERT INTO `nx_sessions` (`id`, `user_id`, `ip`, `user_agent`, `payload`, `created_at`, `last_activity`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `user_id` = ?, `ip` = ?, `user_agent` = ?, `payload` = ?, `last_activity` = ?',
                [
                    self::hashId($session->id()), $session->userId(), $ip, $userAgent, $payload, $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'),
                    $session->userId(), $ip, $userAgent, $payload, $now->format('Y-m-d H:i:s'),
                ],
            );

            return;
        }

        // Zero affected rows means the session was deleted meanwhile; it is
        // deliberately not recreated.
        $this->db->run(
            'UPDATE `nx_sessions` SET `user_id` = ?, `ip` = ?, `user_agent` = ?, `payload` = ?, `last_activity` = ? WHERE `id` = ?',
            [$session->userId(), $ip, $userAgent, $payload, $now->format('Y-m-d H:i:s'), self::hashId($session->id())],
        );
    }

    public function destroy(string $id): void
    {
        $this->db->run('DELETE FROM `nx_sessions` WHERE `id` = ?', [self::hashId($id)]);
    }

    /**
     * Terminates all sessions of a user, optionally keeping the current one
     * (used after password changes and by "log out other devices").
     */
    public function destroyForUser(int $userId, ?string $exceptId = null): int
    {
        if ($exceptId !== null) {
            return $this->db->run(
                'DELETE FROM `nx_sessions` WHERE `user_id` = ? AND `id` <> ?',
                [$userId, self::hashId($exceptId)],
            )->rowCount();
        }

        return $this->db->run('DELETE FROM `nx_sessions` WHERE `user_id` = ?', [$userId])->rowCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT `id`, `ip`, `user_agent`, `created_at`, `last_activity` FROM `nx_sessions` WHERE `user_id` = ? ORDER BY `last_activity` DESC',
            [$userId],
        );
    }

    public function garbageCollect(): int
    {
        $now = $this->clock->now()->getTimestamp();

        return $this->db->run(
            'DELETE FROM `nx_sessions` WHERE `last_activity` < ? OR `created_at` < ?',
            [gmdate('Y-m-d H:i:s', $now - $this->idleTimeoutSeconds), gmdate('Y-m-d H:i:s', $now - $this->absoluteLifetimeSeconds)],
        )->rowCount();
    }
}
