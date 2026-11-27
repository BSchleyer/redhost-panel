<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Queue\JobQueue;
use Nebrix\Repository\RoleRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\FeatureTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminPanelTest extends FeatureTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string, 2: int}>
     */
    public static function accessMatrix(): iterable
    {
        yield 'customer dashboard' => [Role::CUSTOMER, '/admin', 403];
        yield 'customer customers' => [Role::CUSTOMER, '/admin/customers', 403];
        yield 'support dashboard' => [Role::SUPPORT, '/admin', 200];
        yield 'support customers' => [Role::SUPPORT, '/admin/customers', 200];
        yield 'support settings' => [Role::SUPPORT, '/admin/settings/general', 403];
        yield 'support payments' => [Role::SUPPORT, '/admin/settings/payments', 403];
        yield 'support roles' => [Role::SUPPORT, '/admin/roles', 403];
        yield 'support staff' => [Role::SUPPORT, '/admin/staff', 403];
        yield 'finance payments settings' => [Role::FINANCE, '/admin/settings/payments', 403];
        yield 'finance products' => [Role::FINANCE, '/admin/products', 200];
        yield 'finance product create' => [Role::FINANCE, '/admin/products/create', 403];
        yield 'admin system' => [Role::ADMIN, '/admin/settings/system', 200];
        yield 'admin roles' => [Role::ADMIN, '/admin/roles', 403];
        yield 'super admin roles' => [Role::SUPER_ADMIN, '/admin/roles', 200];
    }

    #[DataProvider('accessMatrix')]
    public function testRoleBasedAccess(string $role, string $path, int $expected): void
    {
        $this->actingAs($this->createUser($role));

        self::assertSame($expected, $this->get($path)->status());
    }

    public function testGuestsCannotReachTheAdminArea(): void
    {
        $this->assertRedirect($this->get('/admin'), '/login');
        self::assertSame(401, $this->post('/admin/settings/general', ['group' => 'general'])->status());
    }

    public function testEveryAdminPageRendersForSuperAdmin(): void
    {
        $admin = $this->createUser(Role::SUPER_ADMIN);
        $customer = $this->createUser();
        $this->actingAs($admin);
        $roleId = $this->app->container()->get(RoleRepository::class)->idForSlug(Role::SUPPORT);

        $pages = [
            '/admin', '/admin/settings/general', '/admin/settings/branding', '/admin/settings/email', '/admin/settings/payments',
            '/admin/settings/system', '/admin/mail-templates', '/admin/mail-templates/password_reset', '/admin/customers',
            '/admin/customers/create', '/admin/customers/' . $customer->id, '/admin/customers/' . $customer->id . '/edit',
            '/admin/customer-groups', '/admin/products', '/admin/products/create', '/admin/products/create?category=vps',
            '/admin/products/create?category=minecraft', '/admin/products/create?category=nextcloud', '/admin/staff',
            '/admin/staff/create', '/admin/staff/' . $admin->id . '/edit', '/admin/roles', '/admin/roles/' . $roleId,
            '/admin/audit-log', '/admin/login-history', '/admin/jobs', '/admin/jobs?status=failed',
            '/admin/products/create?category=dedicated', '/admin/products/create?category=lxc', '/admin/ip-pools', '/admin/ip-pools/create',
            '/admin/ip-pools/' . $this->app->container()->get(\Nebrix\Network\IpPoolService::class)->createPool('Render', '192.0.2.0/29', '192.0.2.1', null, null),
            '/admin/integrations/create?type=hetzner_robot',
        ];

        foreach ($pages as $page) {
            $response = $this->get($page);
            self::assertSame(200, $response->status(), $page . ' did not render: ' . strip_tags(substr($response->body(), 0, 500)));
        }

        self::assertSame(404, $this->get('/admin/settings/unknown')->status());
        self::assertSame(404, $this->get('/admin/customers/' . $admin->id)->status(), 'Staff accounts are not shown as customers.');
    }

    public function testSettingsAreSavedAndSecretsNeverRenderedBack(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));

        $this->assertRedirect($this->post('/admin/settings/email', [
            'group' => 'mail', 'mail__host' => 'smtp.example.com', 'mail__port' => '587', 'mail__encryption' => 'tls',
            'mail__username' => 'mailer', 'mail__password' => 'super-secret-smtp', 'mail__from_address' => 'noreply@example.com',
        ]), '/admin/settings/email');

        $settings = $this->app->container()->get(SettingsRepository::class);
        $settings->flush();
        self::assertSame('super-secret-smtp', $settings->get('mail.password'));

        $page = $this->get('/admin/settings/email')->body();
        self::assertStringNotContainsString('super-secret-smtp', $page);
        self::assertStringContainsString('stored – leave empty to keep', $page);

        $audit = (string) $this->db->fetchValue("SELECT details FROM nx_audit_log WHERE action = 'settings.updated' ORDER BY id DESC LIMIT 1");
        self::assertStringContainsString('mail.password', $audit);
        self::assertStringNotContainsString('super-secret-smtp', $audit);
    }

    public function testSettingsGroupMustBelongToThePage(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));

        // Trying to change payment settings through the general page is rejected.
        self::assertSame(400, $this->post('/admin/settings/general', ['group' => 'payments_stripe', 'payments__stripe__enabled' => '1'])->status());
    }

    public function testInvalidSettingsAreRejected(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));
        $this->post('/admin/settings/branding', ['group' => 'branding', 'branding__primary_color' => 'javascript:alert(1)', 'branding__secondary_color' => '#000000', 'branding__accent_color' => '#000000', 'branding__email_header_color' => '#000000', 'branding__default_theme' => 'dark']);

        self::assertSame('#6d28d9', $this->app->container()->get(SettingsRepository::class)->string('branding.primary_color'));
    }

    public function testCorrectionDocumentsNeedTheirOwnNumberPrefix(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));
        $form = ['group' => 'billing'];
        foreach (require dirname(__DIR__, 2) . '/config/settings.php' as $key => $definition) {
            if ($definition['group'] === 'billing') {
                $value = $definition['default'];
                $form[str_replace('.', '__', $key)] = is_bool($value) ? ($value ? '1' : '') : (is_array($value) ? implode(',', $value) : (string) $value);
            }
        }
        $settings = $this->app->container()->get(SettingsRepository::class);

        $this->post('/admin/settings/general', ['billing__credit_note_prefix' => 'ST-'] + $form);
        self::assertSame('ST-', $settings->string('billing.credit_note_prefix'), 'A distinct prefix is saved.');
        $this->post('/admin/settings/general', ['billing__credit_note_prefix' => 'nx-'] + $form);
        self::assertSame('ST-', $settings->string('billing.credit_note_prefix'), 'The invoice prefix is refused.');
        $this->post('/admin/settings/general', ['billing__credit_note_prefix' => ''] + $form);
        self::assertSame('ST-', $settings->string('billing.credit_note_prefix'), 'An empty prefix is refused.');
    }

    public function testProductLifecycle(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));
        $integrationId = $this->db->insert('nx_integrations', ['type' => 'proxmox', 'name' => 'PVE', 'is_enabled' => 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);

        $base = [
            'category' => 'lxc', 'name' => 'LXC Small', 'slug' => 'lxc-small', 'price' => '4,99', 'setup_fee' => '0', 'currency' => 'EUR',
            'billing_cycle' => 'monthly', 'tax_rate' => '19', 'is_active' => '1', 'is_visible' => '1', 'auto_provision' => '1',
            'integration_id' => (string) $integrationId, 'config_ostemplate' => 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst',
            'config_storage' => 'local-lvm', 'config_cores' => '1', 'config_memory_mb' => '1024', 'config_swap_mb' => '512',
            'config_disk_gb' => '10', 'config_bridge' => 'vmbr0', 'config_rate_mbps' => '0', 'config_unprivileged' => '1', 'config_vmid_start' => '20000',
        ];

        // Invalid category config (shell metacharacters in the template) is rejected.
        $this->post('/admin/products', ['config_ostemplate' => 'local:vztmpl/x;rm -rf /'] + $base);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_products'));

        $response = $this->post('/admin/products', $base);
        $product = $this->db->fetchOne("SELECT * FROM nx_products WHERE slug = 'lxc-small'");
        self::assertNotNull($product);
        $this->assertRedirect($response, '/admin/products/' . $product['id'] . '/edit');
        self::assertSame(499, (int) $product['price']);
        self::assertSame(1900, (int) $product['tax_rate_bp']);
        $config = json_decode((string) $product['provisioning_config'], true);
        self::assertSame(1024, $config['memory_mb']);
        self::assertTrue($config['unprivileged']);

        // Duplicate slug is rejected.
        $this->post('/admin/products', ['name' => 'Other'] + $base);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_products'));

        // An integration of the wrong type cannot be attached.
        $ptero = $this->db->insert('nx_integrations', ['type' => 'pterodactyl', 'name' => 'Ptero', 'is_enabled' => 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
        $this->post('/admin/products/' . $product['id'], ['integration_id' => (string) $ptero, 'price' => '9.99'] + $base);
        self::assertSame(499, (int) $this->db->fetchValue('SELECT price FROM nx_products WHERE id = ?', [$product['id']]));

        $this->post('/admin/products/' . $product['id'], ['price' => '5.49'] + $base);
        self::assertSame(549, (int) $this->db->fetchValue('SELECT price FROM nx_products WHERE id = ?', [$product['id']]));
    }

    public function testCustomerManagement(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));

        $this->post('/admin/customers', ['email' => 'new.customer@example.test', 'first_name' => 'New', 'last_name' => 'Customer', 'language' => 'en']);
        $customerId = (int) $this->db->fetchValue("SELECT id FROM nx_users WHERE email = 'new.customer@example.test'");
        self::assertGreaterThan(0, $customerId);
        self::assertSame('new.customer@example.test', $this->lastMail('password_reset')['to'] ?? null);

        $this->post('/admin/customers/' . $customerId . '/credit', ['amount' => '25.50', 'reason' => 'Migration from legacy balance']);
        self::assertSame(2550, (int) $this->db->fetchValue('SELECT credit_balance FROM nx_users WHERE id = ?', [$customerId]));
        self::assertSame(2550, (int) $this->db->fetchValue('SELECT balance_after FROM nx_credit_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$customerId]));

        $this->post('/admin/customers/' . $customerId . '/credit', ['amount' => '-100', 'reason' => 'Too much']);
        self::assertSame(2550, (int) $this->db->fetchValue('SELECT credit_balance FROM nx_users WHERE id = ?', [$customerId]), 'Balance must never become negative.');

        $this->post('/admin/customers/' . $customerId . '/status', ['status' => 'suspended', 'reason' => 'Abuse']);
        self::assertSame(User::STATUS_SUSPENDED, $this->db->fetchValue('SELECT status FROM nx_users WHERE id = ?', [$customerId]));

        $this->post('/admin/customers/' . $customerId . '/close', ['confirm_email' => 'wrong@example.test']);
        self::assertSame(User::STATUS_SUSPENDED, $this->db->fetchValue('SELECT status FROM nx_users WHERE id = ?', [$customerId]));

        $this->post('/admin/customers/' . $customerId . '/close', ['confirm_email' => 'new.customer@example.test']);
        $row = $this->db->fetchOne('SELECT * FROM nx_users WHERE id = ?', [$customerId]);
        self::assertNotNull($row);
        self::assertSame(User::STATUS_CLOSED, $row['status']);
        self::assertNull($row['first_name']);
        self::assertStringEndsWith('@invalid.invalid', $row['email']);
        self::assertGreaterThan(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_audit_log WHERE action = 'account.closed' AND target_id = ?", [(string) $customerId]));
    }

    public function testSupportCannotAdjustCreditOrCloseAccounts(): void
    {
        $customer = $this->createUser();
        $this->actingAs($this->createUser(Role::SUPPORT));

        self::assertSame(403, $this->post('/admin/customers/' . $customer->id . '/credit', ['amount' => '100', 'reason' => 'x'])->status());
        self::assertSame(403, $this->post('/admin/customers/' . $customer->id . '/close', ['confirm_email' => $customer->email])->status());
        self::assertSame(403, $this->post('/admin/customers/' . $customer->id . '/status', ['status' => 'suspended'])->status());
    }

    public function testStaffPrivilegeEscalationGuards(): void
    {
        $admin = $this->createUser(Role::ADMIN);
        $superAdmin = $this->createUser(Role::SUPER_ADMIN);
        $roles = $this->app->container()->get(RoleRepository::class);
        $this->actingAs($admin);

        // Administrators cannot create super administrators …
        $this->post('/admin/staff', ['email' => 'evil@example.test', 'first_name' => 'E', 'last_name' => 'V', 'role_id' => (string) $roles->idForSlug(Role::SUPER_ADMIN)]);
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_users WHERE email = 'evil@example.test'"));
        // … cannot edit them …
        self::assertSame(403, $this->get('/admin/staff/' . $superAdmin->id . '/edit')->status());
        // … and cannot change their own role.
        $this->post('/admin/staff/' . $admin->id, ['first_name' => 'A', 'last_name' => 'B', 'role_id' => (string) $roles->idForSlug(Role::FINANCE), 'status' => 'active']);
        self::assertSame($admin->roleId, (int) $this->db->fetchValue('SELECT role_id FROM nx_users WHERE id = ?', [$admin->id]));

        // A customer role cannot be assigned to staff via the staff form.
        $this->post('/admin/staff', ['email' => 'support@example.test', 'first_name' => 'S', 'last_name' => 'P', 'role_id' => (string) $roles->idForSlug(Role::CUSTOMER)]);
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_users WHERE email = 'support@example.test'"));

        $this->post('/admin/staff', ['email' => 'support@example.test', 'first_name' => 'S', 'last_name' => 'P', 'role_id' => (string) $roles->idForSlug(Role::SUPPORT)]);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_users WHERE email = 'support@example.test'"));
    }

    public function testStaffManagersCannotGrantMorePermissionsThanTheyHold(): void
    {
        $roles = $this->app->container()->get(RoleRepository::class);
        // A custom "support lead" role that may manage staff but not much else.
        $leadRole = $roles->create('Support lead', null);
        $roles->syncPermissions($leadRole, ['admin.access', 'staff.view', 'staff.manage', 'tickets.view', 'tickets.reply']);
        $this->app->container()->get(\Nebrix\Authorization\Gate::class)->flush();
        $lead = $this->createUser(Role::SUPPORT);
        $this->db->update('nx_users', ['role_id' => $leadRole], ['id' => $lead->id]);
        $lead = $this->app->container()->get(\Nebrix\Repository\UserRepository::class)->find($lead->id) ?? $lead;
        $admin = $this->createUser(Role::ADMIN);
        $this->actingAs($lead);

        self::assertSame(200, $this->get('/admin/staff/create')->status(), 'The lead may manage staff in general.');

        // Cannot create an account with the administrator role …
        $this->post('/admin/staff', ['email' => 'mine@example.test', 'first_name' => 'M', 'last_name' => 'E', 'role_id' => (string) $roles->idForSlug(Role::ADMIN)]);
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_users WHERE email = 'mine@example.test'"));
        // … the form does not offer it …
        self::assertStringNotContainsString('>Administrator<', $this->get('/admin/staff/create')->body());
        // … and more powerful staff members cannot be edited or suspended.
        self::assertSame(403, $this->get('/admin/staff/' . $admin->id . '/edit')->status());
        self::assertSame(403, $this->post('/admin/staff/' . $admin->id, ['first_name' => 'A', 'last_name' => 'D', 'role_id' => (string) $admin->roleId, 'status' => 'suspended'])->status());
        self::assertSame('active', $this->db->fetchValue('SELECT status FROM nx_users WHERE id = ?', [$admin->id]));

        // A role with a subset of the lead's permissions can be assigned.
        $helperRole = $roles->create('Ticket helper', null);
        $roles->syncPermissions($helperRole, ['admin.access', 'tickets.view']);
        $this->app->container()->get(\Nebrix\Authorization\Gate::class)->flush();
        $this->post('/admin/staff', ['email' => 'helper@example.test', 'first_name' => 'H', 'last_name' => 'E', 'role_id' => (string) $helperRole]);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_users WHERE email = 'helper@example.test'"));
    }

    public function testSuperAdminsCanDemoteOthersButNotThemselves(): void
    {
        $first = $this->createUser(Role::SUPER_ADMIN);
        $second = $this->createUser(Role::SUPER_ADMIN);
        $roles = $this->app->container()->get(RoleRepository::class);
        $this->actingAs($first);

        $this->post('/admin/staff/' . $second->id, ['first_name' => 'S', 'last_name' => 'A', 'role_id' => (string) $roles->idForSlug(Role::ADMIN), 'status' => 'active']);
        self::assertSame($roles->idForSlug(Role::ADMIN), (int) $this->db->fetchValue('SELECT role_id FROM nx_users WHERE id = ?', [$second->id]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_sessions WHERE user_id = ?', [$second->id]), 'A role change signs the user out.');

        $this->post('/admin/staff/' . $first->id, ['first_name' => 'F', 'last_name' => 'A', 'role_id' => (string) $roles->idForSlug(Role::ADMIN), 'status' => 'suspended']);
        self::assertSame($first->roleId, (int) $this->db->fetchValue('SELECT role_id FROM nx_users WHERE id = ?', [$first->id]));
        self::assertSame('active', $this->db->fetchValue('SELECT status FROM nx_users WHERE id = ?', [$first->id]));
    }

    public function testRolePermissionsCanBeEditedButSuperAdminIsLocked(): void
    {
        $this->actingAs($this->createUser(Role::SUPER_ADMIN));
        $roles = $this->app->container()->get(RoleRepository::class);
        $supportId = $roles->idForSlug(Role::SUPPORT);

        $this->post('/admin/roles/' . $supportId, ['name' => 'Support', 'permissions' => ['admin.access', 'tickets.view', 'not.a.permission']]);
        self::assertSame(['admin.access', 'tickets.view'], $roles->permissions($supportId));

        self::assertSame(403, $this->post('/admin/roles/' . $roles->idForSlug(Role::SUPER_ADMIN), ['name' => 'X', 'permissions' => []])->status());
        self::assertSame(403, $this->post('/admin/roles/' . $supportId . '/delete')->status(), 'System roles cannot be deleted.');

        $this->post('/admin/roles', ['name' => 'Night shift']);
        $customRole = (int) $this->db->fetchValue("SELECT id FROM nx_roles WHERE name = 'Night shift'");
        $this->assertRedirect($this->post('/admin/roles/' . $customRole . '/delete'), '/admin/roles');
        self::assertNull($roles->find($customRole));
    }

    public function testFailedJobsCanBeRetried(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));
        $queue = $this->app->container()->get(JobQueue::class);
        $id = $queue->dispatch('test.job', [], null, 1);
        $queue->reserve('test');
        $queue->fail($id, 'boom');
        self::assertSame('failed', $queue->find($id)['status'] ?? null);

        $this->post('/admin/jobs/' . $id . '/retry');

        self::assertSame('pending', $queue->find($id)['status'] ?? null);
    }

    public function testMailTemplatesAreEditableAndPreviewIsSandboxed(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));

        $this->post('/admin/mail-templates/password_reset', ['subject' => 'Reset for {{ name }}', 'html' => '<p>Hi {{ name }}, <a href="{{ reset_url }}">reset</a></p>']);
        $preview = $this->get('/admin/mail-templates/password_reset/preview');

        self::assertStringContainsString('Hi [name]', $preview->body());
        self::assertStringContainsString('sandbox', (string) $preview->header('Content-Security-Policy'));

        $this->post('/admin/mail-templates/password_reset/reset');
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_mail_templates WHERE `key` = 'password_reset'"));
    }

    public function testAdminActionsAreAudited(): void
    {
        $admin = $this->createUser(Role::ADMIN);
        $this->actingAs($admin);
        $this->post('/admin/customer-groups', ['name' => 'Resellers', 'discount_percent' => '10', 'can_order' => '1']);

        $entry = $this->db->fetchOne("SELECT * FROM nx_audit_log WHERE action = 'customer_group.created'");
        self::assertNotNull($entry);
        self::assertSame($admin->id, (int) $entry['actor_id']);
        self::assertSame($this->clientIp, $entry['ip']);
        self::assertSame(1000, (int) $this->db->fetchValue("SELECT discount_bp FROM nx_customer_groups WHERE name = 'Resellers'"));
    }
}
