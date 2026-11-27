<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use InvalidArgumentException;
use Nebrix\Auth\User;
use Nebrix\Billing\OrderException;
use Nebrix\Domain\Registrar;
use Nebrix\Domain\RegistrarContacts;
use Nebrix\Integration\Integration;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Security\Encrypter;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;

/**
 * Domain registrations and transfers through a registrar (INWX).
 *
 * - Before the order: TLD, profile completeness (registrant data) and
 *   availability are checked; registered domains need an auth code.
 * - Registration/transfer uses a registrant contact created from the
 *   customer profile. The submission is recorded before the remote call; a
 *   retry adopts the domain only if this service submitted it.
 * - The transfer auth code is stored encrypted and removed after submission.
 * - Suspension stops automatic renewal at the registry, unsuspension enables
 *   it again; termination lets the domain be deleted at the end of its term
 *   (never an immediate deletion).
 */
final class DomainProvisioner implements Provisioner, OrderPrecheck
{
    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly ServiceRepository $services,
        private readonly RegistrarContacts $contacts,
        private readonly Encrypter $encrypter,
    ) {
    }

    public function checkOrder(Integration $integration, array $config, User $user, array $options): void
    {
        $domain = mb_strtolower((string) ($options['domain'] ?? ''));
        if (!$this->matchesTld($domain, (string) ($config['tld'] ?? ''))) {
            throw new OrderException(sprintf('This product is only available for %s domains.', '.' . ltrim((string) ($config['tld'] ?? ''), '.')));
        }
        try {
            RegistrarContacts::contactData($user);
        } catch (InvalidArgumentException $exception) {
            throw new OrderException($exception->getMessage() . ' (Account → Profile)');
        }

        try {
            $available = $this->integrations->registrar($integration)->isAvailable($domain);
        } catch (IntegrationException) {
            throw new OrderException('The availability of the domain could not be checked. Please try again later.');
        }
        $transfer = isset($options['auth_code']) && $options['auth_code'] !== '' && $options['auth_code'] !== [];
        if (!$available && !$transfer) {
            throw new OrderException(sprintf('%s is already registered. If it is yours, enter its auth code to transfer it.', $domain));
        }
        if ($available && $transfer) {
            throw new OrderException(sprintf('%s is not registered yet and cannot be transferred. Order it without an auth code.', $domain));
        }
    }

    public function provision(Service $service, User $owner): ProvisionResult
    {
        [$integration, $registrar] = $this->client($service);
        $domain = $this->domain($service);

        $info = $registrar->info($domain);
        if ($info !== null) {
            if (($service->remote['submitted'] ?? null) === null) {
                throw new IntegrationException(sprintf('%s is already in the registrar account but was not registered by this service. Assign it manually.', $domain));
            }

            return $this->result($service, $domain);
        }
        if (($service->remote['submitted'] ?? null) === 'transfer') {
            return $this->result($service, $domain); // transfer requested earlier, waiting for the losing registrar
        }

        try {
            $contactId = $this->contacts->ensure($owner, $integration->id, $registrar);
        } catch (InvalidArgumentException $exception) {
            throw new IntegrationException($exception->getMessage());
        }
        $nameservers = $this->defaultNameservers($integration);
        $authCode = $this->authCode($service);

        $this->services->mergeRemote($service->id, ['domain' => $domain, 'contact_id' => $contactId, 'submitted' => $authCode !== null ? 'transfer' : 'register']);
        if ($authCode !== null) {
            $registrar->transfer($domain, $authCode, $contactId, $nameservers);
            $this->forgetAuthCode($service);
        } else {
            $registrar->register($domain, $contactId, $nameservers, 1);
        }

        return $this->result($service, $domain);
    }

    public function suspend(Service $service): void
    {
        [, $registrar] = $this->client($service);
        $registrar->setRenewalMode($this->domain($service), Registrar::EXPIRE);
    }

    public function unsuspend(Service $service): void
    {
        [, $registrar] = $this->client($service);
        $registrar->setRenewalMode($this->domain($service), Registrar::RENEW);
    }

    public function terminate(Service $service): void
    {
        if (($service->remote['submitted'] ?? null) === null) {
            return;
        }
        [, $registrar] = $this->client($service);
        if ($registrar->info($this->domain($service)) !== null) {
            $registrar->setRenewalMode($this->domain($service), Registrar::DELETE);
        }
    }

    public function status(Service $service): array
    {
        [, $registrar] = $this->client($service);
        $domain = $this->domain($service);
        $info = $registrar->info($domain);
        if ($info === null) {
            return ['state' => ($service->remote['submitted'] ?? null) === 'transfer' ? 'transfer pending' : 'pending', 'connection' => ['Domain' => $domain]];
        }

        return [
            'state' => strtoupper($info['status']) === 'OK' ? 'active' : mb_strtolower($info['status']),
            'connection' => array_filter([
                'Domain' => $domain,
                'Paid until (registry)' => (string) ($info['expires'] ?? ''),
                'Name servers' => implode(', ', $info['nameservers']),
            ]),
        ];
    }

    public function actions(Service $service): array
    {
        // Host names are case-insensitive; they are lower-cased before use.
        $hostname = 'required|string|max:253|regex:/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i';
        $optional = 'nullable|string|max:253|regex:/^((?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63})?$/i';

        return [
            'nameservers' => new ServiceAction('Change name servers', [
                'ns1' => ['label' => 'Name server 1', 'rules' => $hostname],
                'ns2' => ['label' => 'Name server 2', 'rules' => $hostname],
                'ns3' => ['label' => 'Name server 3 (optional)', 'rules' => $optional],
                'ns4' => ['label' => 'Name server 4 (optional)', 'rules' => $optional],
            ], 'Change the name servers? Wrong name servers make the domain unreachable.'),
            'auth_code' => ServiceAction::simple('Show auth code (transfer away)', 'Request the auth code? With it the domain can be transferred to another provider.'),
        ];
    }

    public function perform(Service $service, string $action, array $input = []): string
    {
        [, $registrar] = $this->client($service);
        $domain = $this->domain($service);

        if ($action === 'nameservers') {
            $nameservers = array_values(array_filter(array_map(static fn (mixed $v): string => mb_strtolower(trim((string) $v)), [$input['ns1'] ?? '', $input['ns2'] ?? '', $input['ns3'] ?? '', $input['ns4'] ?? '']), static fn (string $v): bool => $v !== ''));
            $registrar->setNameservers($domain, $nameservers);

            return 'The name servers have been updated. Changes can take up to 24 hours to propagate.';
        }
        if ($action === 'auth_code') {
            $code = $registrar->info($domain)['auth_code'] ?? null;
            if ($code === null) {
                throw new IntegrationException('The registry did not provide an auth code for this domain.');
            }
            $this->services->setCredentials($service->id, ['auth_code' => $code] + $this->services->credentials($service));

            return 'The auth code is shown under access data.';
        }

        throw new IntegrationException('Unsupported action.');
    }

    public function prepareReinstall(Service $service, array $input): void
    {
        throw new ServiceStateException('Domains cannot be reinstalled.');
    }

    private function result(Service $service, string $domain): ProvisionResult
    {
        return new ProvisionResult('domain:' . $domain, ['domain' => $domain], $this->services->credentials($service));
    }

    private function domain(Service $service): string
    {
        $domain = mb_strtolower((string) $service->option('domain', ''));
        if (!$this->matchesTld($domain, (string) $service->config('tld', ''))) {
            throw new IntegrationException('The service has no valid domain for the product\'s TLD.');
        }

        return $domain;
    }

    private function matchesTld(string $domain, string $tld): bool
    {
        $tld = ltrim(mb_strtolower(trim($tld)), '.');

        return $tld !== ''
            && preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) === 1
            && str_ends_with($domain, '.' . $tld)
            && !str_contains(substr($domain, 0, -strlen($tld) - 1), '.');
    }

    private function authCode(Service $service): ?string
    {
        $value = $service->option('auth_code');
        if (is_array($value) && is_string($value['encrypted'] ?? null)) {
            return $this->encrypter->decrypt($value['encrypted'], 'order-option:auth_code');
        }

        return null;
    }

    private function forgetAuthCode(Service $service): void
    {
        $config = $service->config;
        if (is_array($config['_options'] ?? null)) {
            unset($config['_options']['auth_code']);
            $this->services->update($service->id, ['config' => $config]);
        }
    }

    /**
     * @return list<string>
     */
    private function defaultNameservers(Integration $integration): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $integration->config('nameservers', '')) ?: [])));
    }

    /**
     * @return array{0: Integration, 1: Registrar}
     */
    private function client(Service $service): array
    {
        if ($service->integrationId === null) {
            throw new IntegrationException('No registrar integration is assigned to this service.');
        }
        $integration = $this->integrations->get($service->integrationId);

        return [$integration, $this->integrations->registrar($integration)];
    }
}
