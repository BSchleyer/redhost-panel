<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration\Integrations;

use GuzzleHttp\Psr7\Response;
use Nebrix\Integration\HealthResult;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\IntegrationRepository;
use Nebrix\Provisioning\ProxmoxProvisioner;
use Nebrix\Provisioning\RetryLaterException;
use Nebrix\Service\ServiceRepository;
use Nebrix\Tests\Support\FakeHttp;

final class ProxmoxTest extends IntegrationTestCase
{
    private const TOKEN_SECRET = '0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0';

    private int $integrationId;

    /** @var array<int, array{name: string, status: string, description?: string}> */
    private array $guests = [];

    /** @var array<string, string> upid => status */
    private array $tasks = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->integrationId = $this->createIntegration('proxmox', [
            'base_url' => 'https://203.0.113.10:8006',
            'token_id' => 'nebrix@pve!panel',
            'verify_tls' => true,
        ], ['token_secret' => self::TOKEN_SECRET]);

        $this->http
            ->on('GET', '/api2/json/version', ['data' => ['version' => '8.2.4']])
            ->on('GET', '/api2/json/nodes', ['data' => [
                ['node' => 'pve1', 'status' => 'online', 'mem' => 60, 'maxmem' => 100],
                ['node' => 'pve2', 'status' => 'online', 'mem' => 10, 'maxmem' => 100],
                ['node' => 'pve3', 'status' => 'offline', 'mem' => 0, 'maxmem' => 500],
            ]])
            ->on('GET', '/api2/json/cluster/resources', function (): Response {
                $data = [];
                foreach ($this->guests as $vmid => $guest) {
                    $data[] = ['vmid' => $vmid, 'name' => $guest['name'], 'status' => $guest['status']];
                }

                return new Response(200, [], json_encode(['data' => $data], JSON_THROW_ON_ERROR));
            })
            ->on('POST', '/api2/json/nodes/(?<node>[^/]+)/lxc', function ($request, array $m): Response {
                $form = FakeHttp::form($request);
                $this->guests[(int) $form['vmid']] = ['name' => $form['hostname'], 'status' => 'running', 'description' => $form['description'] ?? ''];
                $upid = 'UPID:' . $m['node'] . ':0000AAAA:0000BBBB:6553F00D:vzcreate:' . $form['vmid'] . ':nebrix@pve!panel:';
                $this->tasks[$upid] = 'running';

                return new Response(200, [], json_encode(['data' => $upid], JSON_THROW_ON_ERROR));
            })
            ->on('GET', '/api2/json/nodes/(?<node>[^/]+)/(?<type>lxc|qemu)/(?<vmid>\d+)/config', fn ($request, array $m): Response => new Response(200, [], json_encode(['data' => [
                'hostname' => $this->guests[(int) $m['vmid']]['name'] ?? '',
                'description' => $this->guests[(int) $m['vmid']]['description'] ?? '',
            ]], JSON_THROW_ON_ERROR)))
            ->on('GET', '/api2/json/nodes/(?<node>[^/]+)/tasks/(?<upid>[^/]+)/status', function ($request, array $m): Response {
                $status = $this->tasks[rawurldecode($m['upid'])] ?? 'unknown';

                return new Response(200, [], json_encode(['data' => ['status' => $status === 'done' ? 'stopped' : $status, 'exitstatus' => $status === 'done' ? 'OK' : null]], JSON_THROW_ON_ERROR));
            });
    }

    public function testConnectionTestUsesApiTokenAndReportsNodes(): void
    {
        $manager = $this->app->container()->get(IntegrationManager::class);
        $result = $manager->testConnection($manager->get($this->integrationId));

        self::assertSame(HealthResult::DEGRADED, $result->status, 'One of three nodes is offline.');
        self::assertStringContainsString('Proxmox VE 8.2.4, 2/3 node(s) online', $result->message);
        self::assertSame('PVEAPIToken=nebrix@pve!panel=' . self::TOKEN_SECRET, $this->http->last()->getHeaderLine('Authorization'));
        self::assertSame('degraded', $this->db->fetchValue('SELECT health_status FROM nx_integrations WHERE id = ?', [$this->integrationId]));

        // Redirects are disabled and timeouts set for every request.
        $options = $this->http->history[0]['options'];
        self::assertFalse($options['allow_redirects']);
        self::assertGreaterThan(0, $options['timeout']);
        self::assertTrue($options['verify']);
    }

    public function testCredentialsAreEncryptedAtRest(): void
    {
        $stored = (string) $this->db->fetchValue('SELECT secrets FROM nx_integrations WHERE id = ?', [$this->integrationId]);

        self::assertStringStartsWith('nx1:', $stored);
        self::assertStringNotContainsString(self::TOKEN_SECRET, $stored);
    }

    public function testUnsafeBaseUrlIsRejectedBeforeAnyRequest(): void
    {
        $repository = $this->app->container()->get(IntegrationRepository::class);
        $repository->update($this->integrationId, 'PVE', true, ['base_url' => 'https://169.254.169.254', 'token_id' => 'a@pve!b', 'verify_tls' => true], []);
        $manager = $this->app->container()->get(IntegrationManager::class);

        $result = $manager->testConnection($manager->get($this->integrationId));

        self::assertSame(HealthResult::DOWN, $result->status);
        self::assertStringContainsString('blocked network range', $result->message);
        self::assertSame([], $this->http->history);
    }

    public function testPrivilegedContainerIsCreatedWithoutFeatureFlags(): void
    {
        $owner = $this->createUser();
        $service = $this->createService($owner, 'lxc', $this->integrationId, [
            'ostemplate' => 'local:vztmpl/debian-12.tar.zst', 'storage' => 'local-lvm', 'cores' => 1, 'memory_mb' => 512,
            'swap_mb' => 0, 'disk_gb' => 8, 'bridge' => 'vmbr0', 'rate_mbps' => 0, 'unprivileged' => false, 'vmid_start' => 20000,
            '_options' => ['hostname' => 'legacy01'],
        ]);

        try {
            $this->app->container()->get(ProxmoxProvisioner::class)->provision($service, $owner);
            self::fail('Expected RetryLater while the task runs.');
        } catch (RetryLaterException) {
        }

        $form = FakeHttp::form($this->http->requests('POST', '/api2/json/nodes/pve2/lxc')[0]);
        self::assertSame('0', $form['unprivileged']);
        self::assertArrayNotHasKey('features', $form, 'Only root@pam may set feature flags on privileged containers.');
    }

    public function testLxcProvisioningIsResumableAndIdempotent(): void
    {
        $owner = $this->createUser();
        $service = $this->createService($owner, 'lxc', $this->integrationId, [
            'ostemplate' => 'local:vztmpl/debian-12.tar.zst', 'storage' => 'local-lvm', 'cores' => 2, 'memory_mb' => 2048,
            'swap_mb' => 512, 'disk_gb' => 20, 'bridge' => 'vmbr0', 'rate_mbps' => 50, 'unprivileged' => true, 'vmid_start' => 20000,
            '_options' => ['hostname' => 'web01'],
        ]);
        $provisioner = $this->app->container()->get(ProxmoxProvisioner::class);

        // First run: creates the container on the node with the most free memory, then waits for the task.
        try {
            $provisioner->provision($service, $owner);
            self::fail('Expected RetryLater while the task runs.');
        } catch (RetryLaterException) {
        }

        $create = $this->http->requests('POST', '/api2/json/nodes/pve2/lxc');
        self::assertCount(1, $create);
        $form = FakeHttp::form($create[0]);
        $expectedVmid = 20000 + $service->id;
        self::assertSame((string) $expectedVmid, $form['vmid']);
        self::assertSame('web01', $form['hostname']);
        self::assertSame('local-lvm:20', $form['rootfs']);
        self::assertSame('name=eth0,bridge=vmbr0,ip=dhcp,ip6=auto,rate=50', $form['net0']);
        self::assertSame('1', $form['unprivileged']);
        self::assertSame('nesting=1', $form['features']);

        // Second run while the task is still running: no new create call.
        try {
            $provisioner->provision($this->reload($service->id), $owner);
            self::fail('Expected RetryLater.');
        } catch (RetryLaterException) {
        }
        self::assertCount(1, $this->http->requests('POST', '/api2/json/nodes/pve2/lxc'));

        // Task finished: provisioning completes.
        foreach (array_keys($this->tasks) as $upid) {
            $this->tasks[$upid] = 'done';
        }
        $result = $provisioner->provision($this->reload($service->id), $owner);

        self::assertSame('pve:pve2:' . $expectedVmid, $result->externalId);
        self::assertSame('root', $result->credentials['username']);
        self::assertSame($form['password'], $result->credentials['password'], 'The password stored for the customer is the one sent to Proxmox.');
        self::assertCount(1, $this->http->requests('POST', '/api2/json/nodes/pve2/lxc'));

        $stored = (string) $this->db->fetchValue('SELECT credentials FROM nx_services WHERE id = ?', [$service->id]);
        self::assertStringNotContainsString($form['password'], $stored);
    }

    public function testCrashAfterRemoteCreateDoesNotCreateADuplicate(): void
    {
        $owner = $this->createUser();
        $service = $this->createService($owner, 'lxc', $this->integrationId, [
            'ostemplate' => 'local:vztmpl/debian-12.tar.zst', 'storage' => 'local-lvm', 'disk_gb' => 10, 'vmid_start' => 20000,
            'node' => 'pve1', '_options' => ['hostname' => 'db01'],
        ]);
        $vmid = 20000 + $service->id;
        // Simulate: an earlier attempt created the guest but died before storing the task ID.
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($service->id, ['node' => 'pve1', 'vmid' => $vmid, 'type' => 'lxc', 'step' => 'create']);
        $this->guests[$vmid] = ['name' => 'db01', 'status' => 'running', 'description' => 'nebrix-service-' . $service->id . "\n"];

        $result = $this->app->container()->get(ProxmoxProvisioner::class)->provision($this->reload($service->id), $owner);

        self::assertSame('pve:pve1:' . $vmid, $result->externalId);
        self::assertSame([], $this->http->requests('POST', '/api2/json/nodes/pve1/lxc'));
    }

    public function testVmidConflictWithForeignGuestFailsSafely(): void
    {
        $owner = $this->createUser();
        $service = $this->createService($owner, 'lxc', $this->integrationId, ['ostemplate' => 'local:vztmpl/x.tar.zst', 'storage' => 's', 'disk_gb' => 5, 'vmid_start' => 20000, 'node' => 'pve1', '_options' => ['hostname' => 'mine']]);
        $this->guests[20000 + $service->id] = ['name' => 'someone-elses-vm', 'status' => 'running'];

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('already used by another guest');
        $this->app->container()->get(ProxmoxProvisioner::class)->provision($service, $owner);
    }

    public function testGuestWithTheSameHostnameButAnotherOwnerIsNotAdopted(): void
    {
        $owner = $this->createUser();
        $service = $this->createService($owner, 'lxc', $this->integrationId, ['ostemplate' => 'local:vztmpl/x.tar.zst', 'storage' => 's', 'disk_gb' => 5, 'vmid_start' => 20000, 'node' => 'pve1', '_options' => ['hostname' => 'victim-web']]);
        // Another customer's guest (overlapping vmid_start of a second product) – the attacker chose its hostname.
        $this->guests[20000 + $service->id] = ['name' => 'victim-web', 'status' => 'running', 'description' => 'nebrix-service-999999'];

        try {
            $this->app->container()->get(ProxmoxProvisioner::class)->provision($service, $owner);
            self::fail('A guest of another service must not be adopted.');
        } catch (IntegrationException $exception) {
            self::assertStringContainsString('already used by another guest', $exception->getMessage());
        }

        self::assertNotSame('done', $this->reload($service->id)->remote['step'] ?? null);
        self::assertSame([], $this->http->requests('POST', '/api2/json/nodes/pve1/lxc'));

        // Terminating the failed service must not delete the other customer's guest.
        try {
            $this->app->container()->get(ProxmoxProvisioner::class)->terminate($this->reload($service->id));
            self::fail('Termination must refuse to delete a foreign guest.');
        } catch (IntegrationException $exception) {
            self::assertStringContainsString('was not deleted', $exception->getMessage());
        }
        self::assertSame([], $this->http->requests('DELETE', '/api2/json/nodes/pve1/lxc/' . (20000 + $service->id)));
        self::assertSame([], $this->http->requests('POST', '/api2/json/nodes/pve1/lxc/' . (20000 + $service->id) . '/status/stop'));
    }

    public function testPowerActionsAndStatus(): void
    {
        $owner = $this->createUser();
        $service = $this->createService($owner, 'vps', $this->integrationId, []);
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($service->id, ['node' => 'pve1', 'vmid' => 10500, 'type' => 'qemu']);
        $this->http
            ->on('GET', '/api2/json/nodes/pve1/qemu/10500/status/current', ['data' => ['status' => 'running', 'cpu' => 0.125, 'mem' => 1024, 'maxmem' => 4096, 'uptime' => 3600]])
            ->on('POST', '/api2/json/nodes/pve1/qemu/10500/status/reboot', ['data' => 'UPID:pve1:00000001:00000002:6553F00D:qmreboot:10500:nebrix@pve!panel:']);
        $provisioner = $this->app->container()->get(ProxmoxProvisioner::class);

        $status = $provisioner->status($this->reload($service->id));
        self::assertSame('running', $status['state']);
        self::assertSame(12.5, $status['cpu_percent']);

        $provisioner->perform($this->reload($service->id), 'reboot');
        self::assertCount(1, $this->http->requests('POST', '/api2/json/nodes/pve1/qemu/10500/status/reboot'));

        $this->expectException(IntegrationException::class);
        $provisioner->perform($this->reload($service->id), 'destroy');
    }

    public function testPathInjectionIsRejected(): void
    {
        $manager = $this->app->container()->get(IntegrationManager::class);
        $client = $manager->proxmox($manager->get($this->integrationId));

        $this->expectException(IntegrationException::class);
        $client->status('pve1/../../access', 'qemu', 100);
    }
}
