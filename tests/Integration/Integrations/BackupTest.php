<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration\Integrations;

use GuzzleHttp\Psr7\Response;
use Nebrix\Integration\IntegrationException;
use Nebrix\Provisioning\PterodactylProvisioner;
use Nebrix\Provisioning\ProxmoxProvisioner;
use Nebrix\Provisioning\RetryLaterException;
use Nebrix\Provisioning\ServiceActionRunner;
use Nebrix\Provisioning\ServiceStateException;
use Nebrix\Security\ValidationException;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Nebrix\Tests\Support\FakeHttp;

/**
 * Customer backups on Proxmox (vzdump, restore through the provisioning
 * queue) and Pterodactyl (Client API) against fake APIs.
 */
final class BackupTest extends IntegrationTestCase
{
    /** @var array<int, array{name: string, status: string, description: string, type: string}> */
    private array $guests = [];

    /** @var array<string, string> */
    private array $tasks = [];

    /** @var list<array{volid: string, vmid: int, ctime: int, size: int, notes: string}> */
    private array $backups = [];

    /** @var list<array<string, mixed>> */
    private array $pteroBackups = [];

    private function proxmox(): int
    {
        $id = $this->createIntegration('proxmox', ['base_url' => 'https://203.0.113.10:8006', 'token_id' => 'nebrix@pve!panel', 'verify_tls' => true], ['token_secret' => '0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0']);
        $json = static fn (mixed $data): Response => new Response(200, [], json_encode(['data' => $data], JSON_THROW_ON_ERROR));
        $task = function (string $kind, int $vmid): string {
            $upid = 'UPID:pve1:00000001:00000002:6553F00D:' . $kind . ':' . $vmid . ':nebrix@pve!panel:';
            $this->tasks[$upid] = 'running';

            return $upid;
        };

        $this->http
            ->on('GET', '/api2/json/cluster/resources', fn (): Response => $json(array_map(static fn (int $vmid, array $g): array => ['vmid' => $vmid, 'name' => $g['name'], 'status' => $g['status'], 'node' => 'pve1', 'type' => $g['type']], array_keys($this->guests), $this->guests)))
            ->on('GET', '/api2/json/nodes/pve1/(?<type>lxc|qemu)/(?<vmid>\d+)/config', fn ($r, array $m): Response => $json(['description' => $this->guests[(int) $m['vmid']]['description'] ?? '']))
            ->on('GET', '/api2/json/nodes/pve1/(?<type>lxc|qemu)/(?<vmid>\d+)/status/current', fn ($r, array $m): Response => $json(['status' => $this->guests[(int) $m['vmid']]['status'] ?? 'stopped']))
            ->on('POST', '/api2/json/nodes/pve1/(?<type>lxc|qemu)/(?<vmid>\d+)/status/(?<action>\w+)', function ($r, array $m) use ($json, $task): Response {
                $this->guests[(int) $m['vmid']]['status'] = in_array($m['action'], ['stop', 'shutdown'], true) ? 'stopped' : 'running';

                return $json($task('vz' . $m['action'], (int) $m['vmid']));
            })
            ->on('GET', '/api2/json/nodes/pve1/tasks/(?<upid>[^/]+)/status', function ($r, array $m) use ($json): Response {
                $done = ($this->tasks[rawurldecode($m['upid'])] ?? '') === 'done';

                return $json(['status' => $done ? 'stopped' : 'running', 'exitstatus' => $done ? 'OK' : null]);
            })
            ->on('POST', '/api2/json/nodes/pve1/vzdump', function ($request) use ($json, $task): Response {
                $form = FakeHttp::form($request);
                $this->backups[] = ['volid' => 'backup-nfs:backup/vzdump-lxc-' . $form['vmid'] . '-' . count($this->backups) . '.tar.zst', 'vmid' => (int) $form['vmid'], 'ctime' => time(), 'size' => 734003200, 'notes' => $form['notes-template']];

                return $json($task('vzdump', (int) $form['vmid']));
            })
            ->on('GET', '/api2/json/nodes/pve1/storage/backup-nfs/content', function ($request) use ($json): Response {
                parse_str($request->getUri()->getQuery(), $query);

                return $json(array_values(array_filter($this->backups, static fn (array $b): bool => $b['vmid'] === (int) $query['vmid'] && $query['content'] === 'backup')));
            })
            ->on('DELETE', '/api2/json/nodes/pve1/storage/backup-nfs/content/(?<volid>[^/]+)', function ($r, array $m) use ($json): Response {
                $volid = rawurldecode($m['volid']);
                $this->backups = array_values(array_filter($this->backups, static fn (array $b): bool => $b['volid'] !== $volid));

                return $json(null);
            })
            ->on('POST', '/api2/json/nodes/pve1/lxc', function ($request) use ($json, $task): Response {
                return $json($task('vzrestore', (int) FakeHttp::form($request)['vmid']));
            })
            ->on('DELETE', '/api2/json/nodes/pve1/(?<type>lxc|qemu)/(?<vmid>\d+)', fn ($r, array $m): Response => $json($task('vzdestroy', (int) $m['vmid'])));

        return $id;
    }

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

