<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration\Integrations;

use GuzzleHttp\Psr7\Response;
use Nebrix\Integration\HealthResult;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Provisioning\NextcloudProvisioner;
use Nebrix\Provisioning\PterodactylProvisioner;
use Nebrix\Provisioning\TeamSpeakProvisioner;
use Nebrix\Service\ServiceRepository;
use Nebrix\Tests\Support\FakeHttp;

final class PterodactylNextcloudTeamSpeakTest extends IntegrationTestCase
{
    // ------------------------------------------------------------ Pterodactyl

    /** @var array<string, array<string, mixed>> */
    private array $pteroUsers = [];

    /** @var array<string, array<string, mixed>> */
    private array $pteroServers = [];

    private function pterodactyl(bool $withClientKey = false): int
    {
        $secrets = ['application_api_key' => 'ptla_' . str_repeat('a', 40)];
        if ($withClientKey) {
            $secrets['client_api_key'] = 'ptlc_' . str_repeat('b', 40);
        }
        $id = $this->createIntegration('pterodactyl', ['base_url' => 'https://203.0.113.20', 'verify_tls' => true], $secrets);

        $notFound = new Response(404, [], json_encode(['errors' => [['code' => 'NotFoundHttpException', 'detail' => 'The requested resource could not be found.']]], JSON_THROW_ON_ERROR));
        $this->http
            ->on('GET', '/api/application/nodes', ['object' => 'list', 'data' => [['object' => 'node', 'attributes' => ['id' => 1, 'name' => 'node1']]], 'meta' => ['pagination' => ['total_pages' => 1]]])
            ->on('GET', '/api/application/users/external/(?<ext>[^/]+)', fn ($r, array $m): Response => isset($this->pteroUsers[$m['ext']])
                ? new Response(200, [], json_encode(['attributes' => $this->pteroUsers[$m['ext']]], JSON_THROW_ON_ERROR)) : $notFound)
            ->on('POST', '/api/application/users', function ($request): Response {
                $data = FakeHttp::json($request);
                $this->pteroUsers[$data['external_id']] = ['id' => 77, 'username' => $data['username'], 'email' => $data['email']];

                return new Response(201, [], json_encode(['attributes' => $this->pteroUsers[$data['external_id']]], JSON_THROW_ON_ERROR));
            })
            ->on('GET', '/api/application/servers/external/(?<ext>[^/]+)', fn ($r, array $m): Response => isset($this->pteroServers[$m['ext']])
                ? new Response(200, [], json_encode(['attributes' => $this->pteroServers[$m['ext']]], JSON_THROW_ON_ERROR)) : $notFound)
            ->on('GET', '/api/application/nests/1/eggs/5', ['attributes' => [
                'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21',
                'startup' => 'java -Xms128M -jar {{SERVER_JARFILE}}',
                'relationships' => ['variables' => ['data' => [
                    ['attributes' => ['env_variable' => 'SERVER_JARFILE', 'default_value' => 'server.jar']],
                    ['attributes' => ['env_variable' => 'VERSION', 'default_value' => 'latest']],
                ]]],
            ]])
            ->on('POST', '/api/application/servers', function ($request): Response {
                $data = FakeHttp::json($request);
                $this->pteroServers[$data['external_id']] = ['id' => 900 + count($this->pteroServers), 'identifier' => '1a7ce997', 'uuid' => '1a7ce997-259b-452e-8b4e-cecc464142ca', 'suspended' => false];

                return new Response(201, [], json_encode(['attributes' => $this->pteroServers[$data['external_id']]], JSON_THROW_ON_ERROR));
            })
            ->on('POST', '/api/application/servers/(?<id>\d+)/suspend', [], 204)
            ->on('DELETE', '/api/application/servers/(?<id>\d+)', [], 204)
            ->on('GET', '/api/client/servers/1a7ce997/resources', ['attributes' => ['current_state' => 'running', 'resources' => ['memory_bytes' => 1048576, 'cpu_absolute' => 42.42, 'disk_bytes' => 2048, 'uptime' => 60000]]])
            ->on('POST', '/api/client/servers/1a7ce997/power', [], 204);

        return $id;
    }

