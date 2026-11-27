<?php

declare(strict_types=1);

namespace Nebrix\Payment;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use PDOException;

/**
 * Deduplicates provider webhooks by event ID. Events that were processed or
 * ignored are not processed again; failed events are retried when the
 * provider re-delivers them.
 */
class WebhookEventRepository
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return bool true when the event must be processed, false for duplicates
     */
    public function begin(string $gateway, string $eventId, string $eventType, string $payload): bool
    {
        try {
            $this->db->insert('nx_webhook_events', [
                'gateway' => $gateway,
                'event_id' => mb_substr($eventId, 0, 191),
                'event_type' => mb_substr($eventType, 0, 100),
                'status' => 'received',
                'payload' => $payload,
                'received_at' => $this->clock->nowString(),
            ]);

            return true;
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
        }

        $status = $this->db->fetchValue('SELECT `status` FROM `nx_webhook_events` WHERE `gateway` = ? AND `event_id` = ?', [$gateway, $eventId]);

        return !in_array($status, ['processed', 'ignored'], true);
    }

    public function finish(string $gateway, string $eventId, string $status, ?string $error = null): void
    {
        $this->db->run(
            'UPDATE `nx_webhook_events` SET `status` = ?, `error` = ?, `processed_at` = ? WHERE `gateway` = ? AND `event_id` = ?',
            [$status, $error !== null ? mb_substr($error, 0, 2000) : null, $this->clock->nowString(), $gateway, $eventId],
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(?string $gateway, ?string $status, int $page, int $perPage = 50): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($gateway !== null && $gateway !== '') {
            $where[] = '`gateway` = ?';
            $params[] = $gateway;
        }
        if ($status !== null && $status !== '') {
            $where[] = '`status` = ?';
            $params[] = $status;
        }
        $whereSql = implode(' AND ', $where);

        return [
            'items' => $this->db->fetchAll('SELECT `id`, `gateway`, `event_id`, `event_type`, `status`, `error`, `received_at`, `processed_at` FROM `nx_webhook_events` WHERE ' . $whereSql . ' ORDER BY `id` DESC LIMIT ? OFFSET ?', [...$params, $perPage, max(0, ($page - 1) * $perPage)]),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_webhook_events` WHERE ' . $whereSql, $params),
        ];
    }
}
