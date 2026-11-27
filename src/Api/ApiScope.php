<?php

declare(strict_types=1);

namespace Nebrix\Api;

use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Authorization\Permission;

/**
 * Catalog of API token scopes.
 *
 * Customer scopes act on the token owner's own data. Admin scopes are only
 * available to staff and additionally require the mapped permission at the
 * time of every request, so a token never outlives a role change.
 */
final class ApiScope
{
    public const ACCOUNT_READ = 'account:read';
    public const PRODUCTS_READ = 'products:read';
    public const ORDERS_READ = 'orders:read';
    public const ORDERS_CREATE = 'orders:create';
    public const SERVICES_READ = 'services:read';
    public const SERVICES_MANAGE = 'services:manage';
    public const SERVICES_CREDENTIALS = 'services:credentials';
    public const INVOICES_READ = 'invoices:read';
    public const INVOICES_PAY = 'invoices:pay';
    public const TICKETS_READ = 'tickets:read';
    public const TICKETS_WRITE = 'tickets:write';

    public const ADMIN_CUSTOMERS_READ = 'admin:customers:read';
    public const ADMIN_PRODUCTS_READ = 'admin:products:read';
    public const ADMIN_ORDERS_READ = 'admin:orders:read';
    public const ADMIN_INVOICES_READ = 'admin:invoices:read';
    public const ADMIN_SERVICES_READ = 'admin:services:read';
    public const ADMIN_SERVICES_WRITE = 'admin:services:write';
    public const ADMIN_TICKETS_READ = 'admin:tickets:read';
    public const ADMIN_TICKETS_WRITE = 'admin:tickets:write';

    /**
     * @return array<string, string> scope => description
     */
    public static function customer(): array
    {
        return [
            self::ACCOUNT_READ => 'Read your profile and credit balance',
            self::PRODUCTS_READ => 'Read the products you can order',
            self::ORDERS_READ => 'Read your orders',
            self::ORDERS_CREATE => 'Place orders (creates invoices)',
            self::SERVICES_READ => 'Read your services and their live status',
            self::SERVICES_MANAGE => 'Run service actions (power, reinstall, …)',
            self::SERVICES_CREDENTIALS => 'Read the access data of your services',
            self::INVOICES_READ => 'Read your invoices',
            self::INVOICES_PAY => 'Pay invoices (account credit or provider checkout)',
            self::TICKETS_READ => 'Read your support tickets',
            self::TICKETS_WRITE => 'Open, answer and close support tickets',
        ];
    }

    /**
     * Write scopes also need the view permission, like the admin routes
     * (which sit inside the view permission groups).
     *
     * @return array<string, array{0: string, 1: list<string>}> scope => [description, required permissions (all)]
     */
    public static function admin(): array
    {
        return [
            self::ADMIN_CUSTOMERS_READ => ['Admin: read customers', [Permission::CUSTOMERS_VIEW]],
            self::ADMIN_PRODUCTS_READ => ['Admin: read products', [Permission::PRODUCTS_VIEW]],
            self::ADMIN_ORDERS_READ => ['Admin: read orders', [Permission::ORDERS_VIEW]],
            self::ADMIN_INVOICES_READ => ['Admin: read invoices', [Permission::INVOICES_VIEW]],
            self::ADMIN_SERVICES_READ => ['Admin: read services', [Permission::SERVICES_VIEW]],
            self::ADMIN_SERVICES_WRITE => ['Admin: suspend, unsuspend, terminate and re-provision services', [Permission::SERVICES_VIEW, Permission::SERVICES_MANAGE]],
            self::ADMIN_TICKETS_READ => ['Admin: read support tickets', [Permission::TICKETS_VIEW]],
            self::ADMIN_TICKETS_WRITE => ['Admin: reply to support tickets', [Permission::TICKETS_VIEW, Permission::TICKETS_REPLY]],
        ];
    }

    /**
     * The permissions an admin scope requires (all of them); empty for customer scopes.
     *
     * @return list<string>
     */
    public static function permissionsFor(string $scope): array
    {
        return self::admin()[$scope][1] ?? [];
    }

    public static function isAdmin(string $scope): bool
    {
        return isset(self::admin()[$scope]);
    }

    public static function exists(string $scope): bool
    {
        return isset(self::customer()[$scope]) || isset(self::admin()[$scope]);
    }

    public static function describe(string $scope): string
    {
        return self::customer()[$scope] ?? self::admin()[$scope][0] ?? $scope;
    }

    /**
     * Scopes the user may put on a token right now.
     *
     * @return array<string, string> scope => description
     */
    public static function availableFor(User $user, Gate $gate): array
    {
        $scopes = self::customer();
        if ($gate->allows($user, Permission::ADMIN_ACCESS)) {
            foreach (self::admin() as $scope => [$description, $permissions]) {
                if (array_filter($permissions, static fn (string $p): bool => !$gate->allows($user, $p)) === []) {
                    $scopes[$scope] = $description;
                }
            }
        }

        return $scopes;
    }
}
