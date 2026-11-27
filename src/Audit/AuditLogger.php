<?php

declare(strict_types=1);

namespace Nebrix\Audit;

use Nebrix\Core\Clock;
use Nebrix\Core\LoggerFactory;
use Nebrix\Database\Connection;

/**
 * Append-only audit trail of sensitive actions (who, what, when, from where).
 * There is no update or delete API for audit entries.
 */
class AuditLogger
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $details
     */
    public function log(?int $actorId, string $action, ?string $targetType = null, int|string|null $targetId = null, array $details = [], ?string $ip = null): void
    {
        $this->db->insert('nx_audit_log', [
            'actor_id' => $actorId,
            'action' => mb_substr($action, 0, 100),
            'target_type' => $targetType,
            'target_id' => $targetId === null ? null : (string) $targetId,
            'ip' => $ip,
            'details' => $details === [] ? null : json_encode(LoggerFactory::redact($details), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $this->clock->nowString(),
        ]);
    }

    /**
     * @param array{action?: string, actor?: int, target_type?: string, target_id?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 50): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['action'] ?? '') !== '') {
            $where[] = 'a.`action` LIKE ?';
            $params[] = addcslashes((string) $filters['action'], '%_\\') . '%';
        }
        if (isset($filters['actor'])) {
            $where[] = 'a.`actor_id` = ?';
            $params[] = $filters['actor'];
        }
        if (($filters['target_type'] ?? '') !== '') {
            $where[] = 'a.`target_type` = ?';
            $params[] = $filters['target_type'];
        }
        if (($filters['target_id'] ?? '') !== '') {
            $where[] = 'a.`target_id` = ?';
            $params[] = $filters['target_id'];
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_audit_log` a WHERE ' . $whereSql, $params);
        $items = $this->db->fetchAll(
            'SELECT a.*, u.`email` AS actor_email FROM `nx_audit_log` a LEFT JOIN `nx_users` u ON u.`id` = a.`actor_id`
             WHERE ' . $whereSql . ' ORDER BY a.`id` DESC LIMIT ? OFFSET ?',
            [...$params, $perPage, max(0, ($page - 1) * $perPage)],
        );

        return ['items' => $items, 'total' => $total];
    }
}
