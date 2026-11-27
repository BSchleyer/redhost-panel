<?php

declare(strict_types=1);

namespace Nebrix\Integration\Pterodactyl;

use Nebrix\Integration\HealthResult;
use Nebrix\Integration\Http\ApiClient;
use Nebrix\Integration\IntegrationException;

/**
 * Pterodactyl Application API (/api/application) and, when a root-admin
 * client key is configured, the Client API (/api/client) for live
 * resources, power signals and backups.
 *
 * External IDs (users: "nebrix-user-<id>", servers: "nebrix-service-<id>")
 * make provisioning idempotent: before creating anything the client looks
 * the object up by its external ID.
 */
class PterodactylClient
{
    private const POWER_SIGNALS = ['start', 'stop', 'restart', 'kill'];

    public function __construct(
        private readonly ApiClient $application,
        private readonly ?ApiClient $client,
    ) {
    }

    public function hasClientApi(): bool
    {
        return $this->client !== null;
    }

    public function healthCheck(): HealthResult
    {
        try {
            $nodes = $this->nodes();
        } catch (IntegrationException $exception) {
            return HealthResult::down($exception->getMessage());
        }

        $message = sprintf('%d node(s) available%s', count($nodes), $this->client === null ? ' (client API key not configured)' : '');

        return $nodes === [] ? HealthResult::degraded($message) : HealthResult::healthy($message, ['nodes' => $nodes]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nodes(): array
    {
        return $this->list('/api/application/nodes', 'nodes.list');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function locations(): array
    {
        return $this->list('/api/application/locations', 'locations.list');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nests(): array
    {
        return $this->list('/api/application/nests', 'nests.list');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function eggs(int $nestId): array
    {
        return $this->list('/api/application/nests/' . $this->id($nestId) . '/eggs', 'eggs.list');
    }

    /**
     * @return array{docker_image: string, startup: string, variables: array<string, string>}
     */
    public function egg(int $nestId, int $eggId): array
    {
        $response = $this->application->request('GET', '/api/application/nests/' . $this->id($nestId) . '/eggs/' . $this->id($eggId), [
            'query' => ['include' => 'variables'],
            'action' => 'eggs.show',
        ]);
        $attributes = $this->attributes($response['json']);

        $variables = [];
        foreach ($attributes['relationships']['variables']['data'] ?? [] as $variable) {
            $variableAttributes = $variable['attributes'] ?? [];
            if (isset($variableAttributes['env_variable'])) {
                $variables[(string) $variableAttributes['env_variable']] = (string) ($variableAttributes['default_value'] ?? '');
            }
        }

        return [
            'docker_image' => (string) ($attributes['docker_image'] ?? ''),
            'startup' => (string) ($attributes['startup'] ?? ''),
            'variables' => $variables,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUserByExternalId(string $externalId): ?array
    {
        return $this->findByExternalId('/api/application/users/external/', $externalId, 'users.find');
    }

    /**
     * @param array{email: string, username: string, first_name: string, last_name: string, external_id: string, password: string} $data
     * @return array<string, mixed>
     */
    public function createUser(array $data): array
    {
        return $this->attributes($this->application->request('POST', '/api/application/users', ['json' => $data, 'action' => 'users.create'])['json']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findServerByExternalId(string $externalId): ?array
    {
        return $this->findByExternalId('/api/application/servers/external/', $externalId, 'servers.find');
    }

    /**
     * @return array<string, mixed>
     */
    public function server(int $id): array
    {
        return $this->attributes($this->application->request('GET', '/api/application/servers/' . $this->id($id), ['query' => ['include' => 'allocations'], 'action' => 'servers.show'])['json']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createServer(array $data): array
    {
        return $this->attributes($this->application->request('POST', '/api/application/servers', ['json' => $data, 'action' => 'servers.create'])['json']);
    }

    public function suspendServer(int $id): void
    {
        $this->application->request('POST', '/api/application/servers/' . $this->id($id) . '/suspend', ['action' => 'servers.suspend']);
    }

    public function unsuspendServer(int $id): void
    {
        $this->application->request('POST', '/api/application/servers/' . $this->id($id) . '/unsuspend', ['action' => 'servers.unsuspend']);
    }

    public function deleteServer(int $id): void
    {
        $this->application->request('DELETE', '/api/application/servers/' . $this->id($id), ['action' => 'servers.delete']);
    }

    /**
     * @param array<string, mixed> $data allocation, memory, swap, disk, io, cpu, feature_limits
     */
    public function updateBuild(int $id, array $data): void
    {
        $this->application->request('PATCH', '/api/application/servers/' . $this->id($id) . '/build', ['json' => $data, 'action' => 'servers.build']);
    }

    /**
     * Live resources via the Client API.
     *
     * @return array<string, mixed>
     */
    public function resources(string $identifier): array
    {
        if ($this->client === null) {
            throw new IntegrationException('The client API key is not configured.');
        }

        return $this->attributes($this->client->request('GET', '/api/client/servers/' . $this->identifier($identifier) . '/resources', ['action' => 'client.resources'])['json']);
    }

    public function power(string $identifier, string $signal): void
    {
        if ($this->client === null) {
            throw new IntegrationException('The client API key is not configured.');
        }
        if (!in_array($signal, self::POWER_SIGNALS, true)) {
            throw new IntegrationException('Unsupported power signal.');
        }

        $this->client->request('POST', '/api/client/servers/' . $this->identifier($identifier) . '/power', ['json' => ['signal' => $signal], 'action' => 'client.power.' . $signal]);
    }

    /**
     * Backups of a server (Client API).
     *
     * @return list<array{uuid: string, name: string, bytes: int, created_at: string, completed: bool, successful: bool, locked: bool}>
     */
    public function backups(string $identifier): array
    {
        $json = $this->clientApi()->request('GET', '/api/client/servers/' . $this->identifier($identifier) . '/backups', ['query' => ['per_page' => 100], 'action' => 'client.backups.list'])['json'];
        $backups = [];
        foreach (is_array($json) ? ($json['data'] ?? []) : [] as $item) {
            $attributes = is_array($item) ? ($item['attributes'] ?? null) : null;
            if (!is_array($attributes) || !is_string($attributes['uuid'] ?? null) || !self::isUuid($attributes['uuid'])) {
                continue;
            }
            $backups[] = [
                'uuid' => $attributes['uuid'],
                'name' => mb_substr((string) ($attributes['name'] ?? ''), 0, 191),
                'bytes' => (int) ($attributes['bytes'] ?? 0),
                'created_at' => (string) ($attributes['created_at'] ?? ''),
                'completed' => ($attributes['completed_at'] ?? null) !== null,
                'successful' => (bool) ($attributes['is_successful'] ?? false),
                'locked' => (bool) ($attributes['is_locked'] ?? false),
            ];
        }

        return $backups;
    }

    public function createBackup(string $identifier, string $name): void
    {
        $this->clientApi()->request('POST', '/api/client/servers/' . $this->identifier($identifier) . '/backups', ['json' => ['name' => mb_substr($name, 0, 191)], 'action' => 'client.backups.create']);
    }

    public function deleteBackup(string $identifier, string $uuid): void
    {
        $this->clientApi()->request('DELETE', '/api/client/servers/' . $this->identifier($identifier) . '/backups/' . $this->uuid($uuid), ['action' => 'client.backups.delete']);
    }

    /** Restores a backup; existing files are deleted first (truncate). */
    public function restoreBackup(string $identifier, string $uuid): void
    {
        $this->clientApi()->request('POST', '/api/client/servers/' . $this->identifier($identifier) . '/backups/' . $this->uuid($uuid) . '/restore', ['json' => ['truncate' => true], 'action' => 'client.backups.restore']);
    }

    /** Short-lived signed download URL issued by the node (Wings). */
    public function backupDownloadUrl(string $identifier, string $uuid): string
    {
        $json = $this->clientApi()->request('GET', '/api/client/servers/' . $this->identifier($identifier) . '/backups/' . $this->uuid($uuid) . '/download', ['action' => 'client.backups.download'])['json'];
        $url = (string) $this->attributes($json)['url'];
        if (preg_match('#^https?://[^\s/?\#]+#i', $url) !== 1) {
            throw new IntegrationException('Pterodactyl did not return a valid download URL.');
        }

        return $url;
    }

    private function clientApi(): ApiClient
    {
        return $this->client ?? throw new IntegrationException('The client API key is not configured.');
    }

    private static function isUuid(string $uuid): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid) === 1;
    }

    private function uuid(string $uuid): string
    {
        if (!self::isUuid($uuid)) {
            throw new IntegrationException('Invalid backup ID.');
        }

        return $uuid;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByExternalId(string $prefix, string $externalId, string $action): ?array
    {
        if (preg_match('/^[A-Za-z0-9._-]{1,191}$/', $externalId) !== 1) {
            throw new IntegrationException('Invalid external ID.');
        }

        try {
            return $this->attributes($this->application->request('GET', $prefix . $externalId, ['action' => $action])['json']);
        } catch (IntegrationException $exception) {
            if ($exception->isNotFound()) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * Fetches all pages of a list endpoint.
     *
     * @return list<array<string, mixed>>
     */
    private function list(string $path, string $action): array
    {
        $items = [];
        $page = 1;
        do {
            $json = $this->application->request('GET', $path, ['query' => ['page' => $page, 'per_page' => 100], 'action' => $action])['json'];
            foreach (is_array($json) ? ($json['data'] ?? []) : [] as $item) {
                if (is_array($item) && isset($item['attributes']) && is_array($item['attributes'])) {
                    $items[] = $item['attributes'];
                }
            }
            $totalPages = is_array($json) ? (int) ($json['meta']['pagination']['total_pages'] ?? 1) : 1;
            $page++;
        } while ($page <= $totalPages && $page <= 50);

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(mixed $json): array
    {
        if (!is_array($json) || !isset($json['attributes']) || !is_array($json['attributes'])) {
            throw new IntegrationException('Unexpected response from Pterodactyl.');
        }

        return $json['attributes'];
    }

    private function id(int $id): int
    {
        if ($id < 1) {
            throw new IntegrationException('Invalid ID.');
        }

        return $id;
    }

    private function identifier(string $identifier): string
    {
        if (preg_match('/^[a-f0-9]{8}(-[a-f0-9-]{27})?$/', $identifier) !== 1) {
            throw new IntegrationException('Invalid server identifier.');
        }

        return $identifier;
    }
}
