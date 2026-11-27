<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use GuzzleHttp\ClientInterface;
use Nebrix\Auth\Role;
use Nebrix\Tests\FeatureTestCase;
use Nebrix\Tests\Support\FakeHttp;

final class AdminIntegrationsTest extends FeatureTestCase
{
    private FakeHttp $http;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = (new FakeHttp())
            ->on('GET', '/status.php', ['installed' => true, 'maintenance' => false, 'versionstring' => '30.0.0'])
            ->on('GET', '/ocs/v2.php/cloud/users', ['ocs' => ['meta' => ['statuscode' => 200, 'message' => 'OK'], 'data' => ['users' => []]]]);
        $this->app->container()->set(ClientInterface::class, fn (): ClientInterface => $this->http->client());
    }

    /**
     * @return array<string, string>
     */
    private function nextcloudForm(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'nextcloud', 'name' => 'Cloud FRA', 'is_enabled' => '1',
            'config_base_url' => 'https://203.0.113.30', 'config_admin_user' => 'admin', 'config_user_prefix' => 'nx', 'config_verify_tls' => '1',
            'secret_admin_password' => 'very-secret-app-password',
        ];
    }

    public function testCreateTestAndSecretsAreWriteOnly(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));

        $response = $this->post('/admin/integrations', $this->nextcloudForm());
        $id = (int) $this->db->fetchValue("SELECT id FROM nx_integrations WHERE name = 'Cloud FRA'");
        $this->assertRedirect($response, '/admin/integrations/' . $id);
        self::assertSame('healthy', $this->db->fetchValue('SELECT health_status FROM nx_integrations WHERE id = ?', [$id]));

        foreach (['/admin/integrations', '/admin/integrations/' . $id, '/admin/integrations/' . $id . '/edit'] as $page) {
            $body = $this->get($page)->body();
            self::assertStringNotContainsString('very-secret-app-password', $body, $page);
        }

        // Saving without a new secret keeps the stored one.
        $this->post('/admin/integrations/' . $id, $this->nextcloudForm(['secret_admin_password' => '', 'name' => 'Cloud FRA 1']));
        $this->post('/admin/integrations/' . $id . '/test');
        self::assertSame('Basic ' . base64_encode('admin:very-secret-app-password'), $this->http->last()->getHeaderLine('Authorization'));

        $audit = (string) $this->db->fetchValue("SELECT details FROM nx_audit_log WHERE action = 'integration.updated'");
        self::assertStringNotContainsString('very-secret-app-password', $audit);
    }

    public function testSsrfTargetsAreRejectedWhenSaving(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));

        foreach (['https://169.254.169.254', 'https://127.0.0.1', 'http://203.0.113.30', 'https://user:pw@203.0.113.30'] as $url) {
            $this->post('/admin/integrations', $this->nextcloudForm(['config_base_url' => $url, 'name' => 'x' . md5($url)]));
        }

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_integrations'));
        self::assertSame([], $this->http->history);
    }

    public function testRequiredSecretsOnCreate(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));
        $this->post('/admin/integrations', $this->nextcloudForm(['secret_admin_password' => '']));

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_integrations'));
    }

    public function testSupportCanViewButNotManage(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));
        $this->post('/admin/integrations', $this->nextcloudForm());
        $id = (int) $this->db->fetchValue('SELECT id FROM nx_integrations');

        $this->actingAs($this->createUser(Role::SUPPORT));
        self::assertSame(200, $this->get('/admin/integrations/' . $id)->status());
        self::assertSame(403, $this->get('/admin/integrations/' . $id . '/edit')->status());
        self::assertSame(403, $this->post('/admin/integrations/' . $id, $this->nextcloudForm(['config_base_url' => 'https://203.0.113.99']))->status());
        self::assertSame(403, $this->post('/admin/integrations/' . $id . '/delete')->status());

        $this->actingAs($this->createUser(Role::FINANCE));
        self::assertSame(403, $this->get('/admin/integrations')->status());
    }

    public function testIntegrationWithActiveServicesCannotBeDeleted(): void
    {
        $admin = $this->createUser(Role::ADMIN);
        $this->actingAs($admin);
        $this->post('/admin/integrations', $this->nextcloudForm());
        $id = (int) $this->db->fetchValue('SELECT id FROM nx_integrations');
        $this->db->insert('nx_services', ['user_id' => $this->createUser()->id, 'integration_id' => $id, 'category' => 'nextcloud', 'name' => 'x', 'status' => 'active', 'billing_cycle' => 'monthly', 'currency' => 'EUR', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);

        $this->post('/admin/integrations/' . $id . '/delete');

        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_integrations WHERE id = ?', [$id]));
    }

    public function testPagesRender(): void
    {
        $this->actingAs($this->createUser(Role::SUPER_ADMIN));
        $this->post('/admin/integrations', $this->nextcloudForm());
        $id = (int) $this->db->fetchValue('SELECT id FROM nx_integrations');

        foreach (['/admin/integrations', '/admin/integrations/create', '/admin/integrations/create?type=proxmox', '/admin/integrations/create?type=teamspeak', '/admin/integrations/create?type=pterodactyl', '/admin/integrations/' . $id, '/admin/integrations/' . $id . '/edit', '/admin/integrations/' . $id . '/resources'] as $page) {
            self::assertSame(200, $this->get($page)->status(), $page);
        }
    }
}
