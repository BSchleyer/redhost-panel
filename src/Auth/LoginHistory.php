<?php

declare(strict_types=1);

namespace Nebrix\Auth;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

class LoginHistory
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    public function record(?int $userId, string $identifier, string $ip, string $userAgent, bool $success, ?string $reason = null): void
    {
        $this->db->insert('nx_login_history', [
            'user_id' => $userId,
            'identifier' => mb_substr(mb_strtolower($identifier), 0, 254),
            'ip' => mb_substr($ip, 0, 45),
            'user_agent' => mb_substr($userAgent, 0, 255),
            'success' => $success,
            'reason' => $reason,
            'created_at' => $this->clock->nowString(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            'SELECT `ip`, `user_agent`, `success`, `reason`, `created_at` FROM `nx_login_history` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT ?',
            [$userId, $limit],
        );
    }

    /**
     * @param array{success?: string, staff_only?: bool, search?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 50): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['success'] ?? '') !== '') {
            $where[] = 'h.`success` = ?';
            $params[] = $filters['success'] === '1' ? 1 : 0;
        }
        if (($filters['staff_only'] ?? false) === true) {
            $where[] = 'r.`is_staff` = 1';
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(h.`identifier` LIKE ? OR h.`ip` LIKE ?)';
            $like = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $whereSql = implode(' AND ', $where);
        $from = 'FROM `nx_login_history` h LEFT JOIN `nx_users` u ON u.`id` = h.`user_id` LEFT JOIN `nx_roles` r ON r.`id` = u.`role_id`';

        return [
            'items' => $this->db->fetchAll(
                'SELECT h.*, u.`email` AS user_email, r.`name` AS role_name ' . $from . ' WHERE ' . $whereSql . ' ORDER BY h.`id` DESC LIMIT ? OFFSET ?',
                [...$params, $perPage, max(0, ($page - 1) * $perPage)],
            ),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) ' . $from . ' WHERE ' . $whereSql, $params),
        ];
    }

    public function prune(int $retentionDays): int
    {
        return $this->db->run(
            'DELETE FROM `nx_login_history` WHERE `created_at` < ?',
            [$this->clock->now()->modify(sprintf('-%d days', max(1, $retentionDays)))->format('Y-m-d H:i:s')],
        )->rowCount();
    }
}