    public function testPterodactylProvisioningIsIdempotent(): void
    {
        $integrationId = $this->pterodactyl();
        $owner = $this->createUser();
        $config = ['nest_id' => 1, 'egg_id' => 5, 'location_id' => 2, 'memory_mb' => 4096, 'swap_mb' => 0, 'disk_mb' => 20480, 'cpu_percent' => 200, 'io' => 500, 'databases' => 1, 'allocations' => 1, 'backups' => 2, 'environment' => '{"VERSION": "1.21.1"}', '_options' => ['server_name' => 'Survival']];
        $service = $this->createService($owner, 'minecraft', $integrationId, $config);
        $provisioner = $this->app->container()->get(PterodactylProvisioner::class);

        $result = $provisioner->provision($service, $owner);

        self::assertSame('900', $result->externalId);
        $create = FakeHttp::json($this->http->requests('POST', '/api/application/servers')[0]);
        self::assertSame('nebrix-service-' . $service->id, $create['external_id']);
        self::assertSame(77, $create['user']);
        self::assertSame(['locations' => [2], 'dedicated_ip' => false, 'port_range' => []], $create['deploy']);
        self::assertSame(['SERVER_JARFILE' => 'server.jar', 'VERSION' => '1.21.1'], $create['environment']);
        self::assertSame(4096, $create['limits']['memory']);
        self::assertSame('Bearer ptla_' . str_repeat('a', 40), $this->http->requests('POST', '/api/application/servers')[0]->getHeaderLine('Authorization'));
        self::assertSame('nx' . $owner->id, $result->credentials['username']);

        // Retry after success (e.g. manual retry): nothing new is created.
        $provisioner->provision($this->reload($service->id), $owner);
        self::assertCount(1, $this->http->requests('POST', '/api/application/servers'));
        self::assertCount(1, $this->http->requests('POST', '/api/application/users'));

        // A second game server of the same customer reuses the panel account.
        $second = $this->createService($owner, 'minecraft', $integrationId, $config);
        $secondResult = $provisioner->provision($second, $owner);
        self::assertCount(1, $this->http->requests('POST', '/api/application/users'));
        self::assertSame($result->credentials['password'], $secondResult->credentials['password']);

        $provisioner->suspend($this->reload($service->id));
        self::assertCount(1, $this->http->requests('POST', '/api/application/servers/900/suspend'));

        $provisioner->terminate($this->reload($service->id));
        self::assertCount(1, $this->http->requests('DELETE', '/api/application/servers/900'));
    }

    public function testPterodactylClientApiFeatures(): void
    {
        $integrationId = $this->pterodactyl(true);
        $owner = $this->createUser();
        $service = $this->createService($owner, 'minecraft', $integrationId, ['memory_mb' => 1024, 'disk_mb' => 1024]);
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($service->id, ['server_id' => 900, 'identifier' => '1a7ce997']);
        $provisioner = $this->app->container()->get(PterodactylProvisioner::class);

        $status = $provisioner->status($this->reload($service->id));
        self::assertSame('running', $status['state']);
        self::assertSame(42.4, $status['cpu_percent']);
        self::assertArrayHasKey('restart', $provisioner->actions($this->reload($service->id)));

        $provisioner->perform($this->reload($service->id), 'restart');
        $power = $this->http->requests('POST', '/api/client/servers/1a7ce997/power')[0];
        self::assertSame(['signal' => 'restart'], FakeHttp::json($power));
        self::assertSame('Bearer ptlc_' . str_repeat('b', 40), $power->getHeaderLine('Authorization'));
    }

    public function testPterodactylHealthCheck(): void
    {
        $manager = $this->app->container()->get(IntegrationManager::class);
        $result = $manager->testConnection($manager->get($this->pterodactyl()));

        self::assertSame(HealthResult::HEALTHY, $result->status);
        self::assertStringContainsString('client API key not configured', $result->message);
    }

    // -------------------------------------------------------------- Nextcloud

    /** @var array<string, array<string, mixed>> */
    private array $cloudUsers = [];

