<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration\Integrations;

use GuzzleHttp\Psr7\Response;
use Nebrix\Integration\IntegrationException;
use Nebrix\Network\IpPoolService;
use Nebrix\Network\PoolExhaustedException;
use Nebrix\Provisioning\ProvisionResult;
use Nebrix\Provisioning\ProxmoxProvisioner;
use Nebrix\Provisioning\RetryLaterException;
use Nebrix\Provisioning\ServiceActionRunner;
use Nebrix\Security\ValidationException;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Nebrix\Tests\Support\FakeHttp;

/**
 * Static addressing, reinstall and password reset on Proxmox (fake API with
 * guests, tasks, status, deletion and the QEMU guest agent).
 */
final class ProxmoxLifecycleTest extends IntegrationTestCase
{
    private int $integrationId;

    /** @var array<int, array{name: string, status: string, description: string, type: string, config?: array<string, string>}> */
    private array $guests = [];

    /** @var array<string, string> */
    private array $tasks = [];

    private bool $agentRunning = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integrationId = $this->createIntegration('proxmox', ['base_url' => 'https://203.0.113.10:8006', 'token_id' => 'nebrix@pve!panel', 'verify_tls' => true], ['token_secret' => '0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0']);
        $json = static fn (mixed $data, int $status = 200): Response => new Response($status, [], json_encode(['data' => $data], JSON_THROW_ON_ERROR));
        $task = function (string $kind, int $vmid): string {
            $upid = 'UPID:pve1:00000001:00000002:6553F00D:' . $kind . ':' . $vmid . ':nebrix@pve!panel:';
            $this->tasks[$upid] = 'running';

            return $upid;
        };