    /**
     * An active LXC container (VMID 20000 + ID) with customer backups.
     *
     * @param array<string, mixed> $config
     */
    private function container(int $integrationId, array $config = []): Service
    {
        $service = $this->createService($this->createUser(), 'lxc', $integrationId, $config + [
            'ostemplate' => 'local:vztmpl/debian-12.tar.zst', 'storage' => 'local-lvm', 'node' => 'pve1', 'vmid_start' => 20000,
            'backup_storage' => 'backup-nfs', 'max_backups' => 2,
        ]);
        $vmid = 20000 + $service->id;
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($service->id, ['node' => 'pve1', 'vmid' => $vmid, 'type' => 'lxc', 'step' => 'done']);
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $this->guests[$vmid] = ['name' => 'web1', 'status' => 'running', 'description' => 'nebrix-service-' . $service->id, 'type' => 'lxc'];

        return $this->reload($service->id);
    }

    public function testProxmoxBackupsAreLimitedToTheServicesOwnBackups(): void
    {
        $integrationId = $this->proxmox();
        $runner = $this->app->container()->get(ServiceActionRunner::class);

        $disabled = $this->container($integrationId, ['max_backups' => 0]);
        self::assertArrayNotHasKey('backup_create', $runner->available($disabled));

        // An unreachable backup storage hides only the backup actions.
        $broken = $this->container($integrationId, ['backup_storage' => 'offline-pbs']);
        $actions = $runner->available($broken);
        self::assertArrayHasKey('start', $actions);
        self::assertArrayNotHasKey('backup_create', $actions);

        $service = $this->container($integrationId);
        $vmid = 20000 + $service->id;
        $created = (int) strtotime((string) $service->attributes['created_at'] . ' UTC');
        // Same VMID, but not this service's backups: an earlier guest, an admin job, another storage.
        $this->backups = [
            ['volid' => 'backup-nfs:backup/vzdump-lxc-' . $vmid . '-old.tar.zst', 'vmid' => $vmid, 'ctime' => $created - 86400, 'size' => 1, 'notes' => 'nebrix-service-' . $service->id],
            ['volid' => 'backup-nfs:backup/vzdump-lxc-' . $vmid . '-admin.tar.zst', 'vmid' => $vmid, 'ctime' => $created + 60, 'size' => 1, 'notes' => 'weekly job'],
            ['volid' => 'other:backup/vzdump-lxc-' . $vmid . '-foreign.tar.zst', 'vmid' => $vmid, 'ctime' => $created + 60, 'size' => 1, 'notes' => 'nebrix-service-' . $service->id],
        ];

        $actions = $runner->available($service);
        self::assertArrayHasKey('backup_create', $actions);
        self::assertArrayNotHasKey('backup_restore', $actions, 'Foreign backups are never offered.');

        self::assertStringContainsString('has been started', $runner->run($service, 'backup_create', [], null, null)['message']);
        $dump = FakeHttp::form($this->http->requests('POST', '/api2/json/nodes/pve1/vzdump')[0]);
        self::assertSame(['vmid' => (string) $vmid, 'storage' => 'backup-nfs', 'mode' => 'snapshot', 'compress' => 'zstd', 'notes-template' => 'nebrix-service-' . $service->id], $dump);

        try {
            $runner->run($this->reload($service->id), 'backup_create', [], null, null);
            self::fail('A second backup was started while the first one is running.');
        } catch (ServiceStateException $exception) {
            self::assertStringContainsString('already running', $exception->getMessage());
        }
        $this->finishTasks();
        $runner->run($this->reload($service->id), 'backup_create', [], null, null);
        $this->finishTasks();

        $actions = $runner->available($this->reload($service->id));
        $options = $actions['backup_restore']->fields['backup']['options'] ?? [];
        self::assertCount(2, $options);
        self::assertStringContainsString('(700 MB)', (string) reset($options));
        try {
            $runner->run($this->reload($service->id), 'backup_create', [], null, null);
            self::fail('The backup limit of the product was exceeded.');
        } catch (ServiceStateException $exception) {
            self::assertStringContainsString('maximum number', $exception->getMessage());
        }

        // Foreign backups can be neither restored nor deleted.
        foreach (['backup_restore', 'backup_delete'] as $action) {
            try {
                $runner->run($this->reload($service->id), $action, ['backup' => 'backup-nfs:backup/vzdump-lxc-' . $vmid . '-admin.tar.zst'], null, null);
                self::fail($action . ' accepted a foreign backup.');
            } catch (ValidationException) {
            }
        }
        try {
            $this->app->container()->get(ProxmoxProvisioner::class)->perform($this->reload($service->id), 'backup_delete', ['backup' => 'backup-nfs:backup/vzdump-lxc-' . $vmid . '-old.tar.zst']);
            self::fail('A backup of an earlier guest was deleted.');
        } catch (ServiceStateException) {
        }

        $own = (string) array_key_last($options);
        self::assertSame('The backup has been deleted.', $runner->run($this->reload($service->id), 'backup_delete', ['backup' => $own], null, null)['message']);
        self::assertSame('/api2/json/nodes/pve1/storage/backup-nfs/content/' . rawurlencode($own), $this->http->requests('DELETE', '/api2/json/nodes/pve1/storage/backup-nfs/content/.+')[0]->getUri()->getPath());
        self::assertCount(4, $this->backups);
    }

