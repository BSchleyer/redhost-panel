<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration;

use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Authorization\Permission;
use Nebrix\Http\HttpException;
use Nebrix\Tests\DatabaseTestCase;

final class AuthorizationTest extends DatabaseTestCase
{
    private function gate(): Gate
    {
        return $this->app->container()->get(Gate::class);
    }

    public function testSuperAdminHasEveryPermission(): void
    {
        $user = $this->createUser(Role::SUPER_ADMIN);

        foreach (Permission::all() as $permission) {
            self::assertTrue($this->gate()->allows($user, $permission), $permission);
        }
    }

    public function testDefaultRolePermissions(): void
    {
        $admin = $this->createUser(Role::ADMIN);
        $support = $this->createUser(Role::SUPPORT);
        $finance = $this->createUser(Role::FINANCE);
        $customer = $this->createUser(Role::CUSTOMER);

        self::assertTrue($this->gate()->allows($admin, Permission::SETTINGS_SYSTEM));
        self::assertFalse($this->gate()->allows($admin, Permission::ROLES_MANAGE), 'Only super administrators manage roles by default.');

        self::assertTrue($this->gate()->allows($support, Permission::TICKETS_REPLY));
        self::assertFalse($this->gate()->allows($support, Permission::PAYMENTS_REFUND));
        self::assertFalse($this->gate()->allows($support, Permission::SETTINGS_GENERAL));

        self::assertTrue($this->gate()->allows($finance, Permission::PAYMENTS_REFUND));
        self::assertFalse($this->gate()->allows($finance, Permission::INTEGRATIONS_MANAGE));

        foreach (Permission::all() as $permission) {
            self::assertFalse($this->gate()->allows($customer, $permission), 'Customers hold no admin permission: ' . $permission);
        }
    }

    public function testInactiveStaffHasNoPermissions(): void
    {
        $admin = $this->createUser(Role::SUPER_ADMIN, ['status' => User::STATUS_SUSPENDED]);

        self::assertFalse($this->gate()->allows($admin, Permission::ADMIN_ACCESS));
        self::assertFalse($this->gate()->allows(null, Permission::ADMIN_ACCESS));
    }

    public function testPermissionsAreReadFromTheDatabase(): void
    {
        $support = $this->createUser(Role::SUPPORT);
        self::assertFalse($this->gate()->allows($support, Permission::AUDIT_VIEW));

        $this->db->insert('nx_role_permissions', ['role_id' => $support->roleId, 'permission' => Permission::AUDIT_VIEW]);
        $this->gate()->flush();

        self::assertTrue($this->gate()->allows($support, Permission::AUDIT_VIEW));
    }

    public function testAuthorizeThrowsForbidden(): void
    {
        $this->expectException(HttpException::class);
        $this->gate()->authorize($this->createUser(), Permission::ADMIN_ACCESS);
    }
}
