<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use DateTimeImmutable;
use DateTimeZone;
use Nebrix\Auth\User;
use Nebrix\Catalog\ProductCategory;
use Nebrix\Database\Connection;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\Proxmox\ProxmoxClient;
use Nebrix\Network\IpPoolService;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Psr\Log\LoggerInterface;

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
 *
 * Backups (product settings "backup_storage" and "max_backups"): vzdump in
 * snapshot mode with the note "nebrix-service-<id>". Customers only ever see,
 * restore or delete backups of their own guest that carry this note and were
 * made after the service was created, so a reused VMID never exposes another
 * customer's data. A restore runs through the provisioning queue (steps
 * "restore" and "restore_start") and keeps the guest's VMID and address.
 * Terminating the service deletes its backups.
 */
final class ProxmoxProvisioner implements Provisioner, ConsoleProvider
{
    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly ServiceRepository $services,
        private readonly IpPoolService $ipPools,
        private readonly Connection $db,
        private readonly LoggerInterface $logger,
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

            if ($step === 'restore') {
                $this->restoreGuest($client, $service, $remote, $node, $vmid, $type);
            }

            if ($step === 'restore_start') {
                $upid = $client->power($node, $type, $vmid, 'start');
                $this->services->mergeRemote($service->id, ['upid' => $upid]);

                throw new RetryLaterException('Waiting for the restored server to start.', 5);
            }

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
                ['node' => $node, 'vmid' => $vmid, 'type' => $type, 'step' => 'done', 'restore_volid' => null],
                $credentials,
            );
        }
    }

    /**
     * Restore step: waits for a running backup, verifies that the backup
     * still belongs to the service, stops the guest and restores the backup
     * over it (same VMID). Ends by throwing RetryLater with the restore task
     * persisted as "upid"; the state machine then starts the guest.
     *
     * @param array<string, mixed> $remote
     */
    private function restoreGuest(ProxmoxClient $client, Service $service, array $remote, string $node, int $vmid, string $type): never
    {
        $storage = $this->backupStorage($service) ?? throw new IntegrationException('Backups are not enabled for this product.');
        $volid = (string) ($remote['restore_volid'] ?? '');

        // A backup of the guest may still be running (the guest is locked then).
        if (isset($remote['backup_upid'])) {
            if ($client->taskStatus($node, (string) $remote['backup_upid'])['status'] !== 'stopped') {
                throw new RetryLaterException('Waiting for the running backup to finish before restoring.', 15);
            }
            $this->services->mergeRemote($service->id, ['backup_upid' => null]);
        }

        if (!isset($this->ownBackups($client, $service, $node, $vmid)[$volid])) {
            // Nothing has been changed yet: a retry by the staff re-activates the service unchanged.
            $this->services->mergeRemote($service->id, ['step' => 'done', 'restore_volid' => null]);

            throw new IntegrationException('The selected backup does not exist (anymore); nothing was restored.');
        }
        $existing = $client->findGuest($vmid);
        if ($existing !== null && !$this->isOwnGuest($client, $service, $existing, $node, $type)) {
            throw new IntegrationException(sprintf('VM ID %d belongs to another guest ("%s"); the backup was not restored.', $vmid, (string) ($existing['name'] ?? '?')));
        }
        if ($existing !== null && ($client->status($node, $type, $vmid)['status'] ?? '') === 'running') {
            $client->power($node, $type, $vmid, 'stop');

            throw new RetryLaterException('Waiting for the server to stop before restoring.', 10);
        }

        $upid = $client->restoreBackup($node, $type, $vmid, $volid, $storage, (string) $service->config('storage'));
        $this->services->mergeRemote($service->id, ['upid' => $upid]);

        throw new RetryLaterException('Waiting for Proxmox to restore the backup.', 15);
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
        $order += ['restore' => 'restore_start', 'restore_start' => 'done'];

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

        $config = $client->guestConfig($node, $type, (int) $guest['vmid']);
        $marker = 'nebrix-service-' . $service->id;

        // Guests imported from RED-Host carry no marker: accept the VMID the
        // legacy database recorded only if the guest still has the recorded
        // host name and no marker of another service (a deleted legacy VM's
        // VMID may have been reused).
        if (($service->remote['legacy'] ?? false) === true && (int) ($service->remote['vmid'] ?? 0) === (int) $guest['vmid']) {
            $legacyName = (string) $service->option('hostname', '');

            return $legacyName !== ''
                && (string) ($guest['name'] ?? $config['hostname'] ?? $config['name'] ?? '') === $legacyName
                && !str_contains((string) ($config['description'] ?? ''), 'nebrix-service-');
        }

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
        $client = $this->client($service);
        $this->destroyGuest($client, $service, $service->remote, $node, $vmid, $type);
        $this->services->mergeRemote($service->id, ['destroy_upid' => null]);
        $this->deleteAllBackups($client, $service, $node, $vmid);
        $this->ipPools->release($service->id);
    }

    /**
     * The contract has ended: the customer's backups are deleted as well.
     * A failure does not block the termination; it is logged for the staff.
     */
    private function deleteAllBackups(ProxmoxClient $client, Service $service, string $node, int $vmid): void
    {
        $storage = $this->backupStorage($service);
        if ($storage === null) {
            return;
        }
        try {
            foreach (array_keys($this->ownBackups($client, $service, $node, $vmid)) as $volid) {
                $client->deleteBackup($node, $storage, $volid);
            }
        } catch (IntegrationException $exception) {
            $this->logger->error('Backups of a terminated service could not be deleted; delete them manually.', ['service' => $service->id, 'storage' => $storage, 'message' => $exception->getMessage()]);
        }
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

        return $actions + $this->backupActions($service);
    }

    /**
     * @return array<string, ServiceAction>
     */
    private function backupActions(Service $service): array
    {
        $max = (int) $service->config('max_backups', 0);
        if ($this->backupStorage($service) === null || $max < 1 || !isset($service->remote['node'], $service->remote['vmid'])) {
            return [];
        }
        [$node, $vmid] = $this->guest($service);
        try {
            $backups = $this->ownBackups($this->client($service), $service, $node, $vmid);
        } catch (IntegrationException $exception) {
            // The backup storage being unavailable must not hide the other actions.
            $this->logger->warning('Backups could not be listed', ['service' => $service->id, 'message' => $exception->getMessage()]);

            return [];
        }
        $options = [];
        foreach ($backups as $volid => $backup) {
            $options[$volid] = gmdate('Y-m-d H:i', $backup['ctime']) . ' UTC (' . self::formatSize($backup['size']) . ')';
        }

        // Always offered: at the limit, the customer gets an explanatory message.
        $actions = ['backup_create' => ServiceAction::simple('Create backup', 'Create a backup of the server now? The server keeps running.')];
        if ($options !== []) {
            $field = ['backup' => ['label' => 'Backup', 'rules' => 'required|string|max:255', 'type' => 'select', 'options' => $options, 'default' => (string) array_key_first($options)]];
            $actions['backup_restore'] = new ServiceAction('Restore backup', $field, 'Restore this backup? All changes since the backup are lost. The root password is the one that was valid at the time of the backup.', ServiceAction::REINSTALL, true, 'The backup is being restored. The server restarts when the restore is finished.');
            $actions['backup_delete'] = new ServiceAction('Delete backup', $field, 'Delete this backup permanently?', ServiceAction::SYNC, true);
        }

        return $actions;
    }

    private function backupStorage(Service $service): ?string
    {
        $storage = trim((string) $service->config('backup_storage', ''));

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $storage) === 1 ? $storage : null;
    }

    /**
     * Backups that belong to this service: guest VMID, note
     * "nebrix-service-<id>" and made after the service was created. Newest
     * first.
     *
     * @return array<string, array{volid: string, ctime: int, size: int, notes: string}> volid => backup
     */
    private function ownBackups(ProxmoxClient $client, Service $service, string $node, int $vmid): array
    {
        $storage = $this->backupStorage($service);
        if ($storage === null) {
            return [];
        }
        $createdAt = (string) ($service->attributes['created_at'] ?? '');
        $since = $createdAt !== '' ? (new DateTimeImmutable($createdAt, new DateTimeZone('UTC')))->getTimestamp() : 0;
        $marker = 'nebrix-service-' . $service->id;

        $own = [];
        foreach ($client->backups($node, $storage, $vmid) as $backup) {
            if (trim($backup['notes']) === $marker && $backup['ctime'] >= $since) {
                $own[$backup['volid']] = $backup;
            }
        }
        uasort($own, static fn (array $a, array $b): int => $b['ctime'] <=> $a['ctime']);

        return $own;
    }

    public static function formatSize(int $bytes): string
    {
        return $bytes >= 1073741824 ? number_format($bytes / 1073741824, 1) . ' GB' : number_format(max(0, $bytes) / 1048576, 0) . ' MB';
    }

    /**
     * @param array<string, mixed> $input
     */
    private function performBackupAction(Service $service, ProxmoxClient $client, string $action, array $input, string $node, int $vmid): string
    {
        $storage = $this->backupStorage($service) ?? throw new ServiceStateException('Backups are not enabled for this server.');

        if ($action === 'backup_delete') {
            $volid = (string) ($input['backup'] ?? '');
            if (!isset($this->ownBackups($client, $service, $node, $vmid)[$volid])) {
                throw new ServiceStateException('The selected backup does not exist (anymore).');
            }
            $client->deleteBackup($node, $storage, $volid);

            return 'The backup has been deleted.';
        }

        // backup_create: serialised per service, so that concurrent requests
        // cannot exceed the number of backups of the product.
        return $this->db->withLock('nebrix-backup-' . $service->id, 5, function () use ($service, $client, $storage, $node, $vmid): string {
            $remote = ($this->services->find($service->id) ?? $service)->remote;
            if (isset($remote['backup_upid']) && $client->taskStatus($node, (string) $remote['backup_upid'])['status'] !== 'stopped') {
                throw new ServiceStateException('A backup is already running. Please wait until it is finished.');
            }
            if (count($this->ownBackups($client, $service, $node, $vmid)) >= (int) $service->config('max_backups', 0)) {
                throw new ServiceStateException('The maximum number of backups has been reached. Delete an old backup first.');
            }
            $upid = $client->createBackup($node, $vmid, $storage, 'nebrix-service-' . $service->id);
            $this->services->mergeRemote($service->id, ['backup_upid' => $upid]);

            return 'The backup has been started. It appears in the list when it is finished.';
        });
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
                // … or through cloud-init, applied at the next boot – only if the VM has a cloud-init drive.
                $hasCloudInit = false;
                foreach ($client->guestConfig($node, ProxmoxClient::TYPE_QEMU, $vmid) as $value) {
                    $hasCloudInit = $hasCloudInit || (is_string($value) && str_contains($value, 'cloudinit'));
                }
                if (!$hasCloudInit) {
                    throw new IntegrationException('The QEMU guest agent is not running and the server has no cloud-init drive. Start the guest agent and try again.');
                }
                $client->updateVmConfig($node, $vmid, ['cipassword' => $password]);
                $message = 'The new root password takes effect after the next restart of the server. You can view it under access data.';
            }
            $this->services->setCredentials($service->id, ['password' => $password] + $this->services->credentials($service));

            return $message;
        }

        if (in_array($action, ['backup_create', 'backup_delete'], true)) {
            return $this->performBackupAction($service, $client, $action, $input, $node, $vmid);
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

        // Restore: the backup was offered from the service's own backups
        // (select whitelist); the provisioning job checks it again.
        if (isset($input['backup'])) {
            $storage = $this->backupStorage($service) ?? throw new ServiceStateException('Backups are not enabled for this server.');
            $volid = (string) $input['backup'];
            if (!ProxmoxClient::isBackupVolume($volid, $storage)) {
                throw new ServiceStateException('Choose one of the offered backups.');
            }
            $this->services->mergeRemote($service->id, ['step' => 'restore', 'upid' => null, 'destroy_upid' => null, 'restore_volid' => $volid]);

            return;
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
