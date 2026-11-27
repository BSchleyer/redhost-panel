<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration\Integrations;

use DateTimeImmutable;
use Nebrix\Integration\Hetzner\RobotClient;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Provisioning\HetznerRobotProvisioner;
use Nebrix\Provisioning\RetryLaterException;
use Nebrix\Provisioning\ServiceActionRunner;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Nebrix\Tests\Support\FakeHttp;
use Nebrix\Tests\Support\FakeRobot;

final class HetznerRobotTest extends IntegrationTestCase
{
    private FakeRobot $robot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->robot = new FakeRobot();
        $this->robot->register($this->http);
        $this->clock()->freeze(new DateTimeImmutable('2026-11-28 10:00:00'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function integration(array $config = []): int
    {
        return $this->createIntegration('hetzner_robot', $config + [
            'base_url' => 'https://203.0.113.50', 'username' => '#ws+AbCdEf', 'pool_prefix' => 'nebrix-free',
            'allow_orders' => false, 'order_test_mode' => true, 'cancel_on_terminate' => false, 'verify_tls' => true,
        ], ['password' => 'robot-pass']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function dedicated(int $integrationId, array $config = []): Service
    {
        return $this->createService($this->createUser(), 'dedicated', $integrationId, $config + [
            'mode' => 'pool', 'server_product' => 'AX41', 'location' => 'FSN1', 'image' => 'Debian 12 base',
            'reinstall_images' => "Ubuntu 24.04 LTS base\nnot valid; rm -rf /",
        ]);
    }

    private function provisioner(): HetznerRobotProvisioner
    {
        return $this->app->container()->get(HetznerRobotProvisioner::class);
    }

    /** Runs provisioning until it completes, simulating the installation. */
    private function provisionToEnd(Service $service): \Nebrix\Provisioning\ProvisionResult
    {
        $owner = $this->app->container()->get(\Nebrix\Repository\UserRepository::class)->find($service->userId);
        self::assertNotNull($owner);
        for ($i = 0; $i < 6; $i++) {
            try {
                return $this->provisioner()->provision($this->reload($service->id), $owner);
            } catch (RetryLaterException) {
                foreach ($this->robot->linux as $number => $state) {
                    $this->robot->linux[$number]['active'] = false; // the server booted the installer
                }
                $this->clock()->freeze($this->clock()->now()->modify('+11 minutes'));
            }
        }
        self::fail('Provisioning did not finish.');
    }

    public function testPoolServerIsAssignedInstalledAndMarked(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(100, 'web-prod-1');                         // not in the pool
        $this->robot->addServer(101, 'nebrix-free-1', 'EX44', 'FSN1-DC1');  // wrong model
        $this->robot->addServer(102, 'nebrix-free-2', 'AX41-NVMe', 'NBG1-DC3'); // wrong location
        $this->robot->addServer(103, 'nebrix-free-3');                      // match
        $service = $this->dedicated($integrationId);

        $result = $this->provisionToEnd($service);

        self::assertSame('hetzner:103', $result->externalId);
        self::assertSame('nebrix-service-' . $service->id, $this->robot->servers[103]['server_name']);
        self::assertSame('web-prod-1', $this->robot->servers[100]['server_name'], 'Servers outside the pool are never touched.');
        self::assertSame(['host' => FakeRobot::ip(103), 'username' => 'root', 'password' => 'linuxPw1'], $result->credentials);
        self::assertSame('Debian 12 base', $this->robot->linux[103]['dist']);
        self::assertSame([['server' => 103, 'type' => 'hw']], $this->robot->resets, 'Exactly one reset starts the installation.');
        self::assertStringStartsWith('Basic ', $this->http->last()->getHeaderLine('Authorization'));
        self::assertSame('done', $this->reload($service->id)->remote['step']);
    }

    public function testTwoServicesNeverGetTheSameServer(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(201, 'nebrix-free-a');
        $this->robot->addServer(202, 'nebrix-free-b');
        $first = $this->dedicated($integrationId);
        // Service A reserved server 201 but crashed before renaming it.
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($first->id, ['server_number' => 201, 'step' => 'name', 'mode' => 'pool']);

        $second = $this->dedicated($integrationId);
        self::assertSame('hetzner:202', $this->provisionToEnd($second)->externalId);
        self::assertSame('hetzner:201', $this->provisionToEnd($first)->externalId);
    }

    public function testEmptyPoolFailsClearly(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(300, 'customer-box');

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('No free server');
        $this->provisionToEnd($this->dedicated($integrationId));
    }

    public function testSuspensionUsesTheFirewallAndRestoresIt(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(400, 'nebrix-free-x');
        $service = $this->dedicated($integrationId);
        $this->provisionToEnd($service);
        $this->robot->firewall[400] = ['status' => 'active', 'whitelist_hos' => false, 'rules' => ['input' => [['name' => 'ssh', 'ip_version' => 'ipv4', 'dst_port' => '22', 'protocol' => 'tcp', 'action' => 'accept']]]];

        $this->provisioner()->suspend($this->reload($service->id));
        self::assertSame(['status' => 'active', 'whitelist_hos' => true, 'rules' => ['input' => []]], $this->robot->firewall[400], 'Suspended: firewall active without rules.');
        $this->provisioner()->suspend($this->reload($service->id)); // idempotent: keeps the original backup

        $this->provisioner()->unsuspend($this->reload($service->id));
        self::assertSame('active', $this->robot->firewall[400]['status']);
        self::assertFalse($this->robot->firewall[400]['whitelist_hos']);
        self::assertSame('22', $this->robot->firewall[400]['rules']['input'][0]['dst_port']);
        self::assertArrayNotHasKey('firewall_backup', $this->reload($service->id)->remote);
    }

    public function testActionsRescueAndReverseDns(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(500, 'nebrix-free-y');
        $service = $this->dedicated($integrationId);
        $this->provisionToEnd($service);
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $runner = $this->app->container()->get(ServiceActionRunner::class);

        $runner->run($this->reload($service->id), 'rdns', ['ptr' => 'srv1.example.com'], null, null);
        self::assertSame(['198.51.1.244' => 'srv1.example.com'], $this->robot->rdns);

        $runner->run($this->reload($service->id), 'rescue', [], null, null);
        self::assertTrue($this->robot->rescue[500]['active']);
        self::assertSame($this->robot->rescue[500]['password'], $this->app->container()->get(ServiceRepository::class)->credentials($this->reload($service->id))['rescue_password']);

        try {
            $runner->run($this->reload($service->id), 'rdns', ['ptr' => 'not a hostname'], null, null);
            self::fail('Invalid input must be rejected.');
        } catch (\Nebrix\Security\ValidationException) {
        }
    }

    public function testActionsRefuseServersThatNoLongerCarryTheMarker(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(600, 'nebrix-free-z');
        $service = $this->dedicated($integrationId);
        $this->provisionToEnd($service);
        $this->robot->servers[600]['server_name'] = 'moved-to-other-customer';

        try {
            $this->provisioner()->perform($this->reload($service->id), 'hard_reset');
            self::fail('Expected a refusal.');
        } catch (IntegrationException $exception) {
            self::assertStringContainsString('no longer named', $exception->getMessage());
        }
        try {
            $this->provisioner()->terminate($this->reload($service->id));
            self::fail('Expected a refusal.');
        } catch (IntegrationException $exception) {
            self::assertStringContainsString('does not belong', $exception->getMessage());
        }
        self::assertCount(1, $this->robot->resets, 'Only the installation reset happened.');
    }

    public function testTerminationBootsRescueAndQueuesTheServerForWiping(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(700, 'nebrix-free-t');
        $service = $this->dedicated($integrationId);
        $this->provisionToEnd($service);

        $this->provisioner()->terminate($this->reload($service->id));
        $this->provisioner()->terminate($this->reload($service->id)); // retried job

        self::assertSame('nebrix-wipe-700', $this->robot->servers[700]['server_name']);
        self::assertTrue($this->robot->rescue[700]['active']);
        self::assertCount(2, $this->robot->resets, 'Installation reset + one termination reset.');
        self::assertSame([], $this->robot->cancelled, 'Pool servers are never cancelled.');

        // The wiped server is not handed out again until staff rename it.
        $this->expectException(IntegrationException::class);
        $this->provisionToEnd($this->dedicated($integrationId));
    }

    public function testOrderingRequiresOptInAndRespectsTestMode(): void
    {
        $disabled = $this->integration();
        try {
            $this->provisionToEnd($this->dedicated($disabled, ['mode' => 'order', 'server_product' => 'AX41-NVMe']));
            self::fail('Ordering must be disabled by default.');
        } catch (IntegrationException $exception) {
            self::assertStringContainsString('disabled', $exception->getMessage());
        }
        self::assertSame([], $this->http->requests('POST', '/order/server/transaction'));

        $testMode = $this->integration(['allow_orders' => true, 'order_test_mode' => true, 'ssh_key_fingerprint' => 'aa:bb:cc:dd:ee:ff:00:11:22:33:44:55:66:77:88:99']);
        try {
            $this->provisionToEnd($this->dedicated($testMode, ['mode' => 'order', 'server_product' => 'AX41-NVMe']));
            self::fail('A test order is never processed.');
        } catch (IntegrationException $exception) {
            self::assertStringContainsString('test mode', $exception->getMessage());
        }
        $order = FakeHttp::form($this->http->requests('POST', '/order/server/transaction')[0]);
        self::assertSame('true', $order['test']);
        self::assertSame(['aa:bb:cc:dd:ee:ff:00:11:22:33:44:55:66:77:88:99'], $order['authorized_key']);
        self::assertSame('FSN1', $order['location']);
    }

    public function testRealOrderIsPlacedOnceAndAwaited(): void
    {
        $integrationId = $this->integration(['allow_orders' => true, 'order_test_mode' => false, 'cancel_on_terminate' => true]);
        $service = $this->dedicated($integrationId, ['mode' => 'order', 'server_product' => 'AX41-NVMe']);
        $owner = $this->app->container()->get(\Nebrix\Repository\UserRepository::class)->find($service->userId);
        self::assertNotNull($owner);

        foreach ([1, 2] as $attempt) {
            try {
                $this->provisioner()->provision($this->reload($service->id), $owner);
                self::fail('Expected RetryLater while Hetzner processes the order.');
            } catch (RetryLaterException) {
            }
        }
        // A crash after the order but before storing the transaction ID: the order is found by its comment.
        $this->db->run("UPDATE nx_services SET remote = JSON_REMOVE(remote, '$.transaction_id') WHERE id = ?", [$service->id]);
        try {
            $this->provisioner()->provision($this->reload($service->id), $owner);
        } catch (RetryLaterException) {
        }
        self::assertCount(1, $this->http->requests('POST', '/order/server/transaction'), 'The server is ordered exactly once.');

        // Hetzner delivers the server.
        $id = array_key_first($this->robot->transactions);
        $this->robot->addServer(800, '');
        $this->robot->transactions[$id] = ['status' => 'ready', 'server_number' => 800, 'server_ip' => '198.51.100.80'] + $this->robot->transactions[$id];

        self::assertSame('hetzner:800', $this->provisionToEnd($service)->externalId);
        self::assertSame('nebrix-service-' . $service->id, $this->robot->servers[800]['server_name']);

        $this->provisioner()->terminate($this->reload($service->id));
        self::assertSame('2026-12-31', $this->robot->cancelled[800], 'Ordered servers are cancelled at the earliest date when configured.');
    }

    public function testReinstallInstallsTheChosenImageWithANewPassword(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(900, 'nebrix-free-r');
        $service = $this->dedicated($integrationId);
        $this->provisionToEnd($service);
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $runner = $this->app->container()->get(ServiceActionRunner::class);

        $options = $runner->available($this->reload($service->id))['reinstall']->fields['image']['options'] ?? [];
        self::assertSame(['Debian 12 base' => 'Debian 12 base', 'Ubuntu 24.04 LTS base' => 'Ubuntu 24.04 LTS base'], $options, 'Invalid image names from the product are ignored.');

        try {
            $runner->run($this->reload($service->id), 'reinstall', ['image' => 'Windows'], null, null);
            self::fail('Only offered images are accepted.');
        } catch (\Nebrix\Security\ValidationException) {
        }

        $runner->run($this->reload($service->id), 'reinstall', ['image' => 'Ubuntu 24.04 LTS base'], null, null);
        self::assertSame('provisioning', $this->reload($service->id)->status);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'service.provision' AND unique_key LIKE ?", ['service.provision:' . $service->id . ':reinstall:%']));

        $result = $this->provisionToEnd($service);
        self::assertSame('Ubuntu 24.04 LTS base', $this->robot->linux[900]['dist']);
        self::assertSame('linuxPw2', $result->credentials['password']);
    }

    public function testFormEncodingUsesRobotListNotation(): void
    {
        self::assertSame('dist=Debian%2012%20base&authorized_key[]=aa%3Abb&authorized_key[]=cc', RobotClient::formEncode(['dist' => 'Debian 12 base', 'authorized_key' => ['aa:bb', 'cc']]));
    }

    public function testHealthCheckReportsThePool(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(1, 'nebrix-free-1');
        $this->robot->addServer(2, 'production');
        $manager = $this->app->container()->get(IntegrationManager::class);

        $result = $manager->testConnection($manager->get($integrationId));

        self::assertTrue($result->isHealthy());
        self::assertStringContainsString('2 server(s)', $result->message);
        self::assertStringContainsString('1 free', $result->message);
    }

    public function testReinstallIsRefusedAtomicallyWhenTheServerChangedHands(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(910, 'nebrix-free-m');
        $service = $this->dedicated($integrationId);
        $this->provisionToEnd($service);
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $runner = $this->app->container()->get(ServiceActionRunner::class);
        $this->robot->servers[910]['server_name'] = 'moved-to-other-customer';

        try {
            $runner->run($this->reload($service->id), 'reinstall', ['image' => 'Debian 12 base'], null, null);
            self::fail('Reinstall of a foreign server accepted.');
        } catch (\Nebrix\Provisioning\ServiceStateException $exception) {
            self::assertStringContainsString('cannot be reinstalled', $exception->getMessage());
        }
        self::assertSame('active', $this->reload($service->id)->status, 'The status is restored when the reinstall is refused.');
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE unique_key LIKE ?", ['service.provision:' . $service->id . ':reinstall:%']));

        // A second request while a reinstall runs is refused (status guard).
        $this->robot->servers[910]['server_name'] = 'nebrix-service-' . $service->id;
        $runner->run($this->reload($service->id), 'reinstall', ['image' => 'Debian 12 base'], null, null);
        $this->expectException(\Nebrix\Provisioning\ServiceStateException::class);
        $runner->run($this->reload($service->id), 'reinstall', ['image' => 'Debian 12 base'], null, null);
    }

    public function testRetryAfterACompletedJobQueuesANewProvisioningJob(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(920, 'nebrix-free-q');
        $service = $this->dedicated($integrationId);
        $jobs = $this->app->container()->get(\Nebrix\Queue\JobQueue::class);
        $first = $jobs->dispatch('service.provision', ['service_id' => $service->id], 'service.provision:' . $service->id);
        $this->db->update('nx_jobs', ['status' => 'completed'], ['id' => $first]);
        $this->db->update('nx_services', ['status' => 'failed'], ['id' => $service->id]);

        $manager = $this->app->container()->get(\Nebrix\Provisioning\ServiceManager::class);
        $manager->retryProvisioning($this->reload($service->id), null);

        $pending = $this->db->fetchAll("SELECT unique_key FROM nx_jobs WHERE type = 'service.provision' AND status = 'pending'");
        self::assertCount(1, $pending);
        self::assertStringStartsWith('service.provision:' . $service->id . ':retry:', (string) $pending[0]['unique_key']);
        self::assertSame('pending', $this->reload($service->id)->status);

        // A failed job is re-run instead of queueing another one.
        $this->db->run("UPDATE nx_jobs SET status = 'failed' WHERE status = 'pending'");
        $this->db->update('nx_services', ['status' => 'failed'], ['id' => $service->id]);
        $manager->retryProvisioning($this->reload($service->id), null);
        self::assertSame(2, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'service.provision'"));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'service.provision' AND status = 'pending'"));
    }

    public function testActiveRescueSystemIsDisabledBeforeTheInstallation(): void
    {
        $integrationId = $this->integration();
        $this->robot->addServer(930, 'nebrix-free-s');
        $this->robot->rescue[930] = ['active' => true, 'password' => 'leftover'];

        $this->provisionToEnd($this->dedicated($integrationId));

        self::assertFalse($this->robot->rescue[930]['active']);
        self::assertCount(1, $this->http->requests('DELETE', '/boot/930/rescue'));
    }

    public function testTestOrderIsReplacedByARealOrderWhenTestModeIsSwitchedOff(): void
    {
        $config = ['allow_orders' => true, 'order_test_mode' => true];
        $integrationId = $this->integration($config);
        $service = $this->dedicated($integrationId, ['mode' => 'order', 'server_product' => 'AX41-NVMe']);
        try {
            $this->provisionToEnd($service);
            self::fail('A test order is never processed.');
        } catch (IntegrationException) {
        }
        self::assertTrue($this->reload($service->id)->remote['order_test'] ?? false);

        $repository = $this->app->container()->get(\Nebrix\Integration\IntegrationRepository::class);
        $integration = $repository->find($integrationId);
        self::assertNotNull($integration);
        $repository->update($integrationId, 'Hetzner', true, ['order_test_mode' => false] + $integration->config, []);
        $owner = $this->app->container()->get(\Nebrix\Repository\UserRepository::class)->find($service->userId);
        self::assertNotNull($owner);
        try {
            $this->provisioner()->provision($this->reload($service->id), $owner);
            self::fail('Expected RetryLater while Hetzner processes the real order.');
        } catch (RetryLaterException) {
        }

        $orders = $this->http->requests('POST', '/order/server/transaction');
        self::assertCount(2, $orders);
        self::assertArrayNotHasKey('test', FakeHttp::form($orders[1]));
        self::assertArrayNotHasKey('order_test', $this->reload($service->id)->remote);
    }

    public function testPoolPrefixMustNotOverlapAssignedServerNames(): void
    {
        foreach (['nebrix-free' => true, 'pool-' => true, '' => false, 'nebrix-' => false, 'nebrix-service' => false, 'nebrix-service-1' => false, 'nebrix-wipe-' => false, 'nebrix-w' => false] as $prefix => $safe) {
            self::assertSame($safe, HetznerRobotProvisioner::isSafePoolPrefix((string) $prefix), (string) $prefix);
        }
    }
}
