<?php

declare(strict_types=1);

namespace Nebrix\Authorization;

/**
 * Catalog of all permissions. Adding a permission here makes it assignable
 * to roles in Admin → Staff → Roles.
 */
final class Permission
{
    public const ADMIN_ACCESS = 'admin.access';
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const CUSTOMERS_VIEW = 'customers.view';
    public const CUSTOMERS_MANAGE = 'customers.manage';
    public const CUSTOMERS_DELETE = 'customers.delete';
    public const CUSTOMERS_CREDIT = 'customers.credit';
    public const CUSTOMER_GROUPS_MANAGE = 'customer_groups.manage';

    public const PRODUCTS_VIEW = 'products.view';
    public const PRODUCTS_MANAGE = 'products.manage';

    public const ORDERS_VIEW = 'orders.view';
    public const ORDERS_MANAGE = 'orders.manage';
    public const INVOICES_VIEW = 'invoices.view';
    public const INVOICES_MANAGE = 'invoices.manage';
    public const TRANSACTIONS_VIEW = 'transactions.view';
    public const PAYMENTS_REFUND = 'payments.refund';
    public const PAYMENTS_SETTINGS = 'payments.settings';

    public const SERVICES_VIEW = 'services.view';
    public const SERVICES_MANAGE = 'services.manage';

    public const INTEGRATIONS_VIEW = 'integrations.view';
    public const INTEGRATIONS_MANAGE = 'integrations.manage';

    public const TICKETS_VIEW = 'tickets.view';
    public const TICKETS_REPLY = 'tickets.reply';
    public const TICKETS_MANAGE = 'tickets.manage';

    public const SETTINGS_GENERAL = 'settings.general';
    public const SETTINGS_BRANDING = 'settings.branding';
    public const SETTINGS_EMAIL = 'settings.email';
    public const SETTINGS_SYSTEM = 'settings.system';
    public const MAIL_TEMPLATES_MANAGE = 'mail_templates.manage';

    public const STAFF_VIEW = 'staff.view';
    public const STAFF_MANAGE = 'staff.manage';
    public const ROLES_MANAGE = 'roles.manage';
    public const AUDIT_VIEW = 'audit.view';
    public const API_TOKENS_MANAGE = 'api_tokens.manage';

    public const JOBS_VIEW = 'jobs.view';
    public const JOBS_MANAGE = 'jobs.manage';

    /**
     * @return array<string, array<string, string>> group => [permission => description]
     */
    public static function grouped(): array
    {
        return [
            'General' => [
                self::ADMIN_ACCESS => 'Access the administration area',
                self::DASHBOARD_VIEW => 'View the admin dashboard',
            ],
            'Customers' => [
                self::CUSTOMERS_VIEW => 'View customers',
                self::CUSTOMERS_MANAGE => 'Edit, suspend and reactivate customers',
                self::CUSTOMERS_DELETE => 'Close and anonymize customer accounts',
                self::CUSTOMERS_CREDIT => 'Adjust customer credit balances',
                self::CUSTOMER_GROUPS_MANAGE => 'Manage customer groups',
            ],
            'Catalog' => [
                self::PRODUCTS_VIEW => 'View products',
                self::PRODUCTS_MANAGE => 'Create and edit products',
            ],
            'Billing' => [
                self::ORDERS_VIEW => 'View orders',
                self::ORDERS_MANAGE => 'Cancel orders and mark them paid',
                self::INVOICES_VIEW => 'View invoices',
                self::INVOICES_MANAGE => 'Cancel invoices and record manual payments',
                self::TRANSACTIONS_VIEW => 'View transactions, payment logs and webhook events',
                self::PAYMENTS_REFUND => 'Issue refunds',
                self::PAYMENTS_SETTINGS => 'Configure payment providers',
            ],
            'Services' => [
                self::SERVICES_VIEW => 'View customer services',
                self::SERVICES_MANAGE => 'Suspend, unsuspend, terminate and re-provision services',
            ],
            'Integrations' => [
                self::INTEGRATIONS_VIEW => 'View integrations, health and logs',
                self::INTEGRATIONS_MANAGE => 'Add, edit and test integrations (credentials)',
            ],
            'Support' => [
                self::TICKETS_VIEW => 'View support tickets',
                self::TICKETS_REPLY => 'Reply to support tickets',
                self::TICKETS_MANAGE => 'Close, reopen and reassign tickets',
            ],
            'Settings' => [
                self::SETTINGS_GENERAL => 'Edit general, registration and billing settings',
                self::SETTINGS_BRANDING => 'Edit branding and design',
                self::SETTINGS_EMAIL => 'Edit SMTP settings and send test e-mails',
                self::SETTINGS_SYSTEM => 'Edit system settings (maintenance, logging)',
                self::MAIL_TEMPLATES_MANAGE => 'Edit e-mail templates',
            ],
            'Staff & Security' => [
                self::STAFF_VIEW => 'View staff accounts and login history',
                self::STAFF_MANAGE => 'Create and edit staff accounts',
                self::ROLES_MANAGE => 'Edit roles and permissions',
                self::AUDIT_VIEW => 'View the audit log',
                self::API_TOKENS_MANAGE => 'View and revoke the API tokens of all users',
            ],
            'System' => [
                self::JOBS_VIEW => 'View background jobs and scheduled tasks',
                self::JOBS_MANAGE => 'Retry and cancel background jobs',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $all = [];
        foreach (self::grouped() as $permissions) {
            $all = array_merge($all, array_keys($permissions));
        }

        return $all;
    }

    /**
     * Default permission sets used when the roles are first created.
     *
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        $all = self::all();

        return [
            'admin' => array_values(array_diff($all, [self::ROLES_MANAGE])),
            'support' => [
                self::ADMIN_ACCESS, self::DASHBOARD_VIEW, self::CUSTOMERS_VIEW, self::SERVICES_VIEW,
                self::SERVICES_MANAGE, self::ORDERS_VIEW, self::INVOICES_VIEW, self::PRODUCTS_VIEW,
                self::INTEGRATIONS_VIEW, self::TICKETS_VIEW, self::TICKETS_REPLY, self::TICKETS_MANAGE,
            ],
            'finance' => [
                self::ADMIN_ACCESS, self::DASHBOARD_VIEW, self::CUSTOMERS_VIEW, self::CUSTOMERS_CREDIT,
                self::ORDERS_VIEW, self::ORDERS_MANAGE, self::INVOICES_VIEW, self::INVOICES_MANAGE,
                self::TRANSACTIONS_VIEW, self::PAYMENTS_REFUND, self::PRODUCTS_VIEW, self::SERVICES_VIEW,
            ],
            'customer' => [],
        ];
    }
}