    private function nextcloud(): int
    {
        $id = $this->createIntegration('nextcloud', ['base_url' => 'https://203.0.113.30', 'admin_user' => 'admin', 'user_prefix' => 'nx', 'verify_tls' => true], ['admin_password' => 'app-pass-123']);
        $ocs = static fn (int $status, mixed $data = [], int $http = 200): Response => new Response($http, [], json_encode(['ocs' => ['meta' => ['status' => $status === 200 ? 'ok' : 'failure', 'statuscode' => $status, 'message' => $status === 200 ? 'OK' : 'User does not exist'], 'data' => $data]], JSON_THROW_ON_ERROR));

        $this->http
            ->on('GET', '/status.php', ['installed' => true, 'maintenance' => false, 'versionstring' => '30.0.2'])
            ->on('GET', '/ocs/v2.php/cloud/users', fn (): Response => $ocs(200, ['users' => []]))
            ->on('GET', '/ocs/v2.php/cloud/users/(?<id>[^/]+)', fn ($r, array $m): Response => isset($this->cloudUsers[$m['id']])
                ? $ocs(200, $this->cloudUsers[$m['id']]) : $ocs(404, [], 404))
            ->on('POST', '/ocs/v2.php/cloud/users', function ($request) use ($ocs): Response {
                $form = FakeHttp::form($request);
                $this->cloudUsers[$form['userid']] = ['id' => $form['userid'], 'enabled' => true, 'email' => $form['email'], 'quota' => ['used' => 1024, 'quota' => 107374182400, 'relative' => 0.01]];

                return $ocs(200, ['id' => $form['userid']]);
            })
            ->on('PUT', '/ocs/v2.php/cloud/users/(?<id>[^/]+)/(?<op>disable|enable)', function ($r, array $m) use ($ocs): Response {
                $this->cloudUsers[$m['id']]['enabled'] = $m['op'] === 'enable';

                return $ocs(200);
            })
            ->on('PUT', '/ocs/v2.php/cloud/users/(?<id>[^/]+)', fn (): Response => $ocs(200))
            ->on('DELETE', '/ocs/v2.php/cloud/users/(?<id>[^/]+)', function ($r, array $m) use ($ocs): Response {
                unset($this->cloudUsers[$m['id']]);

                return $ocs(200);
            });

        return $id;
    }

    public function testNextcloudLifecycle(): void
    {
        $integrationId = $this->nextcloud();
        $owner = $this->createUser();
        $service = $this->createService($owner, 'nextcloud', $integrationId, ['quota_gb' => 100, 'group' => 'customers']);
        $provisioner = $this->app->container()->get(NextcloudProvisioner::class);
        $userId = 'nx' . $service->id;

        $result = $provisioner->provision($service, $owner);
        self::assertSame($userId, $result->externalId);
        $create = FakeHttp::form($this->http->requests('POST', '/ocs/v2.php/cloud/users')[0]);
        self::assertSame('100 GB', $create['quota']);
        self::assertSame($owner->email, $create['email']);
        self::assertSame(['customers'], $create['groups']);
        self::assertSame($create['password'], $result->credentials['password']);
        self::assertSame('true', $this->http->last()->getHeaderLine('OCS-APIRequest'));
        self::assertSame('Basic ' . base64_encode('admin:app-pass-123'), $this->http->last()->getHeaderLine('Authorization'));

        // Idempotent: a retry does not create a second account.
        $provisioner->provision($this->reload($service->id), $owner);
        self::assertCount(1, $this->http->requests('POST', '/ocs/v2.php/cloud/users'));

        $provisioner->suspend($this->reload($service->id));
        self::assertFalse($this->cloudUsers[$userId]['enabled']);
        $provisioner->unsuspend($this->reload($service->id));
        self::assertTrue($this->cloudUsers[$userId]['enabled']);

        $status = $provisioner->status($this->reload($service->id));
        self::assertSame('active', $status['state']);
        self::assertSame(1024, $status['storage_used']);

        $provisioner->terminate($this->reload($service->id));
        self::assertArrayNotHasKey($userId, $this->cloudUsers);
        // Terminating again is a no-op.
        $provisioner->terminate($this->reload($service->id));
    }

    public function testNextcloudHealthCheck(): void
    {
        $manager = $this->app->container()->get(IntegrationManager::class);
        $result = $manager->testConnection($manager->get($this->nextcloud()));

        self::assertSame(HealthResult::HEALTHY, $result->status);
        self::assertStringContainsString('Nextcloud 30.0.2', $result->message);
    }

