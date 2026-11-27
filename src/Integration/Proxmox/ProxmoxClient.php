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
