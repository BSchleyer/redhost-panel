<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use DateTimeImmutable;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Integration\IntegrationRepository;
use Nebrix\Queue\Worker;
use Nebrix\Repository\ProductRepository;
use Nebrix\Scheduler\Scheduler;
use Nebrix\Service\ServiceRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\FeatureTestCase;
use Nebrix\Tests\Support\FakeHttp;
use Nebrix\Tests\Support\FakeStripeHttp;
use Stripe\ApiRequestor;

final class OrderLifecycleTest extends FeatureTestCase
{
    private const WEBHOOK_SECRET = 'whsec_lifecycle';

    private FakeHttp $http;

    /** @var array<int, array{name: string, status: string}> */
    private array $guests = [];

    /** @var array<string, string> */
    private array $tasks = [];

    private User $customer;

    private int $integrationId;

    protected function setUp(): void
    {
        parent::setUp();
        $settings = $this->app->container()->get(SettingsRepository::class);
        foreach (['payments.stripe.enabled' => true, 'payments.stripe.secret_key' => 'sk_test_x', 'payments.stripe.webhook_secret' => self::WEBHOOK_SECRET] as $key => $value) {
            $settings->set($key, $value);
        }
        ApiRequestor::setHttpClient(new FakeStripeHttp());

        $this->http = new FakeHttp();
        $this->http
            ->on('GET', '/api2/json/nodes', ['data' => [['node' => 'pve1', 'status' => 'online', 'mem' => 1, 'maxmem' => 10]]])
            ->on('GET', '/api2/json/cluster/resources', function (): Psr7Response {
                $data = [];
                foreach ($this->guests as $vmid => $guest) {
                    $data[] = ['vmid' => $vmid, 'name' => $guest['name'], 'status' => $guest['status']];
                }

                return new Psr7Response(200, [], json_encode(['data' => $data], JSON_THROW_ON_ERROR));
            })
            ->on('POST', '/api2/json/nodes/pve1/lxc', function ($request): Psr7Response {
                $form = FakeHttp::form($request);
                $this->guests[(int) $form['vmid']] = ['name' => $form['hostname'], 'status' => 'running', 'description' => $form['description'] ?? ''];
                $this->tasks['UPID:pve1:00000001:00000002:6553F00D:vzcreate:' . $form['vmid'] . ':nx@pve!t:'] = 'running';

                return new Psr7Response(200, [], json_encode(['data' => array_key_last($this->tasks)], JSON_THROW_ON_ERROR));
            })
            ->on('GET', '/api2/json/nodes/pve1/tasks/(?<upid>[^/]+)/status', function ($request, array $m): Psr7Response {
                $done = ($this->tasks[rawurldecode($m['upid'])] ?? '') === 'done';

                return new Psr7Response(200, [], json_encode(['data' => ['status' => $done ? 'stopped' : 'running', 'exitstatus' => $done ? 'OK' : null]], JSON_THROW_ON_ERROR));
            })
            ->on('POST', '/api2/json/nodes/pve1/lxc/(?<vmid>\d+)/vncproxy', ['data' => ['port' => '5900', 'ticket' => 'PVEVNC:TICKET']])
            ->on('GET', '/api2/json/nodes/pve1/lxc/(?<vmid>\d+)/config', fn ($r, array $m): Psr7Response => new Psr7Response(200, [], json_encode(['data' => ['description' => $this->guests[(int) $m['vmid']]['description'] ?? '']], JSON_THROW_ON_ERROR)))
            ->on('GET', '/api2/json/nodes/pve1/lxc/(?<vmid>\d+)/status/current', fn ($r, array $m): Psr7Response => new Psr7Response(200, [], json_encode(['data' => ['status' => $this->guests[(int) $m['vmid']]['status'] ?? 'stopped', 'cpu' => 0.05, 'mem' => 100, 'maxmem' => 1000]], JSON_THROW_ON_ERROR)))
            ->on('POST', '/api2/json/nodes/pve1/lxc/(?<vmid>\d+)/status/(?<action>\w+)', function ($r, array $m): Psr7Response {
                $this->guests[(int) $m['vmid']]['status'] = in_array($m['action'], ['stop', 'shutdown'], true) ? 'stopped' : 'running';

                return new Psr7Response(200, [], json_encode(['data' => 'UPID:pve1:00000009:00000009:6553F00D:vz' . $m['action'] . ':' . $m['vmid'] . ':nx@pve!t:'], JSON_THROW_ON_ERROR));
            });
        $this->app->container()->set(ClientInterface::class, fn (): ClientInterface => $this->http->client());

        $this->integrationId = $this->app->container()->get(IntegrationRepository::class)->create('proxmox', 'PVE', true, ['base_url' => 'https://203.0.113.10:8006', 'token_id' => 'nx@pve!t', 'verify_tls' => true], ['token_secret' => '0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0']);
        $this->customer = $this->createUser();
        $this->clock()->freeze(new DateTimeImmutable('2026-06-15 10:00:00'));
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    private function product(array $overrides = []): array
    {
        $repository = $this->app->container()->get(ProductRepository::class);
        $id = $repository->create($overrides + [
            'category' => 'lxc', 'name' => 'LXC S', 'slug' => 'lxc-s-' . bin2hex(random_bytes(2)), 'price' => 500, 'setup_fee' => 0, 'currency' => 'EUR',
            'billing_cycle' => 'monthly', 'tax_rate_bp' => 1900, 'stock' => 3, 'is_visible' => 1, 'is_active' => 1, 'auto_provision' => 1,
            'integration_id' => $this->integrationId,
            'provisioning_config' => ['ostemplate' => 'local:vztmpl/debian-12.tar.zst', 'storage' => 'local-lvm', 'cores' => 1, 'memory_mb' => 512, 'swap_mb' => 0, 'disk_gb' => 8, 'bridge' => 'vmbr0', 'rate_mbps' => 0, 'unprivileged' => true, 'vmid_start' => 20000],
        ]);

        return (array) $repository->find($id);
    }

    private function work(int $times = 10): void
    {
        $worker = $this->app->container()->get(Worker::class);
        for ($i = 0; $i < $times && $worker->runOnce('test-worker'); $i++) {
        }
    }

    private function payWithStripe(array $invoice, string $intent): void
    {
        $payload = json_encode(['id' => 'evt_' . $intent, 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_test_' . $intent, 'payment_status' => 'paid', 'amount_total' => (int) $invoice['total'], 'currency' => 'eur',
            'payment_intent' => $intent, 'metadata' => ['invoice_id' => (string) $invoice['id']],
        ]]], JSON_THROW_ON_ERROR);
        self::assertSame(200, $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)])->status());
    }

    public function testCompleteLifecycle(): void
    {
        $product = $this->product();
        $this->actingAs($this->customer);

        // Order: price and options are taken from the product, not from the request.
        $this->post('/store/' . $product['slug'], ['hostname' => 'web01', 'accept_terms' => '1', 'price' => '1', 'tax_rate_bp' => '0']);
        $invoice = (array) $this->db->fetchOne('SELECT * FROM nx_invoices WHERE user_id = ?', [$this->customer->id]);
        self::assertSame(595, (int) $invoice['total']);
        self::assertSame(2, (int) $this->db->fetchValue('SELECT stock FROM nx_products WHERE id = ?', [$product['id']]));
        self::assertNotNull($this->lastMail('order_confirmation'));

        // Payment via verified webhook, then the worker fulfils and provisions.
        $this->payWithStripe($invoice, 'pi_order');
        $this->work();
        $service = $this->app->container()->get(ServiceRepository::class)->findForUser((int) $this->db->fetchValue('SELECT id FROM nx_services WHERE user_id = ?', [$this->customer->id]), $this->customer->id);
        self::assertNotNull($service);
        self::assertSame('provisioning', $service->status, 'Waiting for the Proxmox task.');
        self::assertSame('2026-07-15 10:00:00', $service->nextDueAt);
        self::assertSame(500, $service->recurringAmount);
        $provisionJob = (array) $this->db->fetchOne("SELECT * FROM nx_jobs WHERE type = 'service.provision'");
        self::assertSame('pending', $provisionJob['status']);
        self::assertSame(0, (int) $provisionJob['attempts'], 'Waiting does not consume retry attempts.');

        // Remote task finishes.
        foreach (array_keys($this->tasks) as $upid) {
            $this->tasks[$upid] = 'done';
        }
        $this->clock()->freeze(new DateTimeImmutable('2026-06-15 10:01:00'));
        $this->work();
        $service = $this->app->container()->get(ServiceRepository::class)->find($service->id);
        self::assertNotNull($service);
        self::assertSame('active', $service->status);
        self::assertSame('pve:pve1:' . (20000 + $service->id), $service->externalId);
        self::assertTrue($this->mailQueued('service_provisioned'));

        // The customer sees live data and can open the access data (audited).
        $page = $this->get('/services/' . $service->id);
        self::assertSame(200, $page->status());
        self::assertStringContainsString('web01', $page->body());
        $credentials = $this->get('/services/' . $service->id . '/credentials');
        self::assertStringContainsString('root', $credentials->body());
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_audit_log WHERE action = 'service.credentials_viewed'"));

        // A replayed payment webhook does not create a second service.
        $this->payWithStripe($invoice, 'pi_order');
        $this->work();
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_services'));

        // Renewal invoice 7 days before the due date, created only once.
        $scheduler = $this->app->container()->get(Scheduler::class);
        $this->clock()->freeze(new DateTimeImmutable('2026-07-09 12:00:00'));
        $scheduler->createRenewalInvoices();
        $scheduler->createRenewalInvoices();
        $renewal = (array) $this->db->fetchOne("SELECT * FROM nx_invoices WHERE type = 'renewal'");
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_invoices WHERE type = 'renewal'"));
        self::assertSame(595, (int) $renewal['total']);
        self::assertTrue($this->mailQueued('service_expiring'));

        // Not paid: suspended after the grace period, remote server stopped.
        $this->clock()->freeze(new DateTimeImmutable('2026-07-16 12:00:00'));
        $scheduler->suspendOverdue();
        self::assertSame('suspended', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$service->id]));
        $this->work();
        self::assertSame('stopped', $this->guests[20000 + $service->id]['status']);
        self::assertTrue($this->mailQueued('service_suspended'));

        // Paying the renewal reactivates the service and extends the term from the old due date.
        $this->payWithStripe($renewal, 'pi_renewal');
        $this->work();
        $service = $this->app->container()->get(ServiceRepository::class)->find($service->id);
        self::assertNotNull($service);
        self::assertSame('active', $service->status);
        self::assertSame('2026-08-15 10:00:00', $service->nextDueAt);
        self::assertSame('running', $this->guests[20000 + $service->id]['status']);
    }

    public function testProvisioningFailureIsRecoverable(): void
    {
        $product = $this->product(['provisioning_config' => ['ostemplate' => 'local:vztmpl/x.tar.zst', 'storage' => 's', 'disk_gb' => 1, 'vmid_start' => 20000, 'node' => 'missing-node']]);
        $this->actingAs($this->customer);
        $this->post('/store/' . $product['slug'], ['hostname' => 'fail01', 'accept_terms' => '1']);
        $invoice = (array) $this->db->fetchOne('SELECT * FROM nx_invoices WHERE user_id = ?', [$this->customer->id]);
        $this->payWithStripe($invoice, 'pi_fail');

        // Exhaust all attempts (backoff is skipped by moving the clock forward).
        for ($i = 0; $i < 8; $i++) {
            $this->work();
            $this->clock()->freeze($this->clock()->now()->modify('+7 hours'));
        }

        $serviceId = (int) $this->db->fetchValue('SELECT id FROM nx_services');
        self::assertSame('failed', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]), 'Payment success does not depend on provisioning.');
        self::assertSame('failed', $this->db->fetchValue("SELECT status FROM nx_jobs WHERE type = 'service.provision'"));

        // Staff fixes the product and retries.
        $this->db->run("UPDATE nx_services SET config = JSON_SET(config, '$.node', 'pve1') WHERE id = ?", [$serviceId]);
        $this->actingAs($this->createUser(Role::SUPPORT));
        $this->post('/admin/services/' . $serviceId . '/action', ['action' => 'retry']);
        $this->work();
        foreach (array_keys($this->tasks) as $upid) {
            $this->tasks[$upid] = 'done';
        }
        $this->clock()->freeze($this->clock()->now()->modify('+1 minute'));
        $this->work();

        self::assertSame('active', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_services'));
    }

    public function testManualProductsAreActivatedByStaff(): void
    {
        $product = $this->product(['category' => 'dedicated', 'integration_id' => null, 'auto_provision' => 0, 'provisioning_config' => ['hardware' => 'Xeon']]);
        $this->actingAs($this->customer);
        $this->post('/store/' . $product['slug'], ['hostname' => 'dedi01', 'accept_terms' => '1']);
        $invoice = (array) $this->db->fetchOne('SELECT * FROM nx_invoices WHERE user_id = ?', [$this->customer->id]);
        $this->payWithStripe($invoice, 'pi_dedi');
        $this->work();

        $serviceId = (int) $this->db->fetchValue('SELECT id FROM nx_services');
        self::assertSame('pending', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'service.provision'"));

        $this->actingAs($this->createUser(Role::ADMIN));
        $this->post('/admin/services/' . $serviceId . '/action', ['action' => 'activate', 'username' => 'root', 'password' => 'Initial-Pass-1', 'access_info' => 'IPMI: 198.51.100.7']);
        self::assertSame('active', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertStringNotContainsString('Initial-Pass-1', (string) $this->db->fetchValue('SELECT credentials FROM nx_services WHERE id = ?', [$serviceId]));

        self::assertSame(200, $this->get('/admin/services/' . $serviceId . '/credentials')->status());
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_audit_log WHERE action = 'service.credentials_viewed_by_staff'"));
    }

    public function testCustomerAccessControlAndGroupPermissions(): void
    {
        $other = $this->createUser();
        $serviceId = $this->app->container()->get(ServiceRepository::class)->create([
            'user_id' => $other->id, 'integration_id' => $this->integrationId, 'category' => 'lxc', 'name' => 'theirs', 'status' => 'active',
            'billing_cycle' => 'monthly', 'currency' => 'EUR', 'config' => [],
        ]);
        $this->actingAs($this->customer);

        foreach (['/services/' . $serviceId, '/services/' . $serviceId . '/credentials'] as $path) {
            self::assertSame(404, $this->get($path)->status(), $path);
        }
        self::assertSame(404, $this->post('/services/' . $serviceId . '/action', ['action' => 'stop'])->status());

        // A group without self-service permission cannot trigger power actions on its own services.
        $groupId = $this->db->insert('nx_customer_groups', ['name' => 'Restricted', 'discount_bp' => 0, 'can_order' => 0, 'can_manage_services' => 0, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
        $this->db->update('nx_users', ['customer_group_id' => $groupId], ['id' => $other->id]);
        $this->db->run("UPDATE nx_services SET remote = '{\"node\":\"pve1\",\"vmid\":20500,\"type\":\"lxc\"}' WHERE id = ?", [$serviceId]);
        $this->guests[20500] = ['name' => 'theirs', 'status' => 'running'];
        $this->actingAs($this->app->container()->get(\Nebrix\Repository\UserRepository::class)->find($other->id) ?? $other);
        self::assertSame(403, $this->post('/services/' . $serviceId . '/action', ['action' => 'stop'])->status());
        self::assertSame('running', $this->guests[20500]['status']);

        // … and cannot order.
        $product = $this->product();
        $this->post('/store/' . $product['slug'], ['hostname' => 'x1', 'accept_terms' => '1']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_orders WHERE user_id = ?', [$other->id]));
    }

    public function testFreeAndSoldOutProducts(): void
    {
        $free = $this->product(['category' => 'nextcloud', 'price' => 0, 'tax_rate_bp' => 0, 'integration_id' => null, 'auto_provision' => 0, 'provisioning_config' => ['quota_gb' => 1]]);
        $this->actingAs($this->customer);
        $this->post('/store/' . $free['slug'], ['accept_terms' => '1']);
        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE user_id = ?', [$this->customer->id]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'order.fulfil'"));

        $soldOut = $this->product(['stock' => 0]);
        self::assertSame(404, $this->get('/store/' . $soldOut['slug'])->status());
        $this->post('/store/' . $soldOut['slug'], ['hostname' => 'x', 'accept_terms' => '1']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_orders WHERE user_id = ?', [$this->customer->id]));

        $last = $this->product(['stock' => 1]);
        $this->post('/store/' . $last['slug'], ['hostname' => 'last1', 'accept_terms' => '1']);
        $this->post('/store/' . $last['slug'], ['hostname' => 'last2', 'accept_terms' => '1']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT stock FROM nx_products WHERE id = ?', [$last['id']]));
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_orders WHERE user_id = ?', [$this->customer->id]));
    }

    public function testFreeRenewalsAreSettledAutomatically(): void
    {
        $this->clock()->freeze(new DateTimeImmutable('2026-06-25 12:00:00'));
        $serviceId = $this->app->container()->get(ServiceRepository::class)->create([
            'user_id' => $this->customer->id, 'category' => 'dedicated', 'name' => 'Sponsored', 'status' => 'active',
            'billing_cycle' => 'monthly', 'recurring_amount' => 0, 'tax_rate_bp' => 1900, 'currency' => 'EUR', 'next_due_at' => '2026-07-01 00:00:00', 'config' => [],
        ]);
        $scheduler = $this->app->container()->get(Scheduler::class);

        $scheduler->createRenewalInvoices();

        self::assertSame('paid', $this->db->fetchValue("SELECT status FROM nx_invoices WHERE service_id = ? AND type = 'renewal'", [$serviceId]));
        self::assertSame('2026-08-01 00:00:00', $this->db->fetchValue('SELECT next_due_at FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertFalse($this->mailQueued('service_expiring'));

        $this->clock()->freeze(new DateTimeImmutable('2026-07-10 12:00:00'));
        $scheduler->suspendOverdue();
        self::assertSame('active', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
    }

    public function testCustomerConsoleAndActionsWithInput(): void
    {
        $serviceId = $this->app->container()->get(ServiceRepository::class)->create([
            'user_id' => $this->customer->id, 'integration_id' => $this->integrationId, 'category' => 'lxc', 'name' => 'web', 'status' => 'active',
            'billing_cycle' => 'monthly', 'currency' => 'EUR',
            'config' => ['ostemplate' => 'local:vztmpl/debian-12.tar.zst', 'reinstall_templates' => 'local:vztmpl/alpine.tar.zst|Alpine'],
        ]);
        $this->db->run("UPDATE nx_services SET remote = '{\"node\":\"pve1\",\"vmid\":20900,\"type\":\"lxc\",\"step\":\"done\"}', external_id = 'pve:pve1:20900' WHERE id = ?", [$serviceId]);
        $this->guests[20900] = ['name' => 'web', 'status' => 'running', 'description' => 'nebrix-service-' . $serviceId];
        $this->actingAs($this->customer);

        $page = $this->get('/services/' . $serviceId);
        self::assertStringContainsString('/services/' . $serviceId . '/console', $page->body());
        self::assertStringContainsString('name="template"', $page->body(), 'Actions with input render their fields.');

        $console = $this->get('/services/' . $serviceId . '/console');
        self::assertSame(200, $console->status());
        self::assertStringContainsString('connect-src \'self\' ws://localhost', (string) $console->header('Content-Security-Policy'));
        self::assertSame(419, $this->post('/services/' . $serviceId . '/console/session', [], false)->status());
        $session = json_decode($this->post('/services/' . $serviceId . '/console/session')->body(), true);
        self::assertSame('PVEVNC:TICKET', $session['password']);
        self::assertMatchesRegularExpression('#^ws://localhost(:\d+)?/console/ws\?token=[a-f0-9]{64}$#', $session['url']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_audit_log WHERE action = 'service.console_opened'"));

        // Reinstall only with an offered template.
        $this->post('/services/' . $serviceId . '/action', ['action' => 'reinstall', 'template' => 'local:vztmpl/evil.tar.zst']);
        self::assertSame('active', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
        $this->post('/services/' . $serviceId . '/action', ['action' => 'reinstall', 'template' => 'local:vztmpl/alpine.tar.zst']);
        self::assertSame('provisioning', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertSame('destroy', json_decode((string) $this->db->fetchValue('SELECT remote FROM nx_services WHERE id = ?', [$serviceId]), true)['step']);

        // Other customers cannot open the console.
        $this->actingAs($this->createUser());
        self::assertSame(404, $this->get('/services/' . $serviceId . '/console')->status());
        self::assertSame(404, $this->post('/services/' . $serviceId . '/console/session')->status());
    }

    public function testInvalidOrderOptionsAreRejected(): void
    {
        $product = $this->product();
        $this->actingAs($this->customer);

        foreach (['', 'UPPER', 'a_b', str_repeat('a', 64), 'web;rm -rf'] as $hostname) {
            $this->post('/store/' . $product['slug'], ['hostname' => $hostname, 'accept_terms' => '1']);
        }
        $this->post('/store/' . $product['slug'], ['hostname' => 'valid-host']);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_orders'));
    }

    public function testOptInAutomaticTermination(): void
    {
        $serviceId = $this->app->container()->get(ServiceRepository::class)->create([
            'user_id' => $this->customer->id, 'integration_id' => $this->integrationId, 'category' => 'lxc', 'name' => 'old', 'status' => 'suspended',
            'suspension_reason' => 'overdue', 'billing_cycle' => 'monthly', 'currency' => 'EUR', 'next_due_at' => '2026-05-01 00:00:00', 'config' => [],
        ]);
        $scheduler = $this->app->container()->get(Scheduler::class);

        self::assertSame('automatic termination disabled', $scheduler->terminateLongSuspended());
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'service.terminate'"));

        $this->app->container()->get(SettingsRepository::class)->set('billing.terminate_after_days', 30);
        $scheduler->terminateLongSuspended();
        $this->work();

        self::assertSame('terminated', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
    }

    public function testSchedulerRecordsRunsAndPagesRender(): void
    {
        $results = $this->app->container()->get(Scheduler::class)->run(true);
        self::assertArrayHasKey('billing.renewal_invoices', $results);
        self::assertSame('success', $this->db->fetchValue("SELECT last_status FROM nx_scheduler_runs WHERE task = 'maintenance.cleanup'"));

        $product = $this->product();
        $this->actingAs($this->customer);
        foreach (['/store', '/store/' . $product['slug'], '/services', '/orders', '/invoices'] as $page) {
            self::assertSame(200, $this->get($page)->status(), $page);
        }

        $this->post('/store/' . $product['slug'], ['hostname' => 'render1', 'accept_terms' => '1']);
        $this->actingAs($this->createUser(Role::SUPER_ADMIN));
        $orderId = (int) $this->db->fetchValue('SELECT id FROM nx_orders');
        $invoiceId = (int) $this->db->fetchValue('SELECT id FROM nx_invoices');
        foreach (['/admin/orders', '/admin/orders/' . $orderId, '/admin/invoices', '/admin/invoices/' . $invoiceId, '/admin/services', '/admin'] as $page) {
            self::assertSame(200, $this->get($page)->status(), $page);
        }

        // Bank transfer recorded by staff settles the invoice exactly once.
        $this->post('/admin/invoices/' . $invoiceId . '/payment', ['amount' => '5.95', 'reference' => 'SEPA 2026/0042']);
        $this->post('/admin/invoices/' . $invoiceId . '/payment', ['amount' => '5.95', 'reference' => 'SEPA 2026/0042']);
        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoiceId]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_transactions WHERE gateway = 'manual'"));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'order.fulfil'"));
        $this->work();
        $serviceId = (int) $this->db->fetchValue('SELECT id FROM nx_services');
        self::assertSame(200, $this->get('/admin/services/' . $serviceId)->status());
    }
}
