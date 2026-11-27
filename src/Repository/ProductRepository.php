<?php

declare(strict_types=1);

namespace Nebrix\Repository;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

class ProductRepository
{
    private const WRITABLE = [
        'category', 'name', 'slug', 'description', 'features', 'price', 'setup_fee', 'currency', 'billing_cycle',
        'tax_rate_bp', 'stock', 'is_visible', 'is_active', 'auto_provision', 'integration_id', 'provisioning_config',
        'customer_group_id', 'sort_order',
    ];

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
        return $this->decode($this->db->fetchOne('SELECT * FROM `nx_products` WHERE `id` = ?', [$id]));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->decode($this->db->fetchOne('SELECT * FROM `nx_products` WHERE `slug` = ?', [$slug]));
    }

    /**
     * @param array{category?: string, search?: string, active?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['category'] ?? '') !== '') {
            $where[] = 'p.`category` = ?';
            $params[] = $filters['category'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = 'p.`name` LIKE ?';
            $params[] = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
        }
        if (($filters['active'] ?? '') !== '') {
            $where[] = 'p.`is_active` = ?';
            $params[] = $filters['active'] === '1' ? 1 : 0;
        }

        return array_map(
            fn (array $row): array => (array) $this->decode($row),
            $this->db->fetchAll(
                'SELECT p.*, i.`name` AS integration_name, i.`type` AS integration_type,
                    (SELECT COUNT(*) FROM `nx_services` s WHERE s.`product_id` = p.`id` AND s.`status` IN (\'active\', \'suspended\', \'provisioning\', \'pending\')) AS active_services
                 FROM `nx_products` p LEFT JOIN `nx_integrations` i ON i.`id` = p.`integration_id`
                 WHERE ' . implode(' AND ', $where) . ' ORDER BY p.`category`, p.`sort_order`, p.`name`',
                $params,
            ),
        );
    }

    /**
     * Products a customer may order.
     *
     * @return list<array<string, mixed>>
     */
    public function orderable(?int $customerGroupId): array
    {
        return array_map(
            fn (array $row): array => (array) $this->decode($row),
            $this->db->fetchAll(
                'SELECT * FROM `nx_products`
                 WHERE `is_active` = 1 AND `is_visible` = 1 AND (`stock` IS NULL OR `stock` > 0)
                   AND (`customer_group_id` IS NULL OR `customer_group_id` = ?)
                 ORDER BY `category`, `sort_order`, `price`',
                [$customerGroupId ?? 0],
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data = $this->filter($data);
        $now = $this->clock->nowString();

        return $this->db->insert('nx_products', $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $data = $this->filter($data);
        $data['updated_at'] = $this->clock->nowString();
        $this->db->update('nx_products', $data, ['id' => $id]);
    }

    public function slugExists(string $slug, int $exceptId = 0): bool
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_products` WHERE `slug` = ? AND `id` <> ?', [$slug, $exceptId]) > 0;
    }

    /**
     * Atomically reserves one unit of stock. Returns false when sold out.
     */
    public function reserveStock(int $id): bool
    {
        $affected = $this->db->run(
            'UPDATE `nx_products` SET `stock` = `stock` - 1 WHERE `id` = ? AND `stock` IS NOT NULL AND `stock` > 0',
            [$id],
        )->rowCount();
        if ($affected === 1) {
            return true;
        }

        return $this->db->fetchValue('SELECT `stock` FROM `nx_products` WHERE `id` = ?', [$id]) === null;
    }

    public function releaseStock(int $id): void
    {
        $this->db->run('UPDATE `nx_products` SET `stock` = `stock` + 1 WHERE `id` = ? AND `stock` IS NOT NULL', [$id]);
    }

    public function countActive(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_products` WHERE `is_active` = 1');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::WRITABLE));
        if (isset($data['provisioning_config']) && is_array($data['provisioning_config'])) {
            $data['provisioning_config'] = json_encode($data['provisioning_config'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $data;
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>|null
     */
    private function decode(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $config = json_decode((string) ($row['provisioning_config'] ?? ''), true);
        $row['provisioning_config'] = is_array($config) ? $config : [];

        return $row;
    }
}
