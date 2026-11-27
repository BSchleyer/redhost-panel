<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Auth\User;
use Nebrix\Catalog\ProductCategory;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\Proxmox\ProxmoxClient;
use Nebrix\Network\IpPoolService;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;

/**
 * Provisions KVM virtual machines (clone of a cloud-init template) and LXC
 * containers on Proxmox VE.
 *
 * Idempotency: the VMID is deterministic (vmid_start + service ID) and the
 * node, VMID, current step and task UPID are persisted before each remote
 * call. A retried job resumes the state machine instead of creating a second
 * guest. An existing guest is only adopted if its description carries the
 * marker "nebrix-service-<id>" written at creation time; any other guest on
 * the VMID is treated as a conflict (the hostname is customer-chosen and
 * therefore not proof of ownership).
 *
 * Addressing: with an IP pool on the product the guest gets a static IPv4
 * address (allocated once per service, released after termination);
 * otherwise DHCP. Reinstalling destroys the guest (step "destroy") and
 * re-creates it with the same VMID, address and a new root password.
 */
final class ProxmoxProvisioner implements Provisioner, ConsoleProvider
{
    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly ServiceRepository $services,
        private readonly IpPoolService $ipPools,
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

        // Static address from the product's IP pool (idempotent per service).
        $poolId = (int) $service->config('ip_pool_id', 0);
        if ($poolId > 0 && !isset($remote['ipv4'])) {
            $lease = $this->ipPools->allocate($poolId, $service->id);
            $addressing = ['ipv4' => $lease['address'] . '/' . $lease['prefix'], 'gateway' => $lease['gateway'], 'dns' => implode(' ', $lease['dns'])];
            $this->services->mergeRemote($service->id, $addressing);
            $remote = $addressing + $remote;
        }

