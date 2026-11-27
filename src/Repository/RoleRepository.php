<?php

declare(strict_types=1);

namespace Nebrix\Repository;

use Nebrix\Auth\Role;
use Nebrix\Authorization\Permission;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

class RoleRepository
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
            'SELECT r.*, (SELECT COUNT(*) FROM `nx_users` u WHERE u.`role_id` = r.`id`) AS user_count,
                    (SELECT COUNT(*) FROM `nx_role_permissions` p WHERE p.`role_id` = r.`id`) AS permission_count
             FROM `nx_roles` r ORDER BY r.`is_staff` DESC, r.`id`',
        );
    }

    /**
     * @return array<int, string> staff roles for select boxes
     */
    public function staffOptions(bool $includeSuperAdmin): array
    {
        $options = [];
        foreach ($this->db->fetchAll('SELECT `id`, `slug`, `name` FROM `nx_roles` WHERE `is_staff` = 1 ORDER BY `id`') as $row) {
            if (!$includeSuperAdmin && $row['slug'] === Role::SUPER_ADMIN) {
                continue;
            }
            $options[(int) $row['id']] = (string) $row['name'];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM `nx_roles` WHERE `id` = ?', [$id]);
    }

    public function idForSlug(string $slug): int
    {
        return (int) $this->db->fetchValue('SELECT `id` FROM `nx_roles` WHERE `slug` = ?', [$slug]);
    }

    /**
     * @return list<string>
     */
    public function permissions(int $roleId): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['permission'],
            $this->db->fetchAll('SELECT `permission` FROM `nx_role_permissions` WHERE `role_id` = ? ORDER BY `permission`', [$roleId]),
        );
    }

    /**
     * Replaces the permissions of a role; unknown permission names are ignored.
     *
     * @param list<string> $permissions
     */
    public function syncPermissions(int $roleId, array $permissions): void
    {
        $valid = array_values(array_intersect(array_unique($permissions), Permission::all()));

        $this->db->transaction(function () use ($roleId, $valid): void {
            $this->db->delete('nx_role_permissions', ['role_id' => $roleId]);
            foreach ($valid as $permission) {
                $this->db->insert('nx_role_permissions', ['role_id' => $roleId, 'permission' => $permission]);
            }
            $this->db->update('nx_roles', ['updated_at' => $this->clock->nowString()], ['id' => $roleId]);
        });
    }

    public function create(string $name, ?string $description): int
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($name)), '_');
        $base = $slug !== '' ? $slug : 'role';
        $slug = $base;
        for ($i = 2; $this->idForSlug($slug) !== 0; $i++) {
            $slug = $base . '_' . $i;
        }

        $now = $this->clock->nowString();

        return $this->db->insert('nx_roles', [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'is_system' => 0,
            'is_staff' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateDetails(int $id, string $name, ?string $description): void
    {
        $this->db->update('nx_roles', ['name' => $name, 'description' => $description, 'updated_at' => $this->clock->nowString()], ['id' => $id]);
    }

    public function nameExists(string $name, int $exceptId = 0): bool
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_roles` WHERE `name` = ? AND `id` <> ?', [$name, $exceptId]) > 0;
    }

    public function userCount(int $id): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_users` WHERE `role_id` = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('nx_roles', ['id' => $id]);
    }
}
