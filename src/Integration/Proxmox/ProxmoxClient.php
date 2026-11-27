<?php

declare(strict_types=1);

namespace Nebrix\Integration\Proxmox;

use Nebrix\Integration\HealthResult;
use Nebrix\Integration\Http\ApiClient;
use Nebrix\Integration\IntegrationException;

/**
 * Proxmox VE REST API (https://pve.proxmox.com/pve-docs/api-viewer/) with
 * API-token authentication. Only the endpoints Nebrix needs are wrapped,
 * and every path segment is validated so that no request can be redirected
 * to another endpoint through crafted node names or IDs.
 */
class ProxmoxClient
{
    public const TYPE_QEMU = 'qemu';
    public const TYPE_LXC = 'lxc';

    private const POWER_ACTIONS = ['start', 'stop', 'shutdown', 'reboot'];

    public function __construct(private readonly ApiClient $api)
    {
    }

    public function healthCheck(): HealthResult
    {
        try {
            $version = $this->version();
            $nodes = $this->nodes();
        } catch (IntegrationException $exception) {
            return HealthResult::down($exception->getMessage());
        }

        $online = array_filter($nodes, static fn (array $node): bool => ($node['status'] ?? '') === 'online');
        $message = sprintf('Proxmox VE %s, %d/%d node(s) online', $version, count($online), count($nodes));

        if ($nodes === [] || count($online) === 0) {
            return HealthResult::down($message);
        }

        return count($online) < count($nodes)
            ? HealthResult::degraded($message, ['nodes' => $nodes])
            : HealthResult::healthy($message, ['nodes' => $nodes]);
    }

    public function version(): string
    {
        $data = $this->data($this->api->request('GET', '/api2/json/version', ['action' => 'version']));

        return is_array($data) ? (string) ($data['version'] ?? 'unknown') : 'unknown';
    }

    /**
     * @return list<array{node: string, status: string, cpu: float, maxcpu: int, mem: int, maxmem: int, disk: int, maxdisk: int, uptime: int}>
     */
    public function nodes(): array
    {
        $data = $this->data($this->api->request('GET', '/api2/json/nodes', ['action' => 'nodes.list']));
        $nodes = [];
        foreach (is_array($data) ? $data : [] as $node) {
            if (!is_array($node) || !isset($node['node'])) {
                continue;
            }
            $nodes[] = [
                'node' => (string) $node['node'],
                'status' => (string) ($node['status'] ?? 'unknown'),
                'cpu' => (float) ($node['cpu'] ?? 0),
                'maxcpu' => (int) ($node['maxcpu'] ?? 0),
                'mem' => (int) ($node['mem'] ?? 0),
                'maxmem' => (int) ($node['maxmem'] ?? 0),
                'disk' => (int) ($node['disk'] ?? 0),
                'maxdisk' => (int) ($node['maxdisk'] ?? 0),
                'uptime' => (int) ($node['uptime'] ?? 0),
            ];
        }

        return $nodes;
    }

    /** Online node with the most free memory. */
    public function pickNode(): string
    {
        $best = null;
        foreach ($this->nodes() as $node) {
            if ($node['status'] !== 'online') {
                continue;
            }
            $free = $node['maxmem'] - $node['mem'];
            if ($best === null || $free > $best[1]) {
                $best = [$node['node'], $free];
            }
        }

        if ($best === null) {
            throw new IntegrationException('No Proxmox node is online.');
        }

        return $best[0];
    }

    /**
     * Guests (VMs and containers) across the cluster.
     *
     * @return list<array<string, mixed>>
     */
    public function guests(): array
    {
        $data = $this->data($this->api->request('GET', '/api2/json/cluster/resources', ['query' => ['type' => 'vm'], 'action' => 'cluster.resources']));

        return array_values(array_filter(is_array($data) ? $data : [], 'is_array'));
    }

    /**
     * @return array<string, mixed>|null null when the guest does not exist
     */
    public function findGuest(int $vmid): ?array
    {
        foreach ($this->guests() as $guest) {
            if ((int) ($guest['vmid'] ?? 0) === $vmid) {
                return $guest;
            }
        }

        return null;
    }

    /**
     * Current configuration of a guest (includes its description).
     *
     * @return array<string, mixed>
     */
    public function guestConfig(string $node, string $type, int $vmid): array
    {
        $data = $this->data($this->api->request('GET', $this->guestPath($node, $type, $vmid) . '/config', ['action' => $type . '.config.read']));

        return is_array($data) ? $data : [];
    }

