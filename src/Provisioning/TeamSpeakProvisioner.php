<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Auth\User;
use Nebrix\Database\Connection;
use Nebrix\Integration\Integration;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\TeamSpeak\ServerQueryClient;
use Nebrix\Integration\TeamSpeak\ServerQueryException;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;

/**
 * Virtual TeamSpeak 3 servers.
 *
 * Idempotency: the voice port is reserved in the database (under a lock,
 * excluding ports reserved by other services of the integration) before
 * "servercreate". A retried job adopts a virtual server on that port only if
 * it carries the name this service requested; anything else on the port is
 * treated as a conflict instead of being taken over.
 */
final class TeamSpeakProvisioner implements Provisioner
{
    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly ServiceRepository $services,
        private readonly Connection $db,
    ) {
    }

    /**
     * @return array{0: Integration, 1: ServerQueryClient}
     */
    private function client(Service $service): array
    {
        if ($service->integrationId === null) {
            throw new IntegrationException('No TeamSpeak integration is assigned to this service.');
        }
        $integration = $this->integrations->get($service->integrationId);

        return [$integration, $this->integrations->teamspeak($integration)];
    }

    public function provision(Service $service, User $owner): ProvisionResult
    {
        [$integration, $client] = $this->client($service);
        $remote = $service->remote;

        try {
            $servers = $client->listServers();

            // Already created (sid known and present)?
            if (isset($remote['sid'])) {
                foreach ($servers as $server) {
                    if ((int) ($server['virtualserver_id'] ?? 0) === (int) $remote['sid']) {
                        return $this->result($integration, $service, (int) $remote['sid'], (int) $server['virtualserver_port']);
                    }
                }
            }

            // Created by an earlier attempt before the sid was stored?
            if (isset($remote['port'])) {
                foreach ($servers as $server) {
                    if ((int) ($server['virtualserver_port'] ?? 0) !== (int) $remote['port']) {
                        continue;
                    }
                    if ((string) ($server['virtualserver_name'] ?? '') !== $this->serverName($service)) {
                        throw new IntegrationException(sprintf(
                            'Voice port %d reserved for this service is used by another virtual server (ID %d). Resolve the conflict on the TeamSpeak server, then retry.',
                            (int) $remote['port'],
                            (int) ($server['virtualserver_id'] ?? 0),
                        ));
                    }
                    $this->services->mergeRemote($service->id, ['sid' => (int) $server['virtualserver_id']]);

                    return $this->result($integration, $service, (int) $server['virtualserver_id'], (int) $remote['port']);
                }
            }

            $used = array_map(static fn (array $s): int => (int) ($s['virtualserver_port'] ?? 0), $servers);
            $port = isset($remote['port']) ? (int) $remote['port'] : $this->db->withLock(
                'nebrix:ts3-port:' . $integration->id,
                10,
                function () use ($integration, $service, $used): int {
                    $reserved = $this->services->reservedRemoteValues($integration->id, 'port', $service->id);
                    $port = $this->freePort($integration, [...$used, ...$reserved]);
                    $this->services->mergeRemote($service->id, ['port' => $port]);

                    return $port;
                },
            );

            $created = $client->createServer($this->serverName($service), (int) $service->config('slots', 32), $port);
            $this->services->mergeRemote($service->id, ['sid' => $created['sid'], 'port' => $created['port']]);

            $credentials = [
                'host' => (string) $integration->config('voice_host'),
                'port' => $created['port'],
                'privilege_key' => $created['token'],
            ];
            $this->services->setCredentials($service->id, $credentials);

            return new ProvisionResult('ts3:' . $created['sid'], ['sid' => $created['sid'], 'port' => $created['port']], $credentials);
        } finally {
            $client->disconnect();
        }
    }

    private function serverName(Service $service): string
    {
        // Same truncation as ServerQueryClient::createServer().
        return mb_substr((string) $service->option('server_name', 'TeamSpeak #' . $service->id), 0, 64);
    }

    private function result(Integration $integration, Service $service, int $sid, int $port): ProvisionResult
    {
        $credentials = $this->services->credentials($service);
        if ($credentials === []) {
            $credentials = ['host' => (string) $integration->config('voice_host'), 'port' => $port];
            $this->services->setCredentials($service->id, $credentials);
        }

        return new ProvisionResult('ts3:' . $sid, ['sid' => $sid, 'port' => $port], $credentials);
    }

    /**
     * @param list<int> $used
     */
    private function freePort(Integration $integration, array $used): int
    {
        $min = (int) $integration->config('port_min', 9987);
        $max = (int) $integration->config('port_max', 10986);
        for ($port = $min; $port <= $max; $port++) {
            if (!in_array($port, $used, true)) {
                return $port;
            }
        }

        throw new IntegrationException('No free voice port is left in the configured range.');
    }

    public function suspend(Service $service): void
    {
        $this->withServer($service, function (ServerQueryClient $client, int $sid): void {
            $client->editServer($sid, ['virtualserver_autostart' => 0]);
            $this->stop($client, $sid);
        });
    }

    public function unsuspend(Service $service): void
    {
        $this->withServer($service, static function (ServerQueryClient $client, int $sid): void {
            $client->startServer($sid);
            $client->editServer($sid, ['virtualserver_autostart' => 1]);
        });
    }

    public function terminate(Service $service): void
    {
        if (!isset($service->remote['sid'])) {
            return;
        }

        [, $client] = $this->client($service);
        try {
            $sid = (int) $service->remote['sid'];
            $exists = false;
            foreach ($client->listServers() as $server) {
                $exists = $exists || (int) ($server['virtualserver_id'] ?? 0) === $sid;
            }
            if ($exists) {
                $this->stop($client, $sid);
                $client->deleteServer($sid);
            }
        } finally {
            $client->disconnect();
        }
    }

    private function stop(ServerQueryClient $client, int $sid): void
    {
        try {
            $client->stopServer($sid);
        } catch (ServerQueryException $exception) {
            if ($exception->queryErrorId() !== ServerQueryException::SERVER_NOT_RUNNING) {
                throw $exception;
            }
        }
    }

    /**
     * @param callable(ServerQueryClient, int): void $operation
     */
    private function withServer(Service $service, callable $operation): void
    {
        if (!isset($service->remote['sid'])) {
            throw new IntegrationException('The TeamSpeak server has not been created yet.');
        }

        [, $client] = $this->client($service);
        try {
            $operation($client, (int) $service->remote['sid']);
        } finally {
            $client->disconnect();
        }
    }

    public function status(Service $service): array
    {
        [$integration, $client] = $this->client($service);
        try {
            foreach ($client->listServers() as $server) {
                if ((int) ($server['virtualserver_id'] ?? 0) === (int) $service->remote('sid')) {
                    return [
                        'state' => (string) ($server['virtualserver_status'] ?? 'unknown'),
                        'clients_online' => (int) ($server['virtualserver_clientsonline'] ?? 0),
                        'slots' => (int) ($server['virtualserver_maxclients'] ?? 0),
                        'uptime' => (int) ($server['virtualserver_uptime'] ?? 0),
                        'connection' => ['Address' => (string) $integration->config('voice_host') . ':' . (int) ($server['virtualserver_port'] ?? 0)],
                    ];
                }
            }
        } finally {
            $client->disconnect();
        }

        return ['state' => 'missing'];
    }

    public function actions(Service $service): array
    {
        return [
            'start' => ServiceAction::simple('Start'),
            'stop' => ServiceAction::simple('Stop', 'Stop the server? Connected users are disconnected.'),
            'restart' => ServiceAction::simple('Restart', 'Restart the server? Connected users are disconnected.'),
            'privilege_key' => ServiceAction::simple('Create admin privilege key'),
        ];
    }

    public function perform(Service $service, string $action, array $input = []): string
    {
        if (!array_key_exists($action, $this->actions($service))) {
            throw new IntegrationException('Unsupported action.');
        }

        $message = 'The action has been executed.';
        $this->withServer($service, function (ServerQueryClient $client, int $sid) use ($action, $service, &$message): void {
            match ($action) {
                'start' => $client->startServer($sid),
                'stop' => $this->stop($client, $sid),
                'restart' => (function () use ($client, $sid): void {
                    $this->stop($client, $sid);
                    $client->startServer($sid);
                })(),
                'privilege_key' => (function () use ($client, $sid, $service, &$message): void {
                    $key = $client->createPrivilegeKey($sid, 'Nebrix customer key');
                    $this->services->setCredentials($service->id, ['privilege_key' => $key] + $this->services->credentials($service));
                    $message = 'A new privilege key has been created. You can view it under access data.';
                })(),
                default => null,
            };
        });

        return $message;
    }

    public function prepareReinstall(Service $service, array $input): void
    {
        throw new IntegrationException('Reinstalling is not supported for this service type.');
    }
}
