<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Auth\User;
use Nebrix\Database\Connection;
use Nebrix\Integration\Integration;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\Pterodactyl\PterodactylClient;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Psr\Log\LoggerInterface;

/**
 * Game servers on Pterodactyl. One panel user per customer
 * (external_id nebrix-user-<id>), one server per service
 * (external_id nebrix-service-<id>).
 *
 * With the Client API configured, customers can create, restore, download
 * and delete backups of their server (product setting "backups" = limit,
 * also enforced by Pterodactyl). Only backups of the service's own server
 * are offered; deleting the server in Pterodactyl deletes its backups.
 */
final class PterodactylProvisioner implements Provisioner
{
    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly ServiceRepository $services,
        private readonly Connection $db,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{0: Integration, 1: PterodactylClient}
     */
    private function client(Service $service): array
    {
        if ($service->integrationId === null) {
            throw new IntegrationException('No Pterodactyl integration is assigned to this service.');
        }
        $integration = $this->integrations->get($service->integrationId);

        return [$integration, $this->integrations->pterodactyl($integration)];
    }

    public function provision(Service $service, User $owner): ProvisionResult
    {
        [$integration, $client] = $this->client($service);
        $panelUrl = rtrim((string) $integration->config('base_url'), '/');

        // 1. Panel user (shared by all game servers of the customer).
        $userExternalId = 'nebrix-user-' . $owner->id;
        $panelUser = $client->findUserByExternalId($userExternalId);
        $credentials = $this->services->credentials($service);

        if ($panelUser === null) {
            $password = ProxmoxProvisioner::generatePassword(24);
            $panelUser = $client->createUser([
                'email' => $owner->email,
                'username' => 'nx' . $owner->id,
                'first_name' => mb_substr($owner->firstName ?: 'Customer', 0, 191),
                'last_name' => mb_substr($owner->lastName ?: (string) $owner->id, 0, 191),
                'external_id' => $userExternalId,
                'password' => $password,
            ]);
            $credentials = ['panel_url' => $panelUrl, 'username' => 'nx' . $owner->id, 'email' => $owner->email, 'password' => $password];
            $this->services->setCredentials($service->id, $credentials);
        } elseif (($credentials['password'] ?? '') === '') {
            $credentials = $this->existingPanelCredentials($owner->id, (int) $integration->id) ?? ['panel_url' => $panelUrl, 'username' => (string) ($panelUser['username'] ?? ''), 'email' => $owner->email, 'note' => 'Use your existing game panel login.'];
            $this->services->setCredentials($service->id, $credentials);
        }

        // 2. Server (looked up by external ID first).
        $externalId = 'nebrix-service-' . $service->id;
        $server = $client->findServerByExternalId($externalId);
        if ($server === null) {
            $egg = $client->egg((int) $service->config('nest_id'), (int) $service->config('egg_id'));
            $overrides = json_decode((string) ($service->config('environment') ?: '{}'), true);
            $environment = array_merge($egg['variables'], is_array($overrides) ? array_map('strval', $overrides) : []);

            $server = $client->createServer([
                'name' => mb_substr((string) $service->option('server_name', 'Game server #' . $service->id), 0, 191),
                'user' => (int) $panelUser['id'],
                'egg' => (int) $service->config('egg_id'),
                'docker_image' => (string) ($service->config('docker_image') ?: $egg['docker_image']),
                'startup' => $egg['startup'],
                'environment' => $environment,
                'limits' => [
                    'memory' => (int) $service->config('memory_mb'),
                    'swap' => (int) $service->config('swap_mb', 0),
                    'disk' => (int) $service->config('disk_mb'),
                    'io' => (int) $service->config('io', 500),
                    'cpu' => (int) $service->config('cpu_percent', 0),
                ],
                'feature_limits' => [
                    'databases' => (int) $service->config('databases', 0),
                    'allocations' => (int) $service->config('allocations', 0),
                    'backups' => (int) $service->config('backups', 0),
                ],
                'deploy' => ['locations' => [(int) $service->config('location_id')], 'dedicated_ip' => false, 'port_range' => []],
                'external_id' => $externalId,
                'start_on_completion' => true,
            ]);
        }

        $remote = [
            'server_id' => (int) $server['id'],
            'identifier' => (string) ($server['identifier'] ?? ''),
            'uuid' => (string) ($server['uuid'] ?? ''),
            'panel_user_id' => (int) $panelUser['id'],
        ];
        $this->services->mergeRemote($service->id, $remote);

        return new ProvisionResult((string) $server['id'], $remote, $credentials);
    }

