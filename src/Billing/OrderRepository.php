<?php

declare(strict_types=1);

namespace Nebrix\Billing;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

class OrderRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM `nx_orders` WHERE `id` = ?', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM `nx_orders` WHERE `id` = ? AND `user_id` = ?', [$id, $userId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $orderId): array
    {
        return array_map(static function (array $row): array {
            $options = json_decode((string) ($row['options'] ?? ''), true);
            $row['options'] = is_array($options) ? $options : [];

            return $row;
        }, $this->db->fetchAll(
            'SELECT oi.*, s.`id` AS service_id, s.`status` AS service_status FROM `nx_order_items` oi
             LEFT JOIN `nx_services` s ON s.`order_item_id` = oi.`id` WHERE oi.`order_id` = ? ORDER BY oi.`id`',
            [$orderId],
        ));
    }

    /**
     * @param array<string, mixed> $order
     * @param list<array<string, mixed>> $items
     * @return array{0: int, 1: list<int>} order ID and item IDs
     */
    public function create(array $order, array $items): array
    {
        $now = $this->clock->nowString();
        $id = $this->db->insert('nx_orders', $order + ['created_at' => $now, 'updated_at' => $now]);
        $itemIds = [];
        foreach ($items as $item) {
            if (isset($item['options']) && is_array($item['options'])) {
                $item['options'] = json_encode($item['options'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $itemIds[] = $this->db->insert('nx_order_items', $item + ['order_id' => $id, 'created_at' => $now]);
        }

        return [$id, $itemIds];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $data['updated_at'] = $this->clock->nowString();
        $this->db->update('nx_orders', $data, ['id' => $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->db->fetchAll('SELECT * FROM `nx_orders` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 100', [$userId]);
    }

    /**
     * @param array{status?: string, search?: string, user_id?: int} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'o.`status` = ?';
            $params[] = $filters['status'];
        }
        if (isset($filters['user_id'])) {
            $where[] = 'o.`user_id` = ?';
            $params[] = $filters['user_id'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(o.`number` LIKE ? OR u.`email` LIKE ?)';
            $like = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $whereSql = implode(' AND ', $where);

        return [
            'items' => $this->db->fetchAll(
                'SELECT o.*, u.`email`, (SELECT COUNT(*) FROM `nx_order_items` oi WHERE oi.`order_id` = o.`id`) AS item_count
                 FROM `nx_orders` o JOIN `nx_users` u ON u.`id` = o.`user_id` WHERE ' . $whereSql . ' ORDER BY o.`id` DESC LIMIT ? OFFSET ?',
                [...$params, $perPage, max(0, ($page - 1) * $perPage)],
            ),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_orders` o JOIN `nx_users` u ON u.`id` = o.`user_id` WHERE ' . $whereSql, $params),
        ];
    }
}