    // -------------------------------------------------------------- TeamSpeak

    private function teamspeakIntegration(): int
    {
        return $this->createIntegration('teamspeak', ['host' => '203.0.113.40', 'query_port' => 10011, 'query_user' => 'serveradmin', 'voice_host' => 'ts.example.com', 'port_min' => 9987, 'port_max' => 9989], ['query_password' => 'query-pass']);
    }

    public function testTeamSpeakProvisioningSuspensionAndTermination(): void
    {
        $integrationId = $this->teamspeakIntegration();
        $this->teamspeak->servers[$this->teamspeak->nextSid++] = ['port' => 9987, 'name' => 'Existing', 'slots' => 10, 'status' => 'online', 'autostart' => 1];
        $owner = $this->createUser();
        $service = $this->createService($owner, 'teamspeak', $integrationId, ['slots' => 32, '_options' => ['server_name' => 'Clan | Server']]);
        $provisioner = $this->app->container()->get(TeamSpeakProvisioner::class);

        $result = $provisioner->provision($service, $owner);

        self::assertSame('ts3:2', $result->externalId);
        self::assertSame(['host' => 'ts.example.com', 'port' => 9988, 'privilege_key' => 'TOKEN2/abc'], $result->credentials);
        self::assertSame('Clan | Server', $this->teamspeak->servers[2]['name']);
        self::assertSame(32, $this->teamspeak->servers[2]['slots']);

        // Retrying never creates a second virtual server.
        $provisioner->provision($this->reload($service->id), $owner);
        self::assertCount(2, $this->teamspeak->servers);

        $provisioner->suspend($this->reload($service->id));
        self::assertSame('offline', $this->teamspeak->servers[2]['status']);
        self::assertSame(0, $this->teamspeak->servers[2]['autostart']);
        // Suspending an already stopped server is harmless.
        $provisioner->suspend($this->reload($service->id));

        $provisioner->unsuspend($this->reload($service->id));
        self::assertSame('online', $this->teamspeak->servers[2]['status']);

        self::assertStringContainsString('privilege key', $provisioner->perform($this->reload($service->id), 'privilege_key'));
        self::assertSame('NEWKEY6', $this->app->container()->get(ServiceRepository::class)->credentials($this->reload($service->id))['privilege_key']);

        $provisioner->terminate($this->reload($service->id));
        self::assertArrayNotHasKey(2, $this->teamspeak->servers);
        self::assertArrayHasKey(1, $this->teamspeak->servers, 'Other virtual servers are untouched.');
    }

    public function testTeamSpeakAdoptsServerCreatedBeforeACrash(): void
    {
        $integrationId = $this->teamspeakIntegration();
        $owner = $this->createUser();
        $service = $this->createService($owner, 'teamspeak', $integrationId, ['slots' => 10]);
        // An earlier attempt stored the port and created the server, then crashed before storing the sid.
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($service->id, ['port' => 9989]);
        $this->teamspeak->servers[5] = ['port' => 9989, 'name' => 'TeamSpeak #' . $service->id, 'slots' => 10, 'status' => 'online', 'autostart' => 1];

        $result = $this->app->container()->get(TeamSpeakProvisioner::class)->provision($this->reload($service->id), $owner);

        self::assertSame('ts3:5', $result->externalId);
        self::assertCount(1, $this->teamspeak->servers);
    }

    public function testTeamSpeakNeverAdoptsAForeignServerOnTheReservedPort(): void
    {
        $integrationId = $this->teamspeakIntegration();
        $owner = $this->createUser();
        $service = $this->createService($owner, 'teamspeak', $integrationId, ['slots' => 10, '_options' => ['server_name' => 'Mine']]);
        // The earlier servercreate failed; meanwhile someone else's server appeared on the reserved port.
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($service->id, ['port' => 9989]);
        $this->teamspeak->servers[7] = ['port' => 9989, 'name' => 'Other customer', 'slots' => 10, 'status' => 'online', 'autostart' => 1];

        try {
            $this->app->container()->get(TeamSpeakProvisioner::class)->provision($this->reload($service->id), $owner);
            self::fail('A foreign server must not be adopted.');
        } catch (IntegrationException $exception) {
            self::assertStringContainsString('used by another virtual server', $exception->getMessage());
        }

        self::assertArrayNotHasKey('sid', $this->reload($service->id)->remote);
        self::assertCount(1, $this->teamspeak->servers);
    }

