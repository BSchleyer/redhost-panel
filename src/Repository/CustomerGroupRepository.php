<?php

declare(strict_types=1);

namespace Nebrix\Repository;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

class CustomerGroupRepository
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT g.*, (SELECT COUNT(*) FROM `nx_users` u WHERE u.`customer_group_id` = g.`id`) AS member_count
             FROM `nx_customer_groups` g ORDER BY g.`name`',
        );
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->db->fetchAll('SELECT `id`, `name` FROM `nx_customer_groups` ORDER BY `name`') as $row) {
            $options[(int) $row['id']] = (string) $row['name'];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM `nx_customer_groups` WHERE `id` = ?', [$id]);
    }

    /**
     * @param array{name: string, description: ?string, discount_bp: int, can_order: bool, can_manage_services: bool} $data
     */
    public function save(?int $id, array $data): int
    {
        $now = $this->clock->nowString();
        $row = [
            'name' => $data['name'],
            'description' => $data['description'],
            'discount_bp' => $data['discount_bp'],
            'can_order' => $data['can_order'],
            'can_manage_services' => $data['can_manage_services'],
            'updated_at' => $now,
        ];

        if ($id === null) {
            return $this->db->insert('nx_customer_groups', $row + ['created_at' => $now]);
        }

        $this->db->update('nx_customer_groups', $row, ['id' => $id]);

        return $id;
    }

    public function nameExists(string $name, int $exceptId = 0): bool
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_customer_groups` WHERE `name` = ? AND `id` <> ?', [$name, $exceptId]) > 0;
    }

    public function delete(int $id): void
    {
        // Members fall back to "no group" (FK ON DELETE SET NULL); no customer data is removed.
        $this->db->delete('nx_customer_groups', ['id' => $id]);
    }
}
