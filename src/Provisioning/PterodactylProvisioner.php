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

/**
 * Game servers on Pterodactyl. One panel user per customer
 * (external_id nebrix-user-<id>), one server per service
 * (external_id nebrix-service-<id>).
 */
final class PterodactylProvisioner implements Provisioner
{
    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly ServiceRepository $services,
        private readonly Connection $db,
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

        return $client->hasClientApi() ? ['start' => 'Start', 'restart' => 'Restart', 'stop' => 'Stop', 'kill' => 'Kill'] : [];
    }

    public function perform(Service $service, string $action): string
    {
        [, $client] = $this->client($service);
        if (!in_array($action, ['start', 'restart', 'stop', 'kill'], true)) {
            throw new IntegrationException('Unsupported action.');
        }
        $client->power((string) $service->remote('identifier', ''), $action);

        return 'The power signal has been sent.';
    }
}
