<?php

declare(strict_types=1);

namespace Nebrix\Support;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

class TicketRepository
{
    public const STATUSES = ['open', 'answered', 'customer_reply', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high'];

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    public function create(int $userId, string $subject, string $message, ?int $serviceId, string $priority): int
    {
        return $this->db->transaction(function () use ($userId, $subject, $message, $serviceId, $priority): int {
            $now = $this->clock->nowString();
            $id = $this->db->insert('nx_tickets', [
                'user_id' => $userId,
                'service_id' => $serviceId,
                'subject' => $subject,
                'status' => 'open',
                'priority' => $priority,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->db->insert('nx_ticket_messages', ['ticket_id' => $id, 'user_id' => $userId, 'is_staff' => 0, 'message' => $message, 'created_at' => $now]);

            return $id;
        });
    }

    public function addMessage(int $ticketId, int $userId, bool $isStaff, string $message): void
    {
        $this->db->transaction(function () use ($ticketId, $userId, $isStaff, $message): void {
            $now = $this->clock->nowString();
            $this->db->insert('nx_ticket_messages', ['ticket_id' => $ticketId, 'user_id' => $userId, 'is_staff' => $isStaff, 'message' => $message, 'created_at' => $now]);
            $this->db->update('nx_tickets', ['status' => $isStaff ? 'answered' : 'customer_reply', 'updated_at' => $now, 'closed_at' => null], ['id' => $ticketId]);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT t.*, u.`email`, u.`first_name`, u.`last_name`, s.`name` AS service_name, a.`email` AS assignee_email
             FROM `nx_tickets` t JOIN `nx_users` u ON u.`id` = t.`user_id`
             LEFT JOIN `nx_services` s ON s.`id` = t.`service_id` LEFT JOIN `nx_users` a ON a.`id` = t.`assigned_to`
             WHERE t.`id` = ?',
            [$id],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT t.*, s.`name` AS service_name FROM `nx_tickets` t LEFT JOIN `nx_services` s ON s.`id` = t.`service_id` WHERE t.`id` = ? AND t.`user_id` = ?',
            [$id, $userId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messages(int $ticketId): array
    {
        return $this->db->fetchAll(
            'SELECT m.*, u.`first_name`, u.`last_name`, u.`email` FROM `nx_ticket_messages` m LEFT JOIN `nx_users` u ON u.`id` = m.`user_id`
             WHERE m.`ticket_id` = ? ORDER BY m.`id`',
            [$ticketId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->db->fetchAll('SELECT * FROM `nx_tickets` WHERE `user_id` = ? ORDER BY `updated_at` DESC LIMIT 200', [$userId]);
    }

    public function setStatus(int $id, string $status): void
    {
        $now = $this->clock->nowString();
        $this->db->update('nx_tickets', ['status' => $status, 'updated_at' => $now, 'closed_at' => $status === 'closed' ? $now : null], ['id' => $id]);
    }

    public function assign(int $id, ?int $staffId): void
    {
        $this->db->update('nx_tickets', ['assigned_to' => $staffId, 'updated_at' => $this->clock->nowString()], ['id' => $id]);
    }

    public function setPriority(int $id, string $priority): void
    {
        $this->db->update('nx_tickets', ['priority' => $priority, 'updated_at' => $this->clock->nowString()], ['id' => $id]);
    }

    /**
     * @param array{status?: string, search?: string, assigned?: int, user_id?: int} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['status'] ?? '') === 'active') {
            $where[] = "t.`status` IN ('open', 'customer_reply')";
        } elseif (($filters['status'] ?? '') !== '') {
            $where[] = 't.`status` = ?';
            $params[] = $filters['status'];
        }
        if (isset($filters['assigned'])) {
            $where[] = 't.`assigned_to` = ?';
            $params[] = $filters['assigned'];
        }
        if (isset($filters['user_id'])) {
            $where[] = 't.`user_id` = ?';
            $params[] = $filters['user_id'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(t.`subject` LIKE ? OR u.`email` LIKE ?)';
            $like = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $whereSql = implode(' AND ', $where);
        $from = 'FROM `nx_tickets` t JOIN `nx_users` u ON u.`id` = t.`user_id` LEFT JOIN `nx_users` a ON a.`id` = t.`assigned_to`';

        return [
            'items' => $this->db->fetchAll(
                "SELECT t.*, u.`email`, a.`email` AS assignee_email $from WHERE $whereSql
                 ORDER BY FIELD(t.`status`, 'customer_reply', 'open', 'answered', 'closed'), FIELD(t.`priority`, 'high', 'normal', 'low'), t.`updated_at` DESC LIMIT ? OFFSET ?",
                [...$params, $perPage, max(0, ($page - 1) * $perPage)],
            ),
            'total' => (int) $this->db->fetchValue("SELECT COUNT(*) $from WHERE $whereSql", $params),
        ];
    }
}