        $this->http
            ->on('GET', '/api2/json/nodes', ['data' => [['node' => 'pve1', 'status' => 'online', 'mem' => 1, 'maxmem' => 10]]])
            ->on('GET', '/api2/json/cluster/resources', fn (): Response => $json(array_map(static fn (int $vmid, array $g): array => ['vmid' => $vmid, 'name' => $g['name'], 'status' => $g['status'], 'node' => 'pve1', 'type' => $g['type']], array_keys($this->guests), $this->guests)))
            ->on('POST', '/api2/json/nodes/pve1/lxc', function ($request) use ($json, $task): Response {
                $form = FakeHttp::form($request);
                $this->guests[(int) $form['vmid']] = ['name' => $form['hostname'], 'status' => 'running', 'description' => $form['description'], 'type' => 'lxc'];

                return $json($task('vzcreate', (int) $form['vmid']));
            })
            ->on('GET', '/api2/json/nodes/pve1/(?<type>lxc|qemu)/(?<vmid>\d+)/config', fn ($r, array $m): Response => $json(['description' => $this->guests[(int) $m['vmid']]['description'] ?? ''] + ($this->guests[(int) $m['vmid']]['config'] ?? [])))
            ->on('GET', '/api2/json/nodes/pve1/(?<type>lxc|qemu)/(?<vmid>\d+)/status/current', fn ($r, array $m): Response => $json(['status' => $this->guests[(int) $m['vmid']]['status'] ?? 'stopped']))
            ->on('POST', '/api2/json/nodes/pve1/(?<type>lxc|qemu)/(?<vmid>\d+)/status/(?<action>\w+)', function ($r, array $m) use ($json, $task): Response {
                $this->guests[(int) $m['vmid']]['status'] = in_array($m['action'], ['stop', 'shutdown'], true) ? 'stopped' : 'running';

                return $json($task('vz' . $m['action'], (int) $m['vmid']));
            })
            ->on('DELETE', '/api2/json/nodes/pve1/(?<type>lxc|qemu)/(?<vmid>\d+)', function ($r, array $m) use ($json, $task): Response {
                return $json($task('vzdestroy', (int) $m['vmid']));
            })
            ->on('GET', '/api2/json/nodes/pve1/tasks/(?<upid>[^/]+)/status', function ($r, array $m) use ($json): Response {
                $upid = rawurldecode($m['upid']);
                $done = ($this->tasks[$upid] ?? '') === 'done';

                return $json(['status' => $done ? 'stopped' : 'running', 'exitstatus' => $done ? 'OK' : null]);
            })
            ->on('POST', '/api2/json/nodes/pve1/qemu/(?<vmid>\d+)/agent/set-user-password', fn (): Response => $this->agentRunning ? $json(null) : new Response(500, [], json_encode(['data' => null, 'message' => 'QEMU guest agent is not running'], JSON_THROW_ON_ERROR)))
            ->on('POST', '/api2/json/nodes/pve1/qemu/(?<vmid>\d+)/config', fn (): Response => $json(null))
            ->on('POST', '/api2/json/nodes/pve1/(?<type>lxc|qemu)/(?<vmid>\d+)/vncproxy', fn (): Response => $json(['port' => '5901', 'ticket' => 'PVEVNC:6553F00D::secret-ticket', 'user' => 'nebrix@pve!panel', 'upid' => 'UPID:x']));
    }

    /** Completes running tasks; deletion tasks remove their guest. */
    private function finishTasks(): void
    {
        foreach ($this->tasks as $upid => $state) {
            if ($state === 'running') {
                $this->tasks[$upid] = 'done';
                if (str_contains($upid, ':vzdestroy:')) {
                    unset($this->guests[(int) explode(':', $upid)[6]]);
                }
            }
        }
    }

    private function provisionToEnd(Service $service): ProvisionResult
    {
        $owner = $this->app->container()->get(\Nebrix\Repository\UserRepository::class)->find($service->userId);
        self::assertNotNull($owner);
        for ($i = 0; $i < 10; $i++) {
            try {
                return $this->app->container()->get(ProxmoxProvisioner::class)->provision($this->reload($service->id), $owner);
            } catch (RetryLaterException) {
                $this->finishTasks();
            }
        }
        self::fail('Provisioning did not finish.');
    }

    private function container(?int $poolId, string $hostname): Service
    {
        return $this->createService($this->createUser(), 'lxc', $this->integrationId, [
            'ostemplate' => 'local:vztmpl/debian-12.tar.zst', 'storage' => 'local-lvm', 'cores' => 1, 'memory_mb' => 512, 'swap_mb' => 0,
            'disk_gb' => 8, 'bridge' => 'vmbr0', 'rate_mbps' => 0, 'unprivileged' => true, 'vmid_start' => 20000, 'node' => 'pve1',
            'ip_pool_id' => $poolId, 'reinstall_templates' => "local:vztmpl/ubuntu-24.04.tar.zst|Ubuntu 24.04\ninvalid template|Nope",
            '_options' => ['hostname' => $hostname],
        ]);
    }

    public function testStaticAddressesComeFromThePoolAndAreReleased(): void
    {
        $pools = $this->app->container()->get(IpPoolService::class);
        $poolId = $pools->createPool('Public FSN', '203.0.113.0/29', '203.0.113.1', '1.1.1.1, 9.9.9.9', $this->integrationId);
        self::assertCount(5, $pools->addresses($poolId), '/29 minus network, broadcast and gateway.');

        $first = $this->container($poolId, 'web1');
        $this->provisionToEnd($first);
        $second = $this->container($poolId, 'web2');
        $this->provisionToEnd($second);

        $creates = $this->http->requests('POST', '/api2/json/nodes/pve1/lxc');
        self::assertSame('name=eth0,bridge=vmbr0,ip=203.0.113.2/29,gw=203.0.113.1,ip6=auto', FakeHttp::form($creates[0])['net0']);
        self::assertSame('1.1.1.1 9.9.9.9', FakeHttp::form($creates[0])['nameserver']);
        self::assertStringContainsString('ip=203.0.113.3/29', FakeHttp::form($creates[1])['net0']);
        self::assertSame(['203.0.113.2'], $pools->addressesOf($first->id));

        // A retried run keeps the address.
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($first->id, ['step' => 'done']);
        $this->provisionToEnd($first);
        self::assertSame(['203.0.113.2'], $pools->addressesOf($first->id));

        // Termination deletes the guest and returns the address.
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->app->container()->get(ProxmoxProvisioner::class)->terminate($this->reload($first->id));
                break;
            } catch (RetryLaterException) {
                $this->finishTasks();
            }
        }
        self::assertSame([], $pools->addressesOf($first->id));
        self::assertArrayNotHasKey(20000 + $first->id, $this->guests);
        self::assertSame('free', $this->db->fetchValue("SELECT status FROM nx_ip_addresses WHERE address = '203.0.113.2'"));
    }

    public function testExhaustedPoolStopsProvisioning(): void
    {
        $pools = $this->app->container()->get(IpPoolService::class);
        $poolId = $pools->createPool('Tiny', '203.0.113.8/30', '203.0.113.9', null, null);
        $this->provisionToEnd($this->container($poolId, 'a1'));

        $this->expectException(PoolExhaustedException::class);
        $this->provisionToEnd($this->container($poolId, 'a2'));
    }

    public function testPoolValidation(): void
    {
        $pools = $this->app->container()->get(IpPoolService::class);
        foreach ([['10.0.0.0/24', '10.0.1.1'], ['10.0.0.0/8', '10.0.0.1'], ['not-a-network', '10.0.0.1']] as [$network, $gateway]) {
            try {
                $pools->createPool('X' . bin2hex(random_bytes(2)), $network, $gateway, null, null);
                self::fail('Invalid pool accepted: ' . $network);
            } catch (ValidationException) {
            }
        }

        $poolId = $pools->createPool('Reserve test', '198.51.100.0/30', '198.51.100.1', null, null);
        $address = (int) $this->db->fetchValue('SELECT id FROM nx_ip_addresses WHERE pool_id = ?', [$poolId]);
        $pools->setReserved($poolId, $address, true, 'router');
        $this->expectException(PoolExhaustedException::class);
        $pools->allocate($poolId, $this->container(null, 'r1')->id);
    }

    public function testReinstallRecreatesTheContainerWithTheChosenTemplate(): void
    {
        $poolId = $this->app->container()->get(IpPoolService::class)->createPool('Reinstall', '203.0.113.16/29', '203.0.113.17', null, null);
        $service = $this->container($poolId, 'app1');
        $first = $this->provisionToEnd($service);
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $runner = $this->app->container()->get(ServiceActionRunner::class);

        $templates = $runner->available($this->reload($service->id))['reinstall']->fields['template']['options'] ?? [];
        self::assertSame(['local:vztmpl/debian-12.tar.zst' => 'Default image', 'local:vztmpl/ubuntu-24.04.tar.zst' => 'Ubuntu 24.04'], $templates);
        self::assertArrayNotHasKey('reset_password', $runner->available($this->reload($service->id)), 'LXC passwords cannot be changed through the API.');

        $runner->run($this->reload($service->id), 'reinstall', ['template' => 'local:vztmpl/ubuntu-24.04.tar.zst'], null, null);
        self::assertSame('provisioning', $this->reload($service->id)->status);
        $second = $this->provisionToEnd($service);

        $creates = $this->http->requests('POST', '/api2/json/nodes/pve1/lxc');
        self::assertCount(2, $creates);
        self::assertSame('local:vztmpl/ubuntu-24.04.tar.zst', FakeHttp::form($creates[1])['ostemplate']);
        self::assertSame(FakeHttp::form($creates[0])['vmid'], FakeHttp::form($creates[1])['vmid'], 'Same VMID after reinstall.');
        self::assertStringContainsString('ip=203.0.113.18/29', FakeHttp::form($creates[1])['net0'], 'Same address after reinstall.');
        self::assertNotSame($first->credentials['password'], $second->credentials['password']);
        self::assertCount(1, $this->http->requests('DELETE', '/api2/json/nodes/pve1/lxc/\d+'));
    }

    public function testConsoleSessionsAreSingleUseAndStoredEncrypted(): void
    {
        $service = $this->createService($this->createUser(), 'vps', $this->integrationId, ['template_vmid' => 9000]);
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($service->id, ['node' => 'pve1', 'vmid' => 10600, 'type' => 'qemu', 'step' => 'done']);
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $sessions = $this->app->container()->get(\Nebrix\RemoteConsole\ConsoleSessions::class);
        $user = $this->app->container()->get(\Nebrix\Repository\UserRepository::class)->find($service->userId);
        self::assertNotNull($user);

        $session = $sessions->open($this->reload($service->id), $user, '198.51.100.9');
        self::assertSame('PVEVNC:6553F00D::secret-ticket', $session['password']);
        self::assertSame(1, preg_match('#/console/ws\?token=([a-f0-9]{64})$#', $session['url'], $match));

        $stored = (string) $this->db->fetchValue('SELECT target FROM nx_console_sessions WHERE service_id = ?', [$service->id]);
        self::assertStringNotContainsString('0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0', $stored, 'The API token is stored encrypted.');
        self::assertStringNotContainsString($match[1], (string) $this->db->fetchValue('SELECT token_hash FROM nx_console_sessions WHERE service_id = ?', [$service->id]));

        $target = $sessions->redeem($match[1]);
        self::assertNotNull($target);
        self::assertSame('wss', $target['scheme']);
        self::assertSame('203.0.113.10', $target['ip']);
        self::assertSame('/api2/json/nodes/pve1/qemu/10600/vncwebsocket?port=5901&vncticket=PVEVNC%3A6553F00D%3A%3Asecret-ticket', $target['path']);
        self::assertSame('PVEAPIToken=nebrix@pve!panel=0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0', $target['headers']['Authorization']);
        self::assertNull($sessions->redeem($match[1]), 'Tokens are single use.');

        // Expired tokens and tokens of suspended services are rejected.
        $second = $sessions->open($this->reload($service->id), $user, null);
        preg_match('#token=([a-f0-9]{64})#', $second['url'], $token2);
        $this->db->update('nx_services', ['status' => 'suspended'], ['id' => $service->id]);
        self::assertNull($sessions->redeem($token2[1]));
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $third = $sessions->open($this->reload($service->id), $user, null);
        preg_match('#token=([a-f0-9]{64})#', $third['url'], $token3);
        $this->clock()->freeze($this->clock()->now()->modify('+2 minutes'));
        self::assertNull($sessions->redeem($token3[1]));
        self::assertNull($sessions->redeem('not-a-token'));
    }

    public function testPasswordResetUsesTheGuestAgentOrCloudInit(): void
    {
        $service = $this->createService($this->createUser(), 'vps', $this->integrationId, ['template_vmid' => 9000]);
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($service->id, ['node' => 'pve1', 'vmid' => 10500, 'type' => 'qemu', 'step' => 'done']);
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $runner = $this->app->container()->get(ServiceActionRunner::class);
        $repository = $this->app->container()->get(ServiceRepository::class);

        $message = $runner->run($this->reload($service->id), 'reset_password', [], null, null)['message'];
        self::assertStringContainsString('has been set', $message);
        $password = (string) $repository->credentials($this->reload($service->id))['password'];
        self::assertSame($password, FakeHttp::form($this->http->requests('POST', '/api2/json/nodes/pve1/qemu/10500/agent/set-user-password')[0])['password']);

        $this->agentRunning = false;
        try {
            $runner->run($this->reload($service->id), 'reset_password', [], null, null);
            self::fail('Password reset accepted without guest agent and cloud-init drive.');
        } catch (IntegrationException $exception) {
            self::assertStringContainsString('no cloud-init drive', $exception->getMessage());
        }
        self::assertSame([], $this->http->requests('POST', '/api2/json/nodes/pve1/qemu/10500/config'), 'No cipassword is set on a VM without cloud-init.');
        self::assertSame($password, (string) $repository->credentials($this->reload($service->id))['password'], 'Stored credentials are unchanged.');

        $this->guests[10500] = ['name' => 'vm10500', 'status' => 'running', 'description' => 'nebrix-service-' . $service->id, 'type' => 'qemu', 'config' => ['ide2' => 'local-lvm:vm-10500-cloudinit,media=cdrom']];
        $message = $runner->run($this->reload($service->id), 'reset_password', [], null, null)['message'];
        self::assertStringContainsString('next restart', $message);
        $fallback = FakeHttp::form($this->http->requests('POST', '/api2/json/nodes/pve1/qemu/10500/config')[0]);
        self::assertSame((string) $repository->credentials($this->reload($service->id))['password'], $fallback['cipassword']);
        self::assertNotSame($password, $fallback['cipassword']);
    }

    public function testLegacyGuestIsOnlyDeletedWhileItKeepsItsRecordedHostname(): void
    {
        $service = $this->createService($this->createUser(), 'lxc', $this->integrationId, ['_options' => ['hostname' => 'legacy01']]);
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($service->id, ['node' => 'pve1', 'vmid' => 333, 'type' => 'lxc', 'legacy' => true, 'step' => 'done']);
        $provisioner = $this->app->container()->get(ProxmoxProvisioner::class);

        // The legacy VMID was reused for another guest (or claimed by a Nebrix service).
        foreach ([['reused-by-admin', ''], ['legacy01', "nebrix-service-999"]] as [$name, $description]) {
            $this->guests[333] = ['name' => $name, 'status' => 'stopped', 'description' => $description, 'type' => 'lxc'];
            try {
                $provisioner->terminate($this->reload($service->id));
                self::fail('Foreign guest deleted: ' . $name);
            } catch (IntegrationException $exception) {
                self::assertStringContainsString('was not deleted', $exception->getMessage());
            }
        }
        self::assertSame([], $this->http->requests('DELETE', '/api2/json/nodes/pve1/lxc/333'));

        $this->guests[333] = ['name' => 'legacy01', 'status' => 'stopped', 'description' => '', 'type' => 'lxc'];
        try {
            $provisioner->terminate($this->reload($service->id));
            self::fail('Expected RetryLater while Proxmox deletes the guest.');
        } catch (RetryLaterException) {
        }
        self::assertCount(1, $this->http->requests('DELETE', '/api2/json/nodes/pve1/lxc/333'));
    }
}
