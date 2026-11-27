<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration\Integrations;

use GuzzleHttp\ClientInterface;
use Nebrix\Auth\User;
use Nebrix\Core\Config;
use Nebrix\Integration\IntegrationLogger;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\IntegrationRepository;
use Nebrix\Integration\TeamSpeak\Transport;
use Nebrix\Security\UrlGuard;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Nebrix\Tests\DatabaseTestCase;
use Nebrix\Tests\Support\FakeHttp;
use Nebrix\Tests\Support\FakeServerQuery;

abstract class IntegrationTestCase extends DatabaseTestCase
{
    protected FakeHttp $http;

    protected FakeServerQuery $teamspeak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new FakeHttp();
        $this->teamspeak = new FakeServerQuery('query-pass');
        $container = $this->app->container();

        $container->set(ClientInterface::class, fn (): ClientInterface => $this->http->client());
        $container->set(IntegrationManager::class, fn ($c): IntegrationManager => new IntegrationManager(
            $c->get(IntegrationRepository::class),
            $c->get(ClientInterface::class),
            $c->get(UrlGuard::class),
            $c->get(IntegrationLogger::class),
            $c->get(Config::class),
            fn (string $ip, int $port): Transport => $this->teamspeak,
        ));
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, string> $secrets
     */
    protected function createIntegration(string $type, array $config, array $secrets, bool $enabled = true): int
    {
        return $this->app->container()->get(IntegrationRepository::class)->create($type, ucfirst($type) . ' ' . bin2hex(random_bytes(2)), $enabled, $config, $secrets);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createService(User $owner, string $category, int $integrationId, array $config): Service
    {
        $services = $this->app->container()->get(ServiceRepository::class);
        $id = $services->create([
            'user_id' => $owner->id,
            'integration_id' => $integrationId,
            'category' => $category,
            'name' => 'Test service',
            'status' => Service::STATUS_PROVISIONING,
            'billing_cycle' => 'monthly',
            'recurring_amount' => 500,
            'tax_rate_bp' => 1900,
            'currency' => 'EUR',
            'config' => $config,
        ]);

        return $this->reload($id);
    }

    protected function reload(int $serviceId): Service
    {
        $service = $this->app->container()->get(ServiceRepository::class)->find($serviceId);
        self::assertNotNull($service);

        return $service;
    }
}