    public function testProxmoxRestoreRunsThroughTheProvisioningQueue(): void
    {
        $integrationId = $this->proxmox();
        $service = $this->container($integrationId);
        $vmid = 20000 + $service->id;
        $runner = $this->app->container()->get(ServiceActionRunner::class);
        $runner->run($service, 'backup_create', [], null, null);
        $this->finishTasks();
        $volid = $this->backups[0]['volid'];
        $credentials = $this->app->container()->get(ServiceRepository::class)->credentials($this->reload($service->id));

        $result = $runner->run($this->reload($service->id), 'backup_restore', ['backup' => $volid], null, null);
        self::assertStringContainsString('being restored', $result['message']);
        self::assertSame('provisioning', $this->reload($service->id)->status);
        self::assertSame('restore', $this->reload($service->id)->remote['step']);

        $provisioner = $this->app->container()->get(ProxmoxProvisioner::class);
        $owner = $this->app->container()->get(\Nebrix\Repository\UserRepository::class)->find($service->userId);
        self::assertNotNull($owner);
        $done = null;
        for ($i = 0; $i < 10 && $done === null; $i++) {
            try {
                $done = $provisioner->provision($this->reload($service->id), $owner);
            } catch (RetryLaterException) {
                $this->finishTasks();
            }
        }
        self::assertNotNull($done);
        self::assertSame('done', $done->remote['step']);

        $restore = FakeHttp::form($this->http->requests('POST', '/api2/json/nodes/pve1/lxc')[0]);
        self::assertSame(['vmid' => (string) $vmid, 'ostemplate' => $volid, 'restore' => '1', 'force' => '1', 'storage' => 'local-lvm'], $restore);
        $power = array_map(static fn ($r): string => $r->getUri()->getPath(), $this->http->requests('POST', '/api2/json/nodes/pve1/lxc/\d+/status/\w+'));
        self::assertSame(['/api2/json/nodes/pve1/lxc/' . $vmid . '/status/stop', '/api2/json/nodes/pve1/lxc/' . $vmid . '/status/start'], $power, 'Stopped before and started after the restore.');
        self::assertSame([], $this->http->requests('DELETE', '/api2/json/nodes/pve1/lxc/\d+'), 'The guest is restored in place, not deleted.');
        self::assertSame($credentials, $done->credentials, 'The stored access data is kept.');

        // A backup that disappeared before the job ran: nothing is touched.
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $runner->run($this->reload($service->id), 'backup_restore', ['backup' => $volid], null, null);
        $this->backups = [];
        try {
            $provisioner->provision($this->reload($service->id), $owner);
            self::fail('A missing backup was restored.');
        } catch (IntegrationException $exception) {
            self::assertStringContainsString('nothing was restored', $exception->getMessage());
        }
        self::assertCount(1, $this->http->requests('POST', '/api2/json/nodes/pve1/lxc'));
        self::assertSame('done', $provisioner->provision($this->reload($service->id), $owner)->remote['step'], 'A retry re-activates the service unchanged.');
    }

    public function testProxmoxTerminationDeletesTheServicesBackups(): void
    {
        $integrationId = $this->proxmox();
        $service = $this->container($integrationId);
        $vmid = 20000 + $service->id;
        $this->app->container()->get(ServiceActionRunner::class)->run($service, 'backup_create', [], null, null);
        $this->finishTasks();
        $this->backups[] = ['volid' => 'backup-nfs:backup/vzdump-lxc-' . $vmid . '-admin.tar.zst', 'vmid' => $vmid, 'ctime' => time(), 'size' => 1, 'notes' => 'weekly job'];

        $provisioner = $this->app->container()->get(ProxmoxProvisioner::class);
        for ($i = 0; $i < 6; $i++) {
            try {
                $provisioner->terminate($this->reload($service->id));
                break;
            } catch (RetryLaterException) {
                $this->finishTasks();
            }
        }
        self::assertArrayNotHasKey($vmid, $this->guests);
        self::assertSame(['backup-nfs:backup/vzdump-lxc-' . $vmid . '-admin.tar.zst'], array_column($this->backups, 'volid'), 'Only the customer backups are deleted.');
    }