    /**
     * @return array<string, scalar|null>|null
     */
    private function existingPanelCredentials(int $userId, int $integrationId): ?array
    {
        foreach ($this->db->fetchAll("SELECT `id` FROM `nx_services` WHERE `user_id` = ? AND `integration_id` = ? AND `credentials` IS NOT NULL ORDER BY `id`", [$userId, $integrationId]) as $row) {
            $other = $this->services->find((int) $row['id']);
            if ($other !== null) {
                $credentials = $this->services->credentials($other);
                if (($credentials['password'] ?? '') !== '') {
                    return $credentials;
                }
            }
        }

        return null;
    }

    public function suspend(Service $service): void
    {
        $this->withServer($service, static fn (PterodactylClient $client, int $id) => $client->suspendServer($id));
    }

    public function unsuspend(Service $service): void
    {
        $this->withServer($service, static fn (PterodactylClient $client, int $id) => $client->unsuspendServer($id));
    }

    public function terminate(Service $service): void
    {
        [, $client] = $this->client($service);
        $server = $client->findServerByExternalId('nebrix-service-' . $service->id);
        if ($server !== null) {
            $client->deleteServer((int) $server['id']);

            return;
        }
        // Servers imported from RED-Host have no external ID; the legacy
        // database recorded their server ID.
        if (($service->remote['legacy'] ?? false) === true && (int) $service->remote('server_id', 0) > 0) {
            try {
                $server = $client->server((int) $service->remote('server_id', 0));
            } catch (IntegrationException $exception) {
                if ($exception->isNotFound()) {
                    return;
                }
                throw $exception;
            }
            // Only delete the server the legacy database recorded (identifier check).
            if ((string) ($server['identifier'] ?? '') === '' || (string) $server['identifier'] !== (string) $service->remote('identifier', '')) {
                throw new IntegrationException('The game server with the recorded ID has another identifier; nothing was deleted.');
            }
            $client->deleteServer((int) $service->remote('server_id', 0));
        }
    }

    /**
     * @param callable(PterodactylClient, int): void $operation
     */
    private function withServer(Service $service, callable $operation): void
    {
        [, $client] = $this->client($service);
        $id = (int) $service->remote('server_id', 0);
        if ($id === 0) {
            $server = $client->findServerByExternalId('nebrix-service-' . $service->id);
            if ($server === null) {
                return;
            }
            $id = (int) $server['id'];
        }
        $operation($client, $id);
    }

    public function status(Service $service): array
    {
        [$integration, $client] = $this->client($service);
        $identifier = (string) $service->remote('identifier', '');
        $status = ['connection' => ['Game panel' => rtrim((string) $integration->config('base_url'), '/') . ($identifier !== '' ? '/server/' . $identifier : '')]];

        if ($client->hasClientApi() && $identifier !== '') {
            $resources = $client->resources($identifier);
            $usage = $resources['resources'] ?? [];

            return $status + [
                'state' => (string) ($resources['current_state'] ?? 'unknown'),
                'cpu_percent' => round((float) ($usage['cpu_absolute'] ?? 0), 1),
                'memory_used' => (int) ($usage['memory_bytes'] ?? 0),
                'memory_total' => (int) $service->config('memory_mb') * 1024 * 1024,
                'disk_used' => (int) ($usage['disk_bytes'] ?? 0),
                'disk_total' => (int) $service->config('disk_mb') * 1024 * 1024,
                'uptime' => (int) (($usage['uptime'] ?? 0) / 1000),
            ];
        }

        $server = $client->server((int) $service->remote('server_id'));

        return $status + ['state' => ($server['suspended'] ?? false) ? 'suspended' : (string) ($server['status'] ?? 'installed')];
    }

    public function actions(Service $service): array
    {
        [, $client] = $this->client($service);
        if (!$client->hasClientApi()) {
            return [];
        }

        return [
            'start' => ServiceAction::simple('Start'),
            'restart' => ServiceAction::simple('Restart'),
            'stop' => ServiceAction::simple('Stop', 'Stop the server?'),
            'kill' => ServiceAction::simple('Kill', 'Kill the server process? Unsaved data is lost.', true),
        ] + $this->backupActions($service, $client);
    }

