<?php

declare(strict_types=1);

namespace Nebrix\Integration;

use Closure;
use GuzzleHttp\ClientInterface;
use Nebrix\Core\Config;
use Nebrix\Domain\Registrar;
use Nebrix\Integration\Hetzner\RobotClient;
use Nebrix\Integration\Http\ApiClient;
use Nebrix\Integration\Inwx\InwxClient;
use Nebrix\Integration\Plesk\PleskClient;
use Nebrix\Integration\Nextcloud\NextcloudClient;
use Nebrix\Integration\Proxmox\ProxmoxClient;
use Nebrix\Integration\Pterodactyl\PterodactylClient;
use Nebrix\Integration\TeamSpeak\ServerQueryClient;
use Nebrix\Integration\TeamSpeak\SocketTransport;
use Nebrix\Integration\TeamSpeak\Transport;
use Nebrix\Security\UnsafeUrlException;
use Nebrix\Security\UrlGuard;
use Throwable;

/**
 * Builds API clients for configured integrations and runs health checks.
 */
class IntegrationManager
{
    /** @var Closure(string, int): Transport */
    private Closure $transportFactory;

    /**
     * @param (Closure(string, int): Transport)|null $transportFactory TeamSpeak transport factory (tests)
     */
    public function __construct(
        private readonly IntegrationRepository $integrations,
        private readonly ClientInterface $http,
        private readonly UrlGuard $guard,
        private readonly IntegrationLogger $logger,
        private readonly Config $config,
        ?Closure $transportFactory = null,
    ) {
        $this->transportFactory = $transportFactory
            ?? fn (string $ip, int $port): Transport => new SocketTransport($ip, $port, $this->config->int('integrations.timeout', 20));
    }

    public function get(int $id): Integration
    {
        $integration = $this->integrations->find($id);
        if ($integration === null) {
            throw new IntegrationException('The integration no longer exists.');
        }

        return $integration;
    }

    public function proxmox(Integration $integration, bool $allowDisabled = false): ProxmoxClient
    {
        $this->assertType($integration, IntegrationType::PROXMOX, $allowDisabled);

        return new ProxmoxClient($this->api($integration, [
            'Authorization' => sprintf('PVEAPIToken=%s=%s', (string) $integration->config('token_id'), $integration->secret('token_secret')),
        ]));
    }

    public function pterodactyl(Integration $integration, bool $allowDisabled = false): PterodactylClient
    {
        $this->assertType($integration, IntegrationType::PTERODACTYL, $allowDisabled);
        $headers = ['Accept' => 'Application/vnd.pterodactyl.v1+json', 'Content-Type' => 'application/json'];

        return new PterodactylClient(
            $this->api($integration, ['Authorization' => 'Bearer ' . $integration->secret('application_api_key')] + $headers),
            $integration->hasSecret('client_api_key')
                ? $this->api($integration, ['Authorization' => 'Bearer ' . $integration->secret('client_api_key')] + $headers)
                : null,
        );
    }

    public function nextcloud(Integration $integration, bool $allowDisabled = false): NextcloudClient
    {
        $this->assertType($integration, IntegrationType::NEXTCLOUD, $allowDisabled);

        return new NextcloudClient($this->api($integration, [
            'Authorization' => 'Basic ' . base64_encode((string) $integration->config('admin_user') . ':' . $integration->secret('admin_password')),
        ]));
    }

    public function teamspeak(Integration $integration, bool $allowDisabled = false): ServerQueryClient
    {
        $this->assertType($integration, IntegrationType::TEAMSPEAK, $allowDisabled);

        try {
            $ip = $this->guard->validateHost((string) $integration->config('host'));
        } catch (UnsafeUrlException $exception) {
            throw new IntegrationException('ServerQuery host rejected: ' . $exception->getMessage());
        }

        return new ServerQueryClient(
            ($this->transportFactory)($ip, (int) $integration->config('query_port', 10011)),
            (string) $integration->config('query_user', 'serveradmin'),
            $integration->secret('query_password'),
            $this->logger,
            $integration->id,
        );
    }