    public function testPterodactylBackups(): void
    {
        $integrationId = $this->createIntegration('pterodactyl', ['base_url' => 'https://203.0.113.20', 'verify_tls' => true], ['application_api_key' => 'ptla_' . str_repeat('a', 40), 'client_api_key' => 'ptlc_' . str_repeat('b', 40)]);
        $base = '/api/client/servers/1a7ce997/backups';
        $uuid = static fn (int $n): string => sprintf('%08d-259b-452e-8b4e-cecc464142ca', $n);
        $this->pteroBackups = [
            ['uuid' => $uuid(1), 'name' => 'Before update', 'bytes' => 2147483648, 'created_at' => '2026-11-20T10:00:00+00:00', 'completed_at' => '2026-11-20T10:05:00+00:00', 'is_successful' => true, 'is_locked' => false],
            ['uuid' => $uuid(2), 'name' => 'Running', 'bytes' => 0, 'created_at' => '2026-11-27T10:00:00+00:00', 'completed_at' => null, 'is_successful' => false, 'is_locked' => false],
        ];
        $this->http
            ->on('GET', $base, fn (): Response => new Response(200, [], json_encode(['object' => 'list', 'data' => array_map(static fn (array $b): array => ['object' => 'backup', 'attributes' => $b], $this->pteroBackups)], JSON_THROW_ON_ERROR)))
            ->on('POST', $base, function ($request) use ($uuid): Response {
                $this->pteroBackups[] = ['uuid' => $uuid(10 + count($this->pteroBackups)), 'name' => FakeHttp::json($request)['name'], 'bytes' => 0, 'created_at' => '2026-11-27T12:00:00+00:00', 'completed_at' => null, 'is_successful' => false, 'is_locked' => false];

                return new Response(200, [], json_encode(['object' => 'backup', 'attributes' => end($this->pteroBackups)], JSON_THROW_ON_ERROR));
            })
            ->on('POST', $base . '/(?<uuid>[a-f0-9-]{36})/restore', [], 204)
            ->on('GET', $base . '/(?<uuid>[a-f0-9-]{36})/download', ['object' => 'signed_url', 'attributes' => ['url' => 'https://node1.example.test:8080/download/backup?token=signed']])
            ->on('DELETE', $base . '/(?<uuid>[a-f0-9-]{36})', [], 204);

        $service = $this->createService($this->createUser(), 'minecraft', $integrationId, ['memory_mb' => 1024, 'disk_mb' => 1024, 'backups' => 3]);
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($service->id, ['server_id' => 900, 'identifier' => '1a7ce997']);
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $runner = $this->app->container()->get(ServiceActionRunner::class);

        $actions = $runner->available($this->reload($service->id));
        self::assertSame([$uuid(1)], array_keys($actions['backup_restore']->fields['backup']['options'] ?? []), 'Only finished backups are offered.');

        $runner->run($this->reload($service->id), 'backup_create', [], null, null);
        self::assertStringStartsWith('Backup ', FakeHttp::json($this->http->requests('POST', $base)[0])['name']);
        try {
            $runner->run($this->reload($service->id), 'backup_create', [], null, null);
            self::fail('The backup limit was exceeded.');
        } catch (ServiceStateException $exception) {
            self::assertStringContainsString('maximum number', $exception->getMessage());
        }
        self::assertCount(1, $this->http->requests('POST', $base));

        self::assertSame('https://node1.example.test:8080/download/backup?token=signed', $runner->run($this->reload($service->id), 'backup_download', ['backup' => $uuid(1)], null, '198.51.100.7')['redirect']);
        $runner->run($this->reload($service->id), 'backup_restore', ['backup' => $uuid(1)], null, null);
        self::assertSame(['truncate' => true], FakeHttp::json($this->http->requests('POST', $base . '/[a-f0-9-]{36}/restore')[0]));

        // A backup that is still running (or of another server) is rejected.
        try {
            $this->app->container()->get(PterodactylProvisioner::class)->perform($this->reload($service->id), 'backup_delete', ['backup' => $uuid(2)]);
            self::fail('An unfinished backup was deleted.');
        } catch (ServiceStateException) {
        }
        $runner->run($this->reload($service->id), 'backup_delete', ['backup' => $uuid(1)], null, null);
        self::assertSame($base . '/' . $uuid(1), $this->http->requests('DELETE', $base . '/.+')[0]->getUri()->getPath());
    }
}