        // Nothing exists remotely yet (e.g. retry after a failed create call):
        // re-evaluate the node so that a corrected product configuration applies.
        if (($remote['step'] ?? 'create') === 'create' && !isset($remote['upid']) && $client->findGuest((int) $remote['vmid']) === null) {
            $configuredNode = (string) $service->config('node', '');
            $node = $configuredNode !== '' ? $configuredNode : $client->pickNode();
            if ($node !== $remote['node']) {
                $remote['node'] = $node;
                $this->services->mergeRemote($service->id, ['node' => $node]);
            }
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

            if ($step === 'destroy') {
                // Reinstall: remove the old guest first (throws RetryLater while running).
                $this->destroyGuest($client, $service, $remote, $node, $vmid, $type);
                $this->services->mergeRemote($service->id, ['step' => 'create', 'destroy_upid' => null]);
                $remote['step'] = 'create';
                unset($remote['destroy_upid']);
                continue;
            }

            if ($step === 'create') {
                $existing = $client->findGuest($vmid);
                if ($existing !== null) {
                    if (!$this->isOwnGuest($client, $service, $existing, $node, $type)) {
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
                    ? $client->createContainer($node, $this->containerParameters($service, $vmid, (string) $credentials['password'], $remote))
                    : $client->cloneVm($node, (int) ($remote['template'] ?? $service->config('template_vmid')), [
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
                $client->updateVmConfig($node, $vmid, $this->vmParameters($service, (string) ($credentials['password'] ?? ''), $remote));
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
     * @param array<string, mixed> $guest entry from /cluster/resources
     */
    private function isOwnGuest(ProxmoxClient $client, Service $service, array $guest, string $node, string $type): bool
    {
        if (isset($guest['type']) && (string) $guest['type'] !== $type) {
            return false;
        }
        if (isset($guest['node']) && (string) $guest['node'] !== $node) {
            return false;
        }

        // Guests imported from RED-Host carry no marker: the legacy database
        // assigned exactly this VMID to the service.
        if (($service->remote['legacy'] ?? false) === true && (int) ($service->remote['vmid'] ?? 0) === (int) $guest['vmid']) {
            return true;
        }

        $config = $client->guestConfig($node, $type, (int) $guest['vmid']);
        $marker = 'nebrix-service-' . $service->id;
        foreach (preg_split('/\R/', (string) ($config['description'] ?? '')) ?: [] as $line) {
            if (trim($line) === $marker) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $remote
     * @return array<string, scalar>
     */
    private function containerParameters(Service $service, int $vmid, string $password, array $remote): array
    {
        $rate = (int) $service->config('rate_mbps', 0);
        $unprivileged = (bool) $service->config('unprivileged', true);
        $ipv4 = isset($remote['ipv4']) ? sprintf('ip=%s,gw=%s', (string) $remote['ipv4'], (string) ($remote['gateway'] ?? '')) : 'ip=dhcp';

        $parameters = [
            'vmid' => $vmid,
            'hostname' => $this->hostname($service),
            'ostemplate' => (string) ($remote['template'] ?? $service->config('ostemplate')),
            'cores' => (int) $service->config('cores', 1),
            'memory' => (int) $service->config('memory_mb', 1024),
            'swap' => (int) $service->config('swap_mb', 512),
            'rootfs' => sprintf('%s:%d', (string) $service->config('storage'), (int) $service->config('disk_gb', 10)),
            'net0' => sprintf('name=eth0,bridge=%s,%s,ip6=auto%s', (string) $service->config('bridge', 'vmbr0'), $ipv4, $rate > 0 ? ',rate=' . $rate : ''),
            'password' => $password,
            'unprivileged' => $unprivileged ? 1 : 0,
            'onboot' => 1,
            'start' => 1,
            'description' => 'nebrix-service-' . $service->id,
        ];

        // Proxmox only lets root@pam set feature flags on privileged
        // containers, so nesting is requested for unprivileged ones only.
        if ($unprivileged) {
            $parameters['features'] = 'nesting=1';
        }
        if (($remote['dns'] ?? '') !== '') {
            $parameters['nameserver'] = (string) $remote['dns'];
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $remote
     * @return array<string, scalar>
     */
    private function vmParameters(Service $service, string $password, array $remote): array
    {
        $rate = (int) $service->config('rate_mbps', 0);
        $parameters = [
            'cores' => (int) $service->config('cores', 2),
            'memory' => (int) $service->config('memory_mb', 2048),
            'ciuser' => 'root',
            'cipassword' => $password,
            'ipconfig0' => isset($remote['ipv4']) ? sprintf('ip=%s,gw=%s,ip6=auto', (string) $remote['ipv4'], (string) ($remote['gateway'] ?? '')) : 'ip=dhcp,ip6=auto',
            'net0' => sprintf('virtio,bridge=%s%s', (string) $service->config('bridge', 'vmbr0'), $rate > 0 ? ',rate=' . $rate : ''),
            'onboot' => 1,
        ];
        if (($remote['dns'] ?? '') !== '') {
            $parameters['nameserver'] = (string) $remote['dns'];
        }

        return $parameters;
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
            $this->ipPools->release($service->id);

            return; // never created
        }

        [$node, $vmid, $type] = $this->guest($service);
        $this->destroyGuest($this->client($service), $service, $service->remote, $node, $vmid, $type);
        $this->services->mergeRemote($service->id, ['destroy_upid' => null]);
        $this->ipPools->release($service->id);
    }

    /**
     * Stops and deletes the service's guest. Returns when the guest no longer
     * exists; throws RetryLater while Proxmox works on it. Never deletes a
     * guest that does not carry the service's marker.
     *
     * @param array<string, mixed> $remote
     */
    private function destroyGuest(ProxmoxClient $client, Service $service, array $remote, string $node, int $vmid, string $type): void
    {
        if (isset($remote['destroy_upid'])) {
            $task = $client->taskStatus($node, (string) $remote['destroy_upid']);
            if ($task['status'] !== 'stopped') {
                throw new RetryLaterException('Waiting for Proxmox to delete the guest.', 10);
            }
            $this->services->mergeRemote($service->id, ['destroy_upid' => null]);
        }

        $existing = $client->findGuest($vmid);
        if ($existing === null) {
            return;
        }
        // Never delete a guest this service does not own (e.g. after a VMID conflict).
        if (!$this->isOwnGuest($client, $service, $existing, $node, $type)) {
            throw new IntegrationException(sprintf('VM ID %d belongs to another guest ("%s"); it was not deleted. Check the Proxmox cluster.', $vmid, (string) ($existing['name'] ?? '?')));
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
            'connection' => array_filter([
                'Hostname' => $this->hostname($service),
                'IPv4' => isset($service->remote['ipv4']) ? strtok((string) $service->remote['ipv4'], '/') : '',
                'Node' => $node,
                'ID' => (string) $vmid,
            ], static fn (string|false $value): bool => $value !== '' && $value !== false),
        ];
    }

    public function actions(Service $service): array
    {
        $actions = [
            'start' => ServiceAction::simple('Start'),
            'shutdown' => ServiceAction::simple('Shut down', 'Shut the server down?'),
            'reboot' => ServiceAction::simple('Reboot', 'Reboot the server?'),
            'stop' => ServiceAction::simple('Force stop', 'Force-stop the server? Unsaved data may be lost.', true),
        ];
        if ($this->type($service) === ProxmoxClient::TYPE_QEMU) {
            $actions['reset_password'] = ServiceAction::simple('Reset root password', 'Set a new root password? The current password stops working.');
        }
        $templates = $this->reinstallTemplates($service);
        $actions['reinstall'] = new ServiceAction('Reinstall', [
            'template' => ['label' => 'Operating system', 'rules' => 'required|string|max:255', 'type' => 'select', 'options' => $templates, 'default' => (string) array_key_first($templates)],
        ], 'Reinstall the server? ALL data on the server will be deleted.', ServiceAction::REINSTALL, true);

        return $actions;
    }

    public function perform(Service $service, string $action, array $input = []): string
    {
        [$node, $vmid, $type] = $this->guest($service);
        $client = $this->client($service);

        if ($action === 'reset_password' && $type === ProxmoxClient::TYPE_QEMU) {
            $password = self::generatePassword();
            try {
                // Immediate change through the QEMU guest agent …
                $client->setUserPassword($node, $vmid, 'root', $password);
                $message = 'A new root password has been set. You can view it under access data.';
            } catch (IntegrationException) {
                // … or through cloud-init, applied at the next boot.
                $client->updateVmConfig($node, $vmid, ['cipassword' => $password]);
                $message = 'The new root password takes effect after the next restart of the server. You can view it under access data.';
            }
            $this->services->setCredentials($service->id, ['password' => $password] + $this->services->credentials($service));

            return $message;
        }

        if (!in_array($action, ['start', 'shutdown', 'reboot', 'stop'], true)) {
            throw new IntegrationException('Unsupported action.');
        }
        $client->power($node, $type, $vmid, $action);

        return 'The action has been sent to the server.';
    }

    public function openConsole(Service $service): array
    {
        [$node, $vmid, $type] = $this->guest($service);
        $client = $this->client($service);
        $proxy = $client->vncProxy($node, $type, $vmid);

        // Proxmox uses the VNC ticket as the VNC password.
        return $client->vncWebSocketTarget($node, $type, $vmid, $proxy['port'], $proxy['ticket']) + ['password' => $proxy['ticket']];
    }

    public function prepareReinstall(Service $service, array $input): void
    {
        if (!isset($service->remote['node'], $service->remote['vmid'])) {
            throw new ServiceStateException('The server has not been created yet.');
        }
        $templates = $this->reinstallTemplates($service);
        $template = (string) ($input['template'] ?? '');
        if (!array_key_exists($template, $templates)) {
            throw new ServiceStateException('Choose one of the offered operating systems.');
        }

        // New root password; the provisioning run destroys and re-creates the guest.
        $this->services->setCredentials($service->id, []);
        $this->services->mergeRemote($service->id, ['step' => 'destroy', 'upid' => null, 'destroy_upid' => null, 'template' => $template]);
    }

    /**
     * Templates offered for reinstalling: the product's template first, then
     * the lines "value|Label" of the product setting "reinstall_templates".
     *
     * @return array<string, string> template => label
     */
    private function reinstallTemplates(Service $service): array
    {
        $isVm = $this->type($service) === ProxmoxClient::TYPE_QEMU;
        $pattern = $isVm ? '/^\d{3,9}$/' : '/^[A-Za-z0-9._-]+:vztmpl\/[A-Za-z0-9._+-]+$/';
        $default = (string) ($isVm ? $service->config('template_vmid', '') : $service->config('ostemplate', ''));
        $templates = [];
        if (preg_match($pattern, $default) === 1) {
            $templates[$default] = 'Default image';
        }
        foreach (preg_split('/\R/', (string) $service->config('reinstall_templates', '')) ?: [] as $line) {
            [$value, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if (preg_match($pattern, $value) === 1) {
                $templates[$value] = $label !== '' ? mb_substr($label, 0, 60) : $value;
            }
        }

        return $templates;
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