    /**
     * Sets a user password inside a running VM through the QEMU guest agent.
     */
    public function setUserPassword(string $node, int $vmid, string $username, #[\SensitiveParameter] string $password): void
    {
        if (preg_match('/^[a-z_][a-z0-9_-]{0,31}$/', $username) !== 1) {
            throw new IntegrationException('Invalid user name.');
        }
        $this->api->request('POST', $this->guestPath($node, self::TYPE_QEMU, $vmid) . '/agent/set-user-password', [
            'form_params' => ['username' => $username, 'password' => $password],
            'action' => 'qemu.agent.set_password',
        ]);
    }

    /**
     * Opens a VNC proxy for the guest's console.
     *
     * @return array{port: int, ticket: string}
     */
    public function vncProxy(string $node, string $type, int $vmid): array
    {
        $data = $this->data($this->api->request('POST', $this->guestPath($node, $type, $vmid) . '/vncproxy', ['form_params' => ['websocket' => 1], 'action' => $type . '.vncproxy']));
        if (!is_array($data) || !isset($data['port'], $data['ticket']) || !is_numeric($data['port'])) {
            throw new IntegrationException('Proxmox did not return a console ticket.');
        }

        return ['port' => (int) $data['port'], 'ticket' => (string) $data['ticket']];
    }

    /**
     * Upstream WebSocket target for the console proxy (API token header,
     * pinned address and TLS settings of the integration).
     *
     * @return array{scheme: string, host: string, ip: string, port: int, path: string, headers: array<string, string>, verify_tls: bool}
     */
    public function vncWebSocketTarget(string $node, string $type, int $vmid, int $port, string $ticket): array
    {
        return $this->api->webSocketTarget($this->guestPath($node, $type, $vmid) . '/vncwebsocket?' . http_build_query(['port' => $port, 'vncticket' => $ticket], '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * @param array<string, scalar> $params
     * @return string task UPID
     */
    public function createContainer(string $node, array $params): string
    {
        return $this->upid($this->api->request('POST', $this->nodePath($node) . '/lxc', ['form_params' => $params, 'action' => 'lxc.create']));
    }

    /**
     * @param array<string, scalar> $params
     * @return string task UPID
     */
    public function cloneVm(string $node, int $templateVmid, array $params): string
    {
        return $this->upid($this->api->request('POST', $this->nodePath($node) . '/qemu/' . $this->vmid($templateVmid) . '/clone', ['form_params' => $params, 'action' => 'qemu.clone']));
    }

    /**
     * @param array<string, scalar> $params
     */
    public function updateVmConfig(string $node, int $vmid, array $params): void
    {
        $this->api->request('POST', $this->guestPath($node, self::TYPE_QEMU, $vmid) . '/config', ['form_params' => $params, 'action' => 'qemu.config']);
    }

    public function resizeDisk(string $node, string $type, int $vmid, string $disk, int $sizeGb): void
    {
        if (preg_match('/^((scsi|virtio|sata|ide)\d+|rootfs)$/', $disk) !== 1) {
            throw new IntegrationException('Invalid disk name.');
        }

        $this->api->request('PUT', $this->guestPath($node, $type, $vmid) . '/resize', ['form_params' => ['disk' => $disk, 'size' => $sizeGb . 'G'], 'action' => $type . '.resize']);
    }

    /**
     * @return string task UPID
     */
    public function power(string $node, string $type, int $vmid, string $action): string
    {
        if (!in_array($action, self::POWER_ACTIONS, true)) {
            throw new IntegrationException('Unsupported power action.');
        }

        return $this->upid($this->api->request('POST', $this->guestPath($node, $type, $vmid) . '/status/' . $action, ['action' => $type . '.' . $action]));
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $node, string $type, int $vmid): array
    {
        $data = $this->data($this->api->request('GET', $this->guestPath($node, $type, $vmid) . '/status/current', ['action' => $type . '.status']));

        return is_array($data) ? $data : [];
    }

    /**
     * @return string task UPID
     */
    public function destroy(string $node, string $type, int $vmid): string
    {
        return $this->upid($this->api->request('DELETE', $this->guestPath($node, $type, $vmid), [
            'query' => ['purge' => 1, 'destroy-unreferenced-disks' => 1],
            'action' => $type . '.destroy',
        ]));
    }

    /**
     * @return array{status: string, exitstatus: ?string}
     */
    public function taskStatus(string $node, string $upid): array
    {
        if (preg_match('/^UPID:[A-Za-z0-9._-]+:[0-9A-Fa-f:]+[A-Za-z0-9@!._:-]*$/', $upid) !== 1) {
            throw new IntegrationException('Invalid task identifier.');
        }

        $data = $this->data($this->api->request('GET', $this->nodePath($node) . '/tasks/' . rawurlencode($upid) . '/status', ['action' => 'task.status']));

        return [
            'status' => is_array($data) ? (string) ($data['status'] ?? 'unknown') : 'unknown',
            'exitstatus' => is_array($data) && isset($data['exitstatus']) ? (string) $data['exitstatus'] : null,
        ];
    }

    /**
     * Starts a backup (vzdump, snapshot mode) of a guest on a backup storage.
     * The notes identify the owning service.
     *
     * @return string task UPID
     */
    public function createBackup(string $node, int $vmid, string $storage, string $notes): string
    {
        return $this->upid($this->api->request('POST', $this->nodePath($node) . '/vzdump', [
            'form_params' => ['vmid' => $this->vmid($vmid), 'storage' => $this->storage($storage), 'mode' => 'snapshot', 'compress' => 'zstd', 'notes-template' => $notes],
            'action' => 'vzdump.create',
        ]));
    }

    /**
     * Backups of one guest on a storage.
     *
     * @return list<array{volid: string, ctime: int, size: int, notes: string}>
     */
    public function backups(string $node, string $storage, int $vmid): array
    {
        $data = $this->data($this->api->request('GET', $this->nodePath($node) . '/storage/' . $this->storage($storage) . '/content', [
            'query' => ['content' => 'backup', 'vmid' => $this->vmid($vmid)],
            'action' => 'storage.backups',
        ]));
        $backups = [];
        foreach (is_array($data) ? $data : [] as $entry) {
            if (!is_array($entry) || (int) ($entry['vmid'] ?? 0) !== $vmid || !is_string($entry['volid'] ?? null) || !self::isBackupVolume($entry['volid'], $storage)) {
                continue;
            }
            $backups[] = ['volid' => $entry['volid'], 'ctime' => (int) ($entry['ctime'] ?? 0), 'size' => (int) ($entry['size'] ?? 0), 'notes' => (string) ($entry['notes'] ?? '')];
        }

        return $backups;
    }

    public function deleteBackup(string $node, string $storage, string $volid): void
    {
        if (!self::isBackupVolume($volid, $storage)) {
            throw new IntegrationException('Invalid backup volume.');
        }
        $this->api->request('DELETE', $this->nodePath($node) . '/storage/' . $this->storage($storage) . '/content/' . rawurlencode($volid), ['action' => 'storage.backup_delete']);
    }

    /**
     * Restores a backup over the existing guest (same VMID, force).
     *
     * @return string task UPID
     */
    public function restoreBackup(string $node, string $type, int $vmid, string $volid, string $storage, string $targetStorage): string
    {
        if (!self::isBackupVolume($volid, $storage)) {
            throw new IntegrationException('Invalid backup volume.');
        }
        if (!in_array($type, [self::TYPE_QEMU, self::TYPE_LXC], true)) {
            throw new IntegrationException('Invalid guest type.');
        }
        $params = $type === self::TYPE_LXC
            ? ['vmid' => $this->vmid($vmid), 'ostemplate' => $volid, 'restore' => 1, 'force' => 1, 'storage' => $this->storage($targetStorage)]
            : ['vmid' => $this->vmid($vmid), 'archive' => $volid, 'force' => 1, 'storage' => $this->storage($targetStorage)];

        return $this->upid($this->api->request('POST', $this->nodePath($node) . '/' . $type, ['form_params' => $params, 'action' => $type . '.restore']));
    }

    public static function isBackupVolume(string $volid, string $storage): bool
    {
        return str_starts_with($volid, $storage . ':backup/') && preg_match('#^[A-Za-z0-9._-]+:backup/[A-Za-z0-9._/:+-]{1,200}$#', $volid) === 1 && !str_contains($volid, '..');
    }

    private function storage(string $storage): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $storage) !== 1) {
            throw new IntegrationException('Invalid storage name.');
        }

        return $storage;
    }

    private function nodePath(string $node): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,62}$/', $node) !== 1) {
            throw new IntegrationException('Invalid node name.');
        }

        return '/api2/json/nodes/' . $node;
    }

    private function guestPath(string $node, string $type, int $vmid): string
    {
        if (!in_array($type, [self::TYPE_QEMU, self::TYPE_LXC], true)) {
            throw new IntegrationException('Invalid guest type.');
        }

        return $this->nodePath($node) . '/' . $type . '/' . $this->vmid($vmid);
    }

    private function vmid(int $vmid): int
    {
        if ($vmid < 100 || $vmid > 999_999_999) {
            throw new IntegrationException('Invalid VM ID.');
        }

        return $vmid;
    }

    /**
     * @param array{status: int, json: mixed, body: string} $response
     */
    private function data(array $response): mixed
    {
        return is_array($response['json']) ? ($response['json']['data'] ?? null) : null;
    }

    /**
     * @param array{status: int, json: mixed, body: string} $response
     */
    private function upid(array $response): string
    {
        $data = $this->data($response);
        if (!is_string($data) || !str_starts_with($data, 'UPID:')) {
            throw new IntegrationException('Proxmox did not return a task ID.');
        }

        return $data;
    }
}
