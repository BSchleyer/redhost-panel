<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Auth\User;
use Nebrix\Catalog\ProductCategory;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\Proxmox\ProxmoxClient;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;

/**
 * Provisions KVM virtual machines (clone of a cloud-init template) and LXC
 * containers on Proxmox VE.
 *
 * Idempotency: the VMID is deterministic (vmid_start + service ID) and the
 * node, VMID, current step and task UPID are persisted before each remote
 * call. A retried job resumes the state machine instead of creating a second
 * guest; an existing guest with a different name is treated as a conflict.
 */
final class ProxmoxProvisioner implements Provisioner
{
    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly ServiceRepository $services,
    ) {
    }

    private function client(Service $service): ProxmoxClient
    {
        if ($service->integrationId === null) {
            throw new IntegrationException('No Proxmox integration is assigned to this service.');
        }

        return $this->integrations->proxmox($this->integrations->get($service->integrationId));
    }

    private function type(Service $service): string
    {
        return $service->category === ProductCategory::LXC ? ProxmoxClient::TYPE_LXC : ProxmoxClient::TYPE_QEMU;
    }

    private function hostname(Service $service): string
    {
        $hostname = (string) $service->option('hostname', 'nebrix-' . $service->id);

        return preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $hostname) === 1 ? $hostname : 'nebrix-' . $service->id;
    }

    public function provision(Service $service, User $owner): ProvisionResult
    {
        $client = $this->client($service);
        $type = $this->type($service);
        $remote = $service->remote;

        // 1. Fix node and VMID before anything is created remotely.
        if (!isset($remote['vmid'], $remote['node'])) {
            $configuredNode = (string) $service->config('node', '');
            $remote['node'] = $configuredNode !== '' ? $configuredNode : $client->pickNode();
            $remote['vmid'] = (int) $service->config('vmid_start', $type === ProxmoxClient::TYPE_LXC ? 20000 : 10000) + $service->id;
            $remote['type'] = $type;
            $remote['step'] = 'create';
            $this->services->mergeRemote($service->id, $remote);
        }

        $node = (string) $remote['node'];
        $vmid = (int) $remote['vmid'];
        $credentials = $this->services->credentials($service);

        // 2. Wait for a pending task of the current step.
        if (isset($remote['upid'])) {
            $this->awaitTask($client, $service->id, $node, (string) $remote['upid']);
            $remote = $this->advance($service->id, $remote);
        }

        // 3. Run the remaining steps.
        while (true) {
            $step = (string) ($remote['step'] ?? 'create');

            if ($step === 'create') {
                $existing = $client->findGuest($vmid);
                if ($existing !== null) {
                    if ((string) ($existing['name'] ?? '') !== $this->hostname($service)) {
                        throw new IntegrationException(sprintf('VM ID %d is already used by another guest ("%s"). Adjust "vmid_start" on the product.', $vmid, (string) ($existing['name'] ?? '?')));
                    }
                    // Created by an earlier attempt whose task completed.
                    $remote = $this->advance($service->id, $remote);
                    continue;
                }

                if (($credentials['password'] ?? '') === '') {
                    $credentials = ['username' => 'root', 'password' => self::generatePassword()];
                    $this->services->setCredentials($service->id, $credentials);
                }

                $upid = $type === ProxmoxClient::TYPE_LXC
                    ? $client->createContainer($node, $this->containerParameters($service, $vmid, (string) $credentials['password']))
                    : $client->cloneVm($node, (int) $service->config('template_vmid'), [
                        'newid' => $vmid,
                        'name' => $this->hostname($service),
                        'full' => 1,
                        'storage' => (string) $service->config('storage'),
                        'description' => 'nebrix-service-' . $service->id,
                    ]);
                $this->services->mergeRemote($service->id, ['upid' => $upid]);

                throw new RetryLaterException('Waiting for Proxmox to create the guest.', 10);
            }

            if ($step === 'configure') {
                $client->updateVmConfig($node, $vmid, $this->vmParameters($service, (string) ($credentials['password'] ?? '')));
                $client->resizeDisk($node, ProxmoxClient::TYPE_QEMU, $vmid, 'scsi0', (int) $service->config('disk_gb'));
                $remote = $this->advance($service->id, $remote);
                continue;
            }

            if ($step === 'start') {
                $upid = $client->power($node, ProxmoxClient::TYPE_QEMU, $vmid, 'start');
                $this->services->mergeRemote($service->id, ['upid' => $upid]);

                throw new RetryLaterException('Waiting for the virtual machine to start.', 5);
            }

            // step === 'done'
            return new ProvisionResult(
                sprintf('pve:%s:%d', $node, $vmid),
                ['node' => $node, 'vmid' => $vmid, 'type' => $type, 'step' => 'done'],
                $credentials,
            );
        }
    }

    /**
     * @param array<string, mixed> $remote
     * @return array<string, mixed>
     */
    private function advance(int $serviceId, array $remote): array
    {
        $order = ($remote['type'] ?? '') === ProxmoxClient::TYPE_LXC
            ? ['create' => 'done']
            : ['create' => 'configure', 'configure' => 'start', 'start' => 'done'];

        $remote['step'] = $order[(string) ($remote['step'] ?? 'create')] ?? 'done';
        unset($remote['upid']);
        $this->services->mergeRemote($serviceId, ['step' => $remote['step'], 'upid' => null]);

        return $remote;
    }

    private function awaitTask(ProxmoxClient $client, int $serviceId, string $node, string $upid): void
    {
        $task = $client->taskStatus($node, $upid);
        if ($task['status'] !== 'stopped') {
            throw new RetryLaterException('Proxmox task is still running.', 10);
        }
        if ($task['exitstatus'] !== 'OK') {
            // Forget the failed task so that a manual retry starts the step again.
            $this->services->mergeRemote($serviceId, ['upid' => null]);

            throw new IntegrationException('Proxmox task failed: ' . mb_substr((string) $task['exitstatus'], 0, 200));
        }
    }

    /**
     * @return array<string, scalar>
     */
    private function containerParameters(Service $service, int $vmid, string $password): array
    {
        $rate = (int) $service->config('rate_mbps', 0);

        return [
            'vmid' => $vmid,
            'hostname' => $this->hostname($service),
            'ostemplate' => (string) $service->config('ostemplate'),
            'cores' => (int) $service->config('cores', 1),
            'memory' => (int) $service->config('memory_mb', 1024),
            'swap' => (int) $service->config('swap_mb', 512),
            'rootfs' => sprintf('%s:%d', (string) $service->config('storage'), (int) $service->config('disk_gb', 10)),
            'net0' => sprintf('name=eth0,bridge=%s,ip=dhcp,ip6=auto%s', (string) $service->config('bridge', 'vmbr0'), $rate > 0 ? ',rate=' . $rate : ''),
            'password' => $password,
            'unprivileged' => $service->config('unprivileged', true) ? 1 : 0,
            'features' => 'nesting=1',
            'onboot' => 1,
            'start' => 1,
            'description' => 'nebrix-service-' . $service->id,
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function vmParameters(Service $service, string $password): array
    {
        $rate = (int) $service->config('rate_mbps', 0);

        return [
            'cores' => (int) $service->config('cores', 2),
            'memory' => (int) $service->config('memory_mb', 2048),
            'ciuser' => 'root',
            'cipassword' => $password,
            'ipconfig0' => 'ip=dhcp,ip6=auto',
            'net0' => sprintf('virtio,bridge=%s%s', (string) $service->config('bridge', 'vmbr0'), $rate > 0 ? ',rate=' . $rate : ''),
            'onboot' => 1,
        ];
    }

    public function suspend(Service $service): void
    {
        $this->powerIfState($service, 'running', 'stop');
    }

    public function unsuspend(Service $service): void
    {
        $this->powerIfState($service, 'stopped', 'start');
    }

    private function powerIfState(Service $service, string $state, string $action): void
    {
        [$node, $vmid, $type] = $this->guest($service);
        $client = $this->client($service);
        if ($client->findGuest($vmid) === null) {
            return;
        }
        if (($client->status($node, $type, $vmid)['status'] ?? '') === $state) {
            $client->power($node, $type, $vmid, $action);
        }
    }

    public function terminate(Service $service): void
    {
        if (!isset($service->remote['vmid'])) {
            return; // never created
        }

        [$node, $vmid, $type] = $this->guest($service);
        $client = $this->client($service);

        if (isset($service->remote['destroy_upid'])) {
            $task = $client->taskStatus($node, (string) $service->remote['destroy_upid']);
            if ($task['status'] !== 'stopped') {
                throw new RetryLaterException('Waiting for Proxmox to delete the guest.', 10);
            }
            if ($client->findGuest($vmid) === null) {
                $this->services->mergeRemote($service->id, ['destroy_upid' => null]);

                return;
            }
            $this->services->mergeRemote($service->id, ['destroy_upid' => null]);
        }

        if ($client->findGuest($vmid) === null) {
            return;
        }

        if (($client->status($node, $type, $vmid)['status'] ?? '') === 'running') {
            $client->power($node, $type, $vmid, 'stop');

            throw new RetryLaterException('Waiting for the guest to stop before deletion.', 10);
        }

        $upid = $client->destroy($node, $type, $vmid);
        $this->services->mergeRemote($service->id, ['destroy_upid' => $upid]);

        throw new RetryLaterException('Waiting for Proxmox to delete the guest.', 10);
    }

    public function status(Service $service): array
    {
        [$node, $vmid, $type] = $this->guest($service);
        $status = $this->client($service)->status($node, $type, $vmid);

        return [
            'state' => (string) ($status['status'] ?? 'unknown'),
            'uptime' => (int) ($status['uptime'] ?? 0),
            'cpu_percent' => round((float) ($status['cpu'] ?? 0) * 100, 1),
            'cpus' => (int) ($status['cpus'] ?? 0),
            'memory_used' => (int) ($status['mem'] ?? 0),
            'memory_total' => (int) ($status['maxmem'] ?? 0),
            'disk_used' => (int) ($status['disk'] ?? 0),
            'disk_total' => (int) ($status['maxdisk'] ?? 0),
            'net_in' => (int) ($status['netin'] ?? 0),
            'net_out' => (int) ($status['netout'] ?? 0),
            'connection' => ['Hostname' => $this->hostname($service), 'Node' => $node, 'ID' => (string) $vmid],
        ];
    }

    public function actions(Service $service): array
    {
        return ['start' => 'Start', 'shutdown' => 'Shut down', 'reboot' => 'Reboot', 'stop' => 'Force stop'];
    }

    public function perform(Service $service, string $action): string
    {
        if (!array_key_exists($action, $this->actions($service))) {
            throw new IntegrationException('Unsupported action.');
        }

        [$node, $vmid, $type] = $this->guest($service);
        $this->client($service)->power($node, $type, $vmid, $action);

        return 'The action has been sent to the server.';
    }

    /**
     * @return array{0: string, 1: int, 2: string}
     */
    private function guest(Service $service): array
    {
        if (!isset($service->remote['node'], $service->remote['vmid'])) {
            throw new IntegrationException('The server has not been created yet.');
        }

        return [(string) $service->remote['node'], (int) $service->remote['vmid'], (string) ($service->remote['type'] ?? $this->type($service))];
    }

    public static function generatePassword(int $length = 20): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