    /**
     * @return array<string, ServiceAction>
     */
    private function backupActions(Service $service, PterodactylClient $client): array
    {
        $limit = (int) $service->config('backups', 0);
        $identifier = (string) $service->remote('identifier', '');
        if ($limit < 1 || $identifier === '') {
            return [];
        }
        try {
            $backups = $client->backups($identifier);
        } catch (IntegrationException $exception) {
            // Backups being unavailable must not hide the power actions.
            $this->logger->warning('Backups could not be listed', ['service' => $service->id, 'message' => $exception->getMessage()]);

            return [];
        }
        $finished = [];
        foreach ($backups as $backup) {
            if ($backup['completed'] && $backup['successful']) {
                $created = strtotime($backup['created_at']);
                $finished[$backup['uuid']] = sprintf('%s – %s (%s)', $backup['name'] !== '' ? $backup['name'] : 'Backup', $created !== false ? gmdate('Y-m-d H:i', $created) . ' UTC' : '?', ProxmoxProvisioner::formatSize($backup['bytes']));
            }
        }

        // Always offered: at the limit, the customer gets an explanatory message.
        $actions = ['backup_create' => ServiceAction::simple('Create backup', 'Create a backup of the server now?')];
        if ($finished !== []) {
            $field = ['backup' => ['label' => 'Backup', 'rules' => 'required|string|max:36', 'type' => 'select', 'options' => $finished, 'default' => (string) array_key_first($finished)]];
            $actions['backup_restore'] = new ServiceAction('Restore backup', $field, 'Restore this backup? All current files of the server are deleted and replaced by the backup.', ServiceAction::SYNC, true);
            $actions['backup_download'] = new ServiceAction('Download backup', $field, null, ServiceAction::REDIRECT);
            $actions['backup_delete'] = new ServiceAction('Delete backup', $field, 'Delete this backup permanently?', ServiceAction::SYNC, true);
        }

        return $actions;
    }

    public function perform(Service $service, string $action, array $input = []): string
    {
        [, $client] = $this->client($service);
        $identifier = (string) $service->remote('identifier', '');

        if (str_starts_with($action, 'backup_')) {
            return $this->performBackupAction($service, $client, $identifier, $action, $input);
        }
        if (!in_array($action, ['start', 'restart', 'stop', 'kill'], true)) {
            throw new IntegrationException('Unsupported action.');
        }
        $client->power($identifier, $action);

        return 'The power signal has been sent.';
    }

    /**
     * @param array<string, mixed> $input
     */
    private function performBackupAction(Service $service, PterodactylClient $client, string $identifier, string $action, array $input): string
    {
        $limit = (int) $service->config('backups', 0);
        if ($limit < 1 || $identifier === '') {
            throw new ServiceStateException('Backups are not available for this server.');
        }
        $backups = $client->backups($identifier);

        if ($action === 'backup_create') {
            if (count($backups) >= $limit) {
                throw new ServiceStateException('The maximum number of backups has been reached. Delete an old backup first.');
            }
            $client->createBackup($identifier, 'Backup ' . gmdate('Y-m-d H:i'));

            return 'The backup has been started. It appears in the list when it is finished.';
        }

        // The backup must be a finished backup of this server.
        $uuid = (string) ($input['backup'] ?? '');
        $selected = null;
        foreach ($backups as $backup) {
            if ($backup['uuid'] === $uuid && $backup['completed'] && $backup['successful']) {
                $selected = $backup;
            }
        }
        if ($selected === null) {
            throw new ServiceStateException('The selected backup does not exist (anymore).');
        }

        return match ($action) {
            'backup_restore' => (function () use ($client, $identifier, $uuid): string {
                $client->restoreBackup($identifier, $uuid);

                return 'The backup is being restored. The server is available again when the restore is finished.';
            })(),
            'backup_download' => $client->backupDownloadUrl($identifier, $uuid),
            'backup_delete' => (function () use ($client, $identifier, $uuid, $selected): string {
                if ($selected['locked']) {
                    throw new ServiceStateException('This backup is locked and cannot be deleted.');
                }
                $client->deleteBackup($identifier, $uuid);

                return 'The backup has been deleted.';
            })(),
            default => throw new IntegrationException('Unsupported action.'),
        };
    }

    public function prepareReinstall(Service $service, array $input): void
    {
        throw new IntegrationException('Reinstalling is not supported for this service type.');
    }
}
