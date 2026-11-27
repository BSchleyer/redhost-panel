<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration\Integrations;

use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\Plesk\PleskClient;
use Nebrix\Provisioning\PleskProvisioner;
use Nebrix\Provisioning\ServiceActionRunner;
use Nebrix\Repository\UserRepository;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Nebrix\Tests\Support\FakePlesk;

final class PleskTest extends IntegrationTestCase
{
    private FakePlesk $plesk;

    private int $integrationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plesk = new FakePlesk();
        $this->plesk->register($this->http);
        $this->integrationId = $this->createIntegration('plesk', ['base_url' => 'https://203.0.113.60:8443', 'ip_address' => '203.0.113.61', 'verify_tls' => true], ['secret_key' => 'plesk-secret-key']);
    }

    private function hosting(int $userId, string $domain): Service
    {
        $owner = $this->app->container()->get(UserRepository::class)->find($userId);
        self::assertNotNull($owner);

        return $this->createService($owner, 'webhosting', $this->integrationId, ['plan' => 'Web S', '_options' => ['domain' => $domain]]);
    }

    private function provision(Service $service): \Nebrix\Provisioning\ProvisionResult
    {
        $owner = $this->app->container()->get(UserRepository::class)->find($service->userId);
        self::assertNotNull($owner);

        return $this->app->container()->get(PleskProvisioner::class)->provision($this->reload($service->id), $owner);
    }

    public function testCustomerAndSubscriptionAreCreatedOnceAndReused(): void
    {
        $user = $this->createUser();
        $first = $this->hosting($user->id, 'example-shop.de');
        $result = $this->provision($first);

        $customerId = array_key_first($this->plesk->customers);
        self::assertSame('nx' . $user->id, $this->plesk->customers[$customerId]['login']);
        self::assertSame('nebrix-user-' . $user->id, $this->plesk->customers[$customerId]['external_id']);
        self::assertSame($result->credentials['password'], $this->plesk->customers[$customerId]['password']);
        $webspace = $this->plesk->webspaces[array_key_first($this->plesk->webspaces)];
        self::assertSame(['example-shop.de', $customerId, 'Web S', '203.0.113.61', 'nx' . $first->id], [$webspace['name'], $webspace['owner_id'], $webspace['plan'], $webspace['ip'], $webspace['ftp_login']]);
        self::assertSame($result->credentials['ftp_password'], $webspace['ftp_password']);
        self::assertSame('https://203.0.113.60:8443', $result->credentials['panel_url']);

        // Retried job: nothing is created twice.
        $this->provision($first);
        self::assertCount(1, $this->plesk->customers);
        self::assertCount(1, $this->plesk->webspaces);

        // A second subscription of the same customer reuses the Plesk login.
        $second = $this->provision($this->hosting($user->id, 'example-blog.de'));
        self::assertCount(1, $this->plesk->customers);
        self::assertCount(2, $this->plesk->webspaces);
        self::assertSame($result->credentials['password'], $second->credentials['password']);
        self::assertNotSame($result->credentials['ftp_password'], $second->credentials['ftp_password']);
    }

    public function testForeignSubscriptionIsNeverAdopted(): void
    {
        $this->plesk->webspaces[99] = ['name' => 'taken.de', 'owner_id' => 5, 'status' => 0, 'plan' => 'X', 'ftp_login' => 'x', 'ftp_password' => 'x', 'ip' => '203.0.113.61'];

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('another customer');
        $this->provision($this->hosting($this->createUser()->id, 'taken.de'));
    }

    public function testSuspendUnsuspendTerminateAndSingleSignOn(): void
    {
        $user = $this->createUser();
        $service = $this->hosting($user->id, 'lifecycle.de');
        $this->provision($service);
        $id = (int) array_key_first($this->plesk->webspaces);
        $provisioner = $this->app->container()->get(PleskProvisioner::class);

        $provisioner->suspend($this->reload($service->id));
        self::assertSame(PleskClient::STATUS_SUSPENDED, $this->plesk->webspaces[$id]['status']);
        self::assertSame('suspended', $provisioner->status($this->reload($service->id))['state']);
        $provisioner->unsuspend($this->reload($service->id));
        self::assertSame(PleskClient::STATUS_ACTIVE, $this->plesk->webspaces[$id]['status']);

        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $result = $this->app->container()->get(ServiceActionRunner::class)->run($this->reload($service->id), 'login', [], $user->id, '198.51.100.23');
        self::assertMatchesRegularExpression('#^https://203\.0\.113\.60:8443/enterprise/rsession_init\.php\?PHPSESSID=sess[a-f0-9]{32}$#', (string) $result['redirect']);
        self::assertStringContainsString('<user_ip>' . base64_encode('198.51.100.23') . '</user_ip>', (string) end($this->plesk->requests));

        $provisioner->terminate($this->reload($service->id));
        $provisioner->terminate($this->reload($service->id)); // retried job
        self::assertSame([], $this->plesk->webspaces);
        self::assertCount(1, $this->plesk->customers, 'The customer account is kept.');
    }

    public function testPasswordResetUpdatesAllSubscriptionsOfTheCustomer(): void
    {
        $user = $this->createUser();
        $first = $this->hosting($user->id, 'one.de');
        $second = $this->hosting($user->id, 'two.de');
        $this->provision($first);
        $this->provision($second);
        $this->db->run("UPDATE nx_services SET status = 'active' WHERE id IN (?, ?)", [$first->id, $second->id]);

        $this->app->container()->get(ServiceActionRunner::class)->run($this->reload($first->id), 'reset_password', [], $user->id, null);

        $password = $this->plesk->customers[array_key_first($this->plesk->customers)]['password'];
        $repository = $this->app->container()->get(ServiceRepository::class);
        self::assertSame($password, $repository->credentials($this->reload($first->id))['password']);
        self::assertSame($password, $repository->credentials($this->reload($second->id))['password']);
    }

    public function testXmlIsEscapedAndUnsafeResponsesAreRejected(): void
    {
        $user = $this->createUser();
        $this->db->update('nx_users', ['first_name' => 'Eve</pname><login>admin', 'last_name' => '&'], ['id' => $user->id]);
        $this->provision($this->hosting($user->id, 'escape.de'));
        self::assertSame('Eve</pname><login>admin &', $this->plesk->customers[array_key_first($this->plesk->customers)]['name'], 'Values are escaped, not interpreted as XML.');

        $this->expectException(IntegrationException::class);
        PleskClient::parse('<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><packet>&e;</packet>');
    }

    public function testHealthCheckAndWrongKey(): void
    {
        $manager = $this->app->container()->get(IntegrationManager::class);
        self::assertSame('Plesk 18.0.62', $manager->testConnection($manager->get($this->integrationId))->message);

        $wrong = $this->createIntegration('plesk', ['base_url' => 'https://203.0.113.60:8443', 'ip_address' => '203.0.113.61', 'verify_tls' => true], ['secret_key' => 'wrong']);
        $result = $manager->testConnection($manager->get($wrong));
        self::assertFalse($result->isHealthy());
        self::assertStringContainsString('Authentication failed', $result->message);
    }

    public function testADomainGetsOnlyOneSubscription(): void
    {
        $first = $this->hosting($this->createUser()->id, 'unique-shop.de');
        $this->provision($first);

        // A second service for the same domain (another customer) is refused before anything is created.
        $second = $this->hosting($this->createUser()->id, 'unique-shop.de');
        try {
            $this->provision($second);
            self::fail('Second subscription for the same domain accepted.');
        } catch (IntegrationException $exception) {
            self::assertStringContainsString('already used by another webhosting service', $exception->getMessage());
        }
        self::assertCount(1, $this->plesk->customers);
        self::assertCount(1, $this->plesk->webspaces);
    }

    public function testSingleSignOnThroughTheApiNeedsTheCredentialsScopeAndIsNeverStored(): void
    {
        $this->app->container()->get(\Nebrix\Settings\SettingsRepository::class)->set('api.enabled', true);
        $user = $this->createUser();
        $service = $this->hosting($user->id, 'sso-api.de');
        $this->provision($service);
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $tokens = $this->app->container()->get(\Nebrix\Api\ApiTokenService::class);
        $call = function (string $secret, string $key): \Nebrix\Http\Response {
            return $this->app->handle(\Nebrix\Http\Request::create('POST', '/api/v1/services/' . $this->lastServiceId . '/actions/login', [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $secret, 'HTTP_IDEMPOTENCY_KEY' => $key, 'CONTENT_TYPE' => 'application/json',
            ], '{}'));
        };
        $this->lastServiceId = $service->id;

        $manageOnly = $tokens->create($user, 'Power', ['services:manage'], 30, [], null)['secret'];
        $denied = $call($manageOnly, 'sso-key-0001');
        self::assertSame(403, $denied->status());
        self::assertSame('insufficient_scope', json_decode($denied->body(), true)['error']['code']);

        $full = $tokens->create($user, 'SSO', ['services:manage', 'services:credentials'], 30, [], null)['secret'];
        $allowed = $call($full, 'sso-key-0002');
        self::assertSame(200, $allowed->status());
        self::assertStringContainsString('rsession_init.php', (string) json_decode($allowed->body(), true)['data']['redirect_url']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_api_idempotency'), 'One-time login URLs are not stored for replays.');
        self::assertNotNull($this->db->fetchOne("SELECT id FROM nx_audit_log WHERE action = 'service.action' AND target_id = ?", [(string) $service->id]));
    }

    private int $lastServiceId = 0;
}
