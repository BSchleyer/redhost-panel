<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration\Integrations;

use Nebrix\Billing\OrderException;
use Nebrix\Billing\OrderService;
use Nebrix\Domain\PhoneNumber;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\IntegrationLogger;
use Nebrix\Integration\IntegrationRepository;
use Nebrix\Core\Config;
use Nebrix\Provisioning\DomainProvisioner;
use Nebrix\Provisioning\ServiceActionRunner;
use Nebrix\Repository\ProductRepository;
use Nebrix\Repository\UserRepository;
use Nebrix\Security\UrlGuard;
use Nebrix\Service\ServiceRepository;
use Nebrix\Tests\Support\FakeInwx;
use GuzzleHttp\ClientInterface;

final class DomainTest extends IntegrationTestCase
{
    private FakeInwx $inwx;

    private int $integrationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inwx = new FakeInwx();
        $this->inwx->register($this->http);
        $container = $this->app->container();
        // The INWX endpoints are fixed host names: resolve them without DNS in tests.
        $container->set(UrlGuard::class, fn (): UrlGuard => new UrlGuard(false, false, static fn (string $host): array => str_ends_with($host, 'domrobot.com') ? ['203.0.113.80'] : []));
        $container->set(IntegrationManager::class, fn ($c): IntegrationManager => new IntegrationManager($c->get(IntegrationRepository::class), $c->get(ClientInterface::class), $c->get(UrlGuard::class), $c->get(IntegrationLogger::class), $c->get(Config::class)));
        $this->integrationId = $this->createIntegration('inwx', ['environment' => 'ote', 'username' => 'reseller', 'nameservers' => "ns.inwx.de\nns2.inwx.de\nns3.inwx.eu", 'verify_tls' => true], ['password' => 'inwx-pass']);
    }

    private function customer(): \Nebrix\Auth\User
    {
        $user = $this->createUser();
        $this->db->update('nx_users', ['first_name' => 'Max', 'last_name' => 'Muster', 'address_line1' => 'Hauptstr. 5', 'postcode' => '45879', 'city' => 'Gelsenkirchen', 'country' => 'DE', 'phone' => '0209 123456'], ['id' => $user->id]);

        return $this->app->container()->get(UserRepository::class)->find($user->id) ?? $user;
    }

    private function product(): int
    {
        return $this->app->container()->get(ProductRepository::class)->create([
            'category' => 'domain', 'name' => '.de domain', 'slug' => 'de-' . bin2hex(random_bytes(3)), 'price' => 900, 'currency' => 'EUR', 'billing_cycle' => 'annually',
            'tax_rate_bp' => 1900, 'is_visible' => 1, 'is_active' => 1, 'auto_provision' => 1, 'integration_id' => $this->integrationId, 'provisioning_config' => ['tld' => 'de'],
        ]);
    }

    public function testOrderPrechecks(): void
    {
        $orders = $this->app->container()->get(OrderService::class);
        $product = $this->product();
        $this->inwx->registeredElsewhere[] = 'taken.de';

        $cases = [
            [$this->createUser(), ['domain' => 'free.de'], 'complete profile'],
            [$this->customer(), ['domain' => 'free.com'], 'only available for .de'],
            [$this->customer(), ['domain' => 'sub.free.de'], 'only available for .de'],
            [$this->customer(), ['domain' => 'taken.de'], 'already registered'],
            [$this->customer(), ['domain' => 'free.de', 'auth_code' => 'abc'], 'cannot be transferred'],
        ];
        foreach ($cases as [$user, $options, $message]) {
            try {
                $orders->place($user, $product, $options, '127.0.0.1');
                self::fail('Order accepted: ' . json_encode($options));
            } catch (OrderException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_orders'));

        $orders->place($this->customer(), $product, ['domain' => 'free.de'], '127.0.0.1');
        $orders->place($this->customer(), $product, ['domain' => 'taken.de', 'auth_code' => 'S3cr3t-Auth!'], '127.0.0.1');
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_orders'));
        self::assertStringNotContainsString('S3cr3t-Auth!', (string) $this->db->fetchValue("SELECT options FROM nx_order_items WHERE options LIKE '%taken.de%'"), 'Auth codes are stored encrypted.');
    }

    public function testRegistrationContactsAndLifecycle(): void
    {
        $user = $this->customer();
        $service = $this->createService($user, 'domain', $this->integrationId, ['tld' => 'de', '_options' => ['domain' => 'my-shop.de']]);
        $provisioner = $this->app->container()->get(DomainProvisioner::class);

        $result = $provisioner->provision($this->reload($service->id), $user);
        self::assertSame('domain:my-shop.de', $result->externalId);
        $create = $this->inwx->callsTo('domain.create')[0];
        self::assertSame(['ns.inwx.de', 'ns2.inwx.de', 'ns3.inwx.eu'], $create['ns']);
        self::assertSame('1Y', $create['period']);
        $contact = $this->inwx->contacts[$create['registrant']];
        self::assertSame(['PERSON', 'Max Muster', 'Hauptstr. 5', '45879', 'DE', '+49.209123456'], [$contact['type'], $contact['name'], $contact['street'], $contact['pc'], $contact['cc'], $contact['voice']]);

        // Retry and a second domain: no duplicate registration, the contact is reused.
        $provisioner->provision($this->reload($service->id), $user);
        $second = $this->createService($user, 'domain', $this->integrationId, ['tld' => 'de', '_options' => ['domain' => 'my-blog.de']]);
        $provisioner->provision($this->reload($second->id), $user);
        self::assertCount(2, $this->inwx->callsTo('domain.create'));
        self::assertCount(1, $this->inwx->contacts);

        $provisioner->suspend($this->reload($service->id));
        self::assertSame('AUTOEXPIRE', $this->inwx->domains['my-shop.de']['renewalMode']);
        $provisioner->unsuspend($this->reload($service->id));
        self::assertSame('AUTORENEW', $this->inwx->domains['my-shop.de']['renewalMode']);
        $provisioner->terminate($this->reload($service->id));
        self::assertSame('AUTODELETE', $this->inwx->domains['my-shop.de']['renewalMode'], 'Termination never deletes immediately.');

        $status = $provisioner->status($this->reload($service->id));
        self::assertSame('active', $status['state']);
        self::assertSame('2027-11-28 00:00:00', $status['connection']['Paid until (registry)']);
    }

    public function testForeignDomainInTheAccountIsNotAdopted(): void
    {
        $this->inwx->domains['existing.de'] = ['domain' => 'existing.de', 'status' => 'OK', 'ns' => []];
        $user = $this->customer();
        $service = $this->createService($user, 'domain', $this->integrationId, ['tld' => 'de', '_options' => ['domain' => 'existing.de']]);

        $this->expectException(\Nebrix\Integration\IntegrationException::class);
        $this->expectExceptionMessage('not registered by this service');
        $this->app->container()->get(DomainProvisioner::class)->provision($this->reload($service->id), $user);
    }

    public function testTransferUsesTheEncryptedAuthCodeOnce(): void
    {
        $this->inwx->registeredElsewhere[] = 'moving.de';
        $user = $this->customer();
        $orders = $this->app->container()->get(OrderService::class);
        $order = $orders->place($user, $this->product(), ['domain' => 'moving.de', 'auth_code' => 'S3cr3t-Auth!'], '127.0.0.1');
        $item = (array) $this->db->fetchOne('SELECT * FROM nx_order_items WHERE order_id = ?', [$order['order_id']]);
        $options = json_decode((string) $item['options'], true);
        $service = $this->createService($user, 'domain', $this->integrationId, ['tld' => 'de', '_options' => $options]);

        $provisioner = $this->app->container()->get(DomainProvisioner::class);
        $this->expectRetry(fn () => $provisioner->provision($this->reload($service->id), $user));

        self::assertSame('S3cr3t-Auth!', $this->inwx->callsTo('domain.transfer')[0]['authCode']);
        self::assertArrayNotHasKey('auth_code', $this->reload($service->id)->config['_options'], 'The auth code is removed after the transfer request.');
        self::assertSame('transfer', $this->reload($service->id)->remote['submitted']);

        // While the transfer is pending the service is not completed and the transfer is not submitted again.
        $this->expectRetry(fn () => $provisioner->provision($this->reload($service->id), $user));
        self::assertCount(1, $this->inwx->callsTo('domain.transfer'));

        $this->inwx->domains['moving.de']['status'] = 'OK';
        $result = $provisioner->provision($this->reload($service->id), $user);
        self::assertSame('domain:moving.de', $result->externalId);
        self::assertCount(1, $this->inwx->callsTo('domain.transfer'));
    }

    public function testTransferThatNeverCompletesFailsAfterTheDeadline(): void
    {
        $user = $this->customer();
        $provisioner = $this->app->container()->get(DomainProvisioner::class);

        // A wrong auth code rejected by the registry: nothing counts as submitted, the service is not activated.
        $wrong = $this->transferService($user, 'wrong.de', 'wrong-code');
        try {
            $provisioner->provision($this->reload($wrong->id), $user);
            self::fail('Transfer with a wrong auth code accepted.');
        } catch (\Nebrix\Integration\IntegrationException $exception) {
            self::assertStringContainsString('Invalid authorization', $exception->getMessage());
        }
        self::assertArrayNotHasKey('submitted', $this->reload($wrong->id)->remote);

        // An accepted transfer that the losing registrar never completes fails after 10 days instead of activating the service.
        $stuck = $this->transferService($user, 'stuck.de', 'S3cr3t-Auth!');
        $this->expectRetry(fn () => $provisioner->provision($this->reload($stuck->id), $user));
        $this->app->container()->get(ServiceRepository::class)->mergeRemote($stuck->id, ['submitted_at' => time() - 11 * 86400]);
        $this->expectException(\Nebrix\Integration\IntegrationException::class);
        $this->expectExceptionMessage('was not completed in time');
        $provisioner->provision($this->reload($stuck->id), $user);
    }

    private function transferService(\Nebrix\Auth\User $user, string $domain, string $authCode): \Nebrix\Service\Service
    {
        $this->inwx->registeredElsewhere[] = $domain;
        $order = $this->app->container()->get(OrderService::class)->place($user, $this->product(), ['domain' => $domain, 'auth_code' => $authCode], '127.0.0.1');
        $options = json_decode((string) $this->db->fetchValue('SELECT options FROM nx_order_items WHERE order_id = ?', [$order['order_id']]), true);

        return $this->createService($user, 'domain', $this->integrationId, ['tld' => 'de', '_options' => $options]);
    }

    public function testDomainOfAnotherCustomerCannotBeTakenOver(): void
    {
        $owner = $this->customer();
        $existing = $this->createService($owner, 'domain', $this->integrationId, ['tld' => 'de', '_options' => ['domain' => 'mine.de']]);
        $provisioner = $this->app->container()->get(DomainProvisioner::class);
        $provisioner->provision($this->reload($existing->id), $owner);
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $existing->id]);

        $attacker = $this->customer();
        try {
            $this->app->container()->get(OrderService::class)->place($attacker, $this->product(), ['domain' => 'mine.de', 'auth_code' => 'guessed'], '127.0.0.1');
            self::fail('A domain managed in the panel was accepted for another order.');
        } catch (OrderException $exception) {
            self::assertStringContainsString('already managed in this panel', $exception->getMessage());
        }

        // Even a service created without the precheck cannot adopt or act on it.
        $foreign = $this->createService($attacker, 'domain', $this->integrationId, ['tld' => 'de', '_options' => ['domain' => 'mine.de']]);
        try {
            $provisioner->provision($this->reload($foreign->id), $attacker);
            self::fail('Foreign service provisioned.');
        } catch (\Nebrix\Integration\IntegrationException $exception) {
            self::assertStringContainsString('already held by another service', $exception->getMessage());
        }
        $this->db->update('nx_services', ['status' => 'terminated'], ['id' => $existing->id]);
        try {
            $provisioner->provision($this->reload($foreign->id), $attacker);
            self::fail('Domain in the account adopted by another registrant.');
        } catch (\Nebrix\Integration\IntegrationException $exception) {
            self::assertStringContainsString('not registered by this service', $exception->getMessage());
        }
        self::assertSame([], $this->inwx->callsTo('domain.transfer'));
        self::assertCount(1, $this->inwx->callsTo('domain.create'));
    }

    private function expectRetry(\Closure $call): void
    {
        try {
            $call();
            self::fail('Expected RetryLaterException.');
        } catch (\Nebrix\Provisioning\RetryLaterException) {
        }
    }

    public function testCustomerActionsAndTwoFactorLogin(): void
    {
        $this->inwx->twoFactor = true;
        $this->db->run('UPDATE nx_integrations SET secrets = NULL WHERE id = ?', [$this->integrationId]);
        $this->app->container()->get(IntegrationRepository::class)->update($this->integrationId, 'INWX', true, ['environment' => 'ote', 'username' => 'reseller', 'nameservers' => "ns.inwx.de\nns2.inwx.de", 'verify_tls' => true], ['password' => 'inwx-pass', 'totp_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP']);
        $user = $this->customer();
        $service = $this->createService($user, 'domain', $this->integrationId, ['tld' => 'de', '_options' => ['domain' => 'actions.de']]);
        $this->app->container()->get(DomainProvisioner::class)->provision($this->reload($service->id), $user);
        self::assertCount(1, $this->inwx->callsTo('account.unlock'), 'Accounts with 2FA are unlocked with a generated TOTP code.');
        $this->db->update('nx_services', ['status' => 'active'], ['id' => $service->id]);
        $runner = $this->app->container()->get(ServiceActionRunner::class);

        $runner->run($this->reload($service->id), 'nameservers', ['ns1' => 'ns1.example.net', 'ns2' => 'NS2.example.net', 'ns3' => ''], $user->id, null);
        self::assertSame(['ns1.example.net', 'ns2.example.net'], $this->inwx->domains['actions.de']['ns']);

        $runner->run($this->reload($service->id), 'auth_code', [], $user->id, null);
        self::assertSame($this->inwx->domains['actions.de']['authCode'], $this->app->container()->get(ServiceRepository::class)->credentials($this->reload($service->id))['auth_code']);

        $this->expectException(\Nebrix\Security\ValidationException::class);
        $runner->run($this->reload($service->id), 'nameservers', ['ns1' => 'bad ns', 'ns2' => 'ns2.example.net'], $user->id, null);
    }

    public function testPhoneNumbersAreConvertedToEppFormat(): void
    {
        self::assertSame('+49.301234567', PhoneNumber::toEpp('+49 30 1234567', 'DE'));
        self::assertSame('+49.301234567', PhoneNumber::toEpp('0049-30-1234567', null));
        self::assertSame('+49.301234567', PhoneNumber::toEpp('030 1234567', 'DE'));
        self::assertSame('+43.1234567', PhoneNumber::toEpp('+431234567', 'AT'));
        self::assertSame('+41.441234567', PhoneNumber::toEpp('+41.44 123 45 67', 'CH'));
        self::assertNull(PhoneNumber::toEpp('12345', 'XX'));
        self::assertNull(PhoneNumber::toEpp('', 'DE'));
    }
}