    public function hetznerRobot(Integration $integration, bool $allowDisabled = false): RobotClient
    {
        $this->assertType($integration, IntegrationType::HETZNER_ROBOT, $allowDisabled);

        return new RobotClient($this->api($integration, [
            'Authorization' => 'Basic ' . base64_encode((string) $integration->config('username') . ':' . $integration->secret('password')),
        ]));
    }

    public function plesk(Integration $integration, bool $allowDisabled = false): PleskClient
    {
        $this->assertType($integration, IntegrationType::PLESK, $allowDisabled);

        return new PleskClient($this->api($integration, ['KEY' => $integration->secret('secret_key')]));
    }

    /**
     * Domain registrar for an integration (currently INWX).
     */
    public function registrar(Integration $integration, bool $allowDisabled = false): Registrar
    {
        $this->assertType($integration, IntegrationType::INWX, $allowDisabled);
        $endpoint = InwxClient::ENDPOINTS[(string) $integration->config('environment', 'ote')] ?? InwxClient::ENDPOINTS['ote'];

        return new InwxClient(
            $this->api($integration, [], $endpoint),
            (string) $integration->config('username'),
            $integration->secret('password'),
            $integration->hasSecret('totp_secret') ? $integration->secret('totp_secret') : null,
        );
    }

    public function testConnection(Integration $integration): HealthResult
    {
        try {
            $result = match ($integration->type) {
                IntegrationType::PROXMOX => $this->proxmox($integration, true)->healthCheck(),
                IntegrationType::PTERODACTYL => $this->pterodactyl($integration, true)->healthCheck(),
                IntegrationType::NEXTCLOUD => $this->nextcloud($integration, true)->healthCheck(),
                IntegrationType::TEAMSPEAK => $this->teamspeak($integration, true)->healthCheck(),
                IntegrationType::HETZNER_ROBOT => $this->hetznerRobot($integration, true)->healthCheck((string) $integration->config('pool_prefix', 'nebrix-free')),
                IntegrationType::PLESK => $this->plesk($integration, true)->healthCheck(),
                IntegrationType::INWX => $this->registrar($integration, true)->healthCheck(),
                default => HealthResult::down('Unsupported integration type.'),
            };
        } catch (Throwable $exception) {
            $result = HealthResult::down($exception instanceof IntegrationException ? $exception->getMessage() : 'Unexpected error during the connection test.');
        }

        $this->integrations->recordHealth($integration->id, $result);
        $this->logger->log($integration->id, $result->isHealthy() ? 'info' : 'warning', 'health_check', $result->status . ': ' . $result->message);

        return $result;
    }

    /**
     * Health check of every enabled integration (scheduler task).
     *
     * @return array<int, HealthResult>
     */
    public function checkAll(): array
    {
        $results = [];
        foreach ($this->integrations->enabledIds() as $id) {
            $integration = $this->integrations->find($id);
            if ($integration !== null) {
                $results[$id] = $this->testConnection($integration);
            }
        }

        return $results;
    }

    /**
     * @param array<string, string> $headers
     */
    private function api(Integration $integration, array $headers, ?string $baseUrl = null): ApiClient
    {
        return new ApiClient(
            $this->http,
            $this->guard,
            $this->logger,
            $integration->id,
            $baseUrl ?? (string) $integration->config('base_url'),
            $headers,
            (bool) $integration->config('verify_tls', true),
            $this->config->int('integrations.timeout', 20),
        );
    }

    private function assertType(Integration $integration, string $type, bool $allowDisabled): void
    {
        if ($integration->type !== $type) {
            throw new IntegrationException(sprintf('Integration #%d is not of type %s.', $integration->id, $type));
        }
        if (!$allowDisabled && !$integration->enabled) {
            throw new IntegrationException(sprintf('Integration "%s" is disabled.', $integration->name));
        }
    }
}