    public function testTeamSpeakSkipsPortsReservedByOtherServices(): void
    {
        $integrationId = $this->teamspeakIntegration();
        $owner = $this->createUser();
        // Service A reserved 9987, but its servercreate failed (no server exists yet).
        $first = $this->createService($owner, 'teamspeak', $integrationId, ['slots' => 10]);
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($first->id, ['port' => 9987]);

        $second = $this->createService($this->createUser(), 'teamspeak', $integrationId, ['slots' => 10]);
        $result = $this->app->container()->get(TeamSpeakProvisioner::class)->provision($second, $owner);

        self::assertSame(9988, $result->credentials['port'], 'The port reserved by service A is not handed out again.');
        self::assertSame(9988, (int) $this->reload($second->id)->remote['port']);

        // A's retry creates its own server on its reserved port.
        $retry = $this->app->container()->get(TeamSpeakProvisioner::class)->provision($this->reload($first->id), $owner);
        self::assertSame(9987, $retry->credentials['port']);
        self::assertCount(2, $this->teamspeak->servers);
    }

    public function testTeamSpeakFailsWhenNoPortIsLeft(): void
    {
        $integrationId = $this->teamspeakIntegration();
        foreach ([9987, 9988, 9989] as $sid => $port) {
            $this->teamspeak->servers[$sid + 1] = ['port' => $port, 'name' => 'x', 'slots' => 1, 'status' => 'online', 'autostart' => 1];
        }
        $owner = $this->createUser();

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('No free voice port');
        $this->app->container()->get(TeamSpeakProvisioner::class)->provision($this->createService($owner, 'teamspeak', $integrationId, ['slots' => 5]), $owner);
    }

    public function testDisabledIntegrationsCannotProvision(): void
    {
        $integrationId = $this->createIntegration('nextcloud', ['base_url' => 'https://203.0.113.30', 'admin_user' => 'admin', 'user_prefix' => 'nx'], ['admin_password' => 'x'], false);
        $owner = $this->createUser();

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('is disabled');
        $this->app->container()->get(NextcloudProvisioner::class)->provision($this->createService($owner, 'nextcloud', $integrationId, ['quota_gb' => 1]), $owner);
    }

    public function testLegacyGameServerIsOnlyDeletedWithTheRecordedIdentifier(): void
    {
        $integrationId = $this->pterodactyl();
        $this->http->on('GET', '/api/application/servers/(?<id>\\d+)', fn ($r, array $m): Response => new Response(200, ['Content-Type' => 'application/json'], json_encode(['object' => 'server', 'attributes' => ['id' => (int) $m['id'], 'identifier' => $m['id'] === '41' ? 'abcd1234' : 'ffff0000']], JSON_THROW_ON_ERROR)));
        $owner = $this->createUser();
        $provisioner = $this->app->container()->get(PterodactylProvisioner::class);
        $services = $this->app->container()->get(\Nebrix\Service\ServiceRepository::class);

        // Server ID 42 now belongs to someone else (ID reused after the legacy server was deleted).
        $reused = $this->createService($owner, 'minecraft', $integrationId, []);
        $services->mergeRemote($reused->id, ['legacy' => true, 'server_id' => 42, 'identifier' => 'abcd1234']);
        try {
            $provisioner->terminate($this->reload($reused->id));
            self::fail('Server with another identifier deleted.');
        } catch (\Nebrix\Integration\IntegrationException $exception) {
            self::assertStringContainsString('another identifier', $exception->getMessage());
        }
        self::assertSame([], $this->http->requests('DELETE', '/api/application/servers/42'));

        $legacy = $this->createService($owner, 'minecraft', $integrationId, []);
        $services->mergeRemote($legacy->id, ['legacy' => true, 'server_id' => 41, 'identifier' => 'abcd1234']);
        $provisioner->terminate($this->reload($legacy->id));
        self::assertCount(1, $this->http->requests('DELETE', '/api/application/servers/41'));
    }
}
