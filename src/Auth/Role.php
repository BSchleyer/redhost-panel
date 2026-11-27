<?php

declare(strict_types=1);

namespace Nebrix\Auth;

/**
 * System role slugs. Roles are stored in nx_roles; their permissions in
 * nx_role_permissions and are editable in the admin panel (except the super
 * administrator, which implicitly holds every permission).
 */
final class Role
{
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const SUPPORT = 'support';
    public const FINANCE = 'finance';
    public const CUSTOMER = 'customer';
}
