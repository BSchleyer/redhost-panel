<?php

declare(strict_types=1);

namespace Nebrix\Authorization;

use Nebrix\Auth\User;
use Nebrix\Database\Connection;
use Nebrix\Http\HttpException;

/**
 * Server-side permission checks (RBAC).
 *
 * Permissions are code-defined (Permission::all()) and assigned to roles in
 * nx_role_permissions. The super administrator implicitly holds all of them.
 * Every admin route and every sensitive controller action calls the gate;
 * templates only use it to hide UI elements, never as the sole check.
 */
class Gate
{
    /** @var array<int, list<string>> */
    private array $cache = [];

    public function __construct(private readonly Connection $db)
    {
    }

    public function allows(?User $user, string $permission): bool
    {
        if ($user === null || !$user->isActive()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$user->isStaff()) {
            return false;
        }

        return in_array($permission, $this->permissionsForRole($user->roleId), true);
    }

    /**
     * @param list<string> $permissions
     */
    public function allowsAny(?User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->allows($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws HttpException
     */
    public function authorize(?User $user, string $permission): void
    {
        if (!$this->allows($user, $permission)) {
            throw HttpException::forbidden();
        }
    }

    /**
     * @return list<string>
     */
    public function permissionsForRole(int $roleId): array
    {
        if (!isset($this->cache[$roleId])) {
            $this->cache[$roleId] = array_map(
                static fn (array $row): string => (string) $row['permission'],
                $this->db->fetchAll('SELECT `permission` FROM `nx_role_permissions` WHERE `role_id` = ?', [$roleId]),
            );
        }

        return $this->cache[$roleId];
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
