<?php

declare(strict_types=1);

namespace Nebrix\Queue;

use DateTimeImmutable;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use PDOException;

/**
 * Database-backed job queue (nx_jobs).
 *
 * - Jobs can carry a unique key; dispatching the same key twice returns the
 *   existing job instead of creating a duplicate (idempotent provisioning).
 * - Workers reserve jobs atomically with SELECT … FOR UPDATE SKIP LOCKED, so
 *   several workers never run the same job (legacy finding M-09).
 * - Failures are retried with exponential backoff; exhausted jobs stay in
 *   status "failed" and can be retried from the admin panel.
 */
class JobQueue
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return int job ID (existing job when the unique key is already taken)
     */
    public function dispatch(string $type, array $payload, ?string $uniqueKey = null, int $maxAttempts = 5, ?DateTimeImmutable $availableAt = null): int
    {
        if ($uniqueKey !== null) {
            $existing = $this->db->fetchValue('SELECT `id` FROM `nx_jobs` WHERE `unique_key` = ?', [$uniqueKey]);
            if ($existing !== null) {
                return (int) $existing;
            }
        }

        $now = $this->clock->nowString();
        try {
            return $this->db->insert('nx_jobs', [
                'type' => $type,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'unique_key' => $uniqueKey,
                'status' => self::STATUS_PENDING,
                'attempts' => 0,
                'max_attempts' => max(1, $maxAttempts),
                'available_at' => ($availableAt ?? $this->clock->now())->format('Y-m-d H:i:s'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (PDOException $exception) {
            // A concurrent dispatch with the same unique key won the race.
            if ($uniqueKey !== null && ($exception->errorInfo[1] ?? null) === 1062) {
                return (int) $this->db->fetchValue('SELECT `id` FROM `nx_jobs` WHERE `unique_key` = ?', [$uniqueKey]);
            }

            throw $exception;
        }
    }

    /**
     * @return array{id: int, type: string, payload: array<string, mixed>, attempts: int, max_attempts: int}|null
     */
    public function reserve(string $workerId): ?array
    {
        return $this->db->transaction(function () use ($workerId): ?array {
            $row = $this->db->fetchOne(
                'SELECT `id`, `type`, `payload`, `attempts`, `max_attempts` FROM `nx_jobs`
                 WHERE `status` = ? AND `available_at` <= ?
                 ORDER BY `available_at`, `id` LIMIT 1 FOR UPDATE SKIP LOCKED',
                [self::STATUS_PENDING, $this->clock->nowString()],
            );
            if ($row === null) {
                return null;
            }

            $now = $this->clock->nowString();
            $this->db->run(
                'UPDATE `nx_jobs` SET `status` = ?, `attempts` = `attempts` + 1, `reserved_at` = ?, `reserved_by` = ?, `updated_at` = ? WHERE `id` = ?',
                [self::STATUS_RUNNING, $now, mb_substr($workerId, 0, 100), $now, (int) $row['id']],
            );

            $payload = json_decode((string) $row['payload'], true);

            return [
                'id' => (int) $row['id'],
                'type' => (string) $row['type'],
                'payload' => is_array($payload) ? $payload : [],
                'attempts' => (int) $row['attempts'] + 1,
                'max_attempts' => (int) $row['max_attempts'],
            ];
        });
    }

    public function complete(int $id, bool $scrubPayload = false): void
    {
        $now = $this->clock->nowString();
        $data = ['status' => self::STATUS_COMPLETED, 'finished_at' => $now, 'updated_at' => $now, 'last_error' => null];
        if ($scrubPayload) {
            // Keep only non-sensitive descriptive keys (e.g. the e-mail template name).
            $payload = json_decode((string) $this->db->fetchValue('SELECT `payload` FROM `nx_jobs` WHERE `id` = ?', [$id]), true);
            $kept = is_array($payload) && isset($payload['template']) && is_string($payload['template']) ? ['template' => $payload['template']] : [];
            $data['payload'] = json_encode($kept + ['scrubbed' => true], JSON_THROW_ON_ERROR);
        }
        $this->db->update('nx_jobs', $data, ['id' => $id]);
    }

    /**
     * Records a failure; schedules a retry with exponential backoff
     * (1, 4, 16, 64 … minutes, max 6 hours) unless attempts are exhausted.
     */
    public function fail(int $id, string $error, bool $permanent = false): void
    {
        $job = $this->db->fetchOne('SELECT `attempts`, `max_attempts` FROM `nx_jobs` WHERE `id` = ?', [$id]);
        if ($job === null) {
            return;
        }

        $attempts = (int) $job['attempts'];
        $exhausted = $permanent || $attempts >= (int) $job['max_attempts'];
        $now = $this->clock->now();
        $delayMinutes = min(360, 4 ** max(0, $attempts - 1));

        $this->db->update('nx_jobs', [
            'status' => $exhausted ? self::STATUS_FAILED : self::STATUS_PENDING,
            'last_error' => mb_substr($error, 0, 5000),
            'available_at' => $exhausted ? $now->format('Y-m-d H:i:s') : $now->modify(sprintf('+%d minutes', $delayMinutes))->format('Y-m-d H:i:s'),
            'finished_at' => $exhausted ? $now->format('Y-m-d H:i:s') : null,
            'reserved_at' => null,
            'reserved_by' => null,
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    /**
     * Puts a running job back without counting the attempt (used while a
     * remote task such as a Proxmox clone is still in progress).
     */
    public function release(int $id, int $delaySeconds): void
    {
        $now = $this->clock->now();
        $this->db->run(
            'UPDATE `nx_jobs` SET `status` = ?, `attempts` = GREATEST(`attempts` - 1, 0), `available_at` = ?, `reserved_at` = NULL, `reserved_by` = NULL, `updated_at` = ? WHERE `id` = ?',
            [self::STATUS_PENDING, $now->modify(sprintf('+%d seconds', max(1, $delaySeconds)))->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), $id],
        );
    }

    /** Manually re-queues a failed or cancelled job (admin action). */
    /**
     * Latest job whose unique key is $prefix or starts with "$prefix:".
     *
     * @return array{id: int, status: string}|null
     */
    public function latestByKeyPrefix(string $prefix): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT `id`, `status` FROM `nx_jobs` WHERE `unique_key` = ? OR `unique_key` LIKE ? ORDER BY `id` DESC LIMIT 1',
            [$prefix, addcslashes($prefix, '%_\\') . ':%'],
        );

        return $row === null ? null : ['id' => (int) $row['id'], 'status' => (string) $row['status']];
    }

    public function retry(int $id): bool
    {
        $now = $this->clock->nowString();

        return $this->db->run(
            'UPDATE `nx_jobs` SET `status` = ?, `attempts` = 0, `available_at` = ?, `finished_at` = NULL, `updated_at` = ?
             WHERE `id` = ? AND `status` IN (?, ?)',
            [self::STATUS_PENDING, $now, $now, $id, self::STATUS_FAILED, self::STATUS_CANCELLED],
        )->rowCount() === 1;
    }

    public function cancel(int $id): bool
    {
        $now = $this->clock->nowString();

        return $this->db->run(
            'UPDATE `nx_jobs` SET `status` = ?, `finished_at` = ?, `updated_at` = ? WHERE `id` = ? AND `status` IN (?, ?)',
            [self::STATUS_CANCELLED, $now, $now, $id, self::STATUS_PENDING, self::STATUS_FAILED],
        )->rowCount() === 1;
    }

    /** Returns jobs whose worker died (reserved for too long) to the queue. */
    public function releaseStale(int $minutes = 30): int
    {
        $now = $this->clock->now();

        return $this->db->run(
            'UPDATE `nx_jobs` SET `status` = ?, `reserved_at` = NULL, `reserved_by` = NULL, `updated_at` = ?
             WHERE `status` = ? AND `reserved_at` < ?',
            [self::STATUS_PENDING, $now->format('Y-m-d H:i:s'), self::STATUS_RUNNING, $now->modify(sprintf('-%d minutes', $minutes))->format('Y-m-d H:i:s')],
        )->rowCount();
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [self::STATUS_PENDING => 0, self::STATUS_RUNNING => 0, self::STATUS_COMPLETED => 0, self::STATUS_FAILED => 0, self::STATUS_CANCELLED => 0];
        foreach ($this->db->fetchAll('SELECT `status`, COUNT(*) AS c FROM `nx_jobs` GROUP BY `status`') as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM `nx_jobs` WHERE `id` = ?', [$id]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(?string $status, int $page, int $perPage = 50): array
    {
        $where = $status !== null && $status !== '' ? 'WHERE `status` = ?' : '';
        $params = $where !== '' ? [$status] : [];

        return [
            'items' => $this->db->fetchAll(
                'SELECT `id`, `type`, `unique_key`, `status`, `attempts`, `max_attempts`, `available_at`, `finished_at`, `last_error`, `created_at`
                 FROM `nx_jobs` ' . $where . ' ORDER BY `id` DESC LIMIT ? OFFSET ?',
                [...$params, $perPage, max(0, ($page - 1) * $perPage)],
            ),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_jobs` ' . $where, $params),
        ];
    }

    public function pruneCompleted(int $days): int
    {
        return $this->db->run(
            'DELETE FROM `nx_jobs` WHERE `status` = ? AND `finished_at` < ?',
            [self::STATUS_COMPLETED, $this->clock->now()->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s')],
        )->rowCount();
    }
}
