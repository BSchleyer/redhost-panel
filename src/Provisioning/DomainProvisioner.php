<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use InvalidArgumentException;
use Nebrix\Auth\User;
use Nebrix\Billing\OrderException;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
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
 * - A domain can be held by one live service only (checked before the order
 *   and again before provisioning).
 * - Registration/transfer uses a registrant contact created from the
 *   customer profile. The submission is recorded only after the registrar
 *   accepted it; the service stays in provisioning until the registry
 *   reports the domain as active (transfers: up to 10 days). Every later
 *   action checks that the domain is still in the account with this
 *   service's registrant contact.
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
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    public function checkOrder(Integration $integration, array $config, User $user, array $options): void
    {
        $domain = mb_strtolower((string) ($options['domain'] ?? ''));
        if (!$this->matchesTld($domain, (string) ($config['tld'] ?? ''))) {
            throw new OrderException(sprintf('This product is only available for %s domains.', '.' . ltrim((string) ($config['tld'] ?? ''), '.')));
        }
        if ($this->heldByOtherService($domain, 0)) {
            throw new OrderException(sprintf('%s is already managed in this panel.', $domain));
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
        if ($this->heldByOtherService($domain, $service->id)) {
            throw new IntegrationException(sprintf('%s is already held by another service.', $domain));
        }
        $remote = $service->remote;

        $info = $registrar->info($domain);
        if ($info !== null) {
            if (!$this->owns($remote, $info)) {
                throw new IntegrationException(sprintf('%s is already in the registrar account but was not registered by this service. Assign it manually.', $domain));
            }
            if (isset($remote['submitting'])) {
                $remote = $this->merge($service, ['submitted' => $remote['submitting'], 'submitting' => null, 'submitted_at' => $remote['submitted_at'] ?? $this->clock->now()->getTimestamp()]);
            }

            return $this->completedOrWait($service, $remote, $info);
        }
        if (isset($remote['submitted'])) {
            // Accepted earlier; a transfer waits for the losing registrar.
            return $this->completedOrWait($service, $remote, null);
        }

        try {
            $contactId = $this->contacts->ensure($owner, $integration->id, $registrar);
        } catch (InvalidArgumentException $exception) {
            throw new IntegrationException($exception->getMessage());
        }
        $nameservers = $this->defaultNameservers($integration);
        $authCode = $this->authCode($service);
        $mode = $authCode !== null ? 'transfer' : 'register';

        // "submitting" lets a retry after a crash recognise its own domain (same registrant contact).
        $this->merge($service, ['domain' => $domain, 'contact_id' => $contactId, 'submitting' => $mode]);
        if ($authCode !== null) {
            $registrar->transfer($domain, $authCode, $contactId, $nameservers);
        } else {
            $registrar->register($domain, $contactId, $nameservers, 1);
        }
        // Only an accepted request counts as submitted.
        $remote = $this->merge($service, ['submitted' => $mode, 'submitting' => null, 'submitted_at' => $this->clock->now()->getTimestamp()]);
        if ($authCode !== null) {
            $this->forgetAuthCode($service);
        }

        $info = $registrar->info($domain);
        if ($info !== null && !$this->owns($remote, $info)) {
            throw new IntegrationException(sprintf('%s appeared in the registrar account with another registrant.', $domain));
        }

        return $this->completedOrWait($service, $remote, $info);
    }

    public function suspend(Service $service): void
    {
        [, $registrar] = $this->client($service);
        $this->ownedInfo($service, $registrar);
        $registrar->setRenewalMode($this->domain($service), Registrar::EXPIRE);
    }

    public function unsuspend(Service $service): void
    {
        [, $registrar] = $this->client($service);
        $this->ownedInfo($service, $registrar);
        $registrar->setRenewalMode($this->domain($service), Registrar::RENEW);
    }

    public function terminate(Service $service): void
    {
        if (($service->remote['submitted'] ?? null) === null) {
            return; // never accepted by the registrar
        }
        [, $registrar] = $this->client($service);
        $info = $registrar->info($this->domain($service));
        if ($info === null) {
            return; // not (or no longer) in the account
        }
        if (!$this->owns($service->remote, $info)) {
            throw new IntegrationException('The domain in the registrar account does not belong to this service; nothing was changed.');
        }
        $registrar->setRenewalMode($this->domain($service), Registrar::DELETE);
    }

    /**
     * @param array<string, mixed> $remote
     * @param array{status: string, expires: string|null, nameservers: list<string>, auth_code: string|null, registrant: string|null}|null $info
     */
    private function completedOrWait(Service $service, array $remote, ?array $info): ProvisionResult
    {
        $domain = $this->domain($service);
        if ($info !== null && strtoupper($info['status']) === 'OK') {
            return $this->result($service, $domain);
        }

        $transfer = ($remote['submitted'] ?? null) === 'transfer';
        $deadline = (int) ($remote['submitted_at'] ?? 0) + ($transfer ? 10 * 86400 : 2 * 86400);
        if ($this->clock->now()->getTimestamp() > $deadline) {
            throw new IntegrationException(sprintf('The %s of %s was not completed in time (status: %s). Check it at the registrar.', $transfer ? 'transfer' : 'registration', $domain, $info['status'] ?? 'not in the account'));
        }

        throw new RetryLaterException(sprintf('Waiting for the registry to complete the %s of %s.', $transfer ? 'transfer' : 'registration', $domain), $transfer ? 3600 : 300);
    }

    /**
     * The domain belongs to the service when the service submitted it and
     * the registrar reports the service's registrant contact.
     *
     * @param array<string, mixed> $remote
     * @param array{registrant: string|null} $info
     */
    private function owns(array $remote, array $info): bool
    {
        if (($remote['submitted'] ?? $remote['submitting'] ?? null) === null) {
            return false;
        }

        return $info['registrant'] === null || $info['registrant'] === (string) ($remote['contact_id'] ?? '');
    }

    /**
     * @return array{status: string, expires: string|null, nameservers: list<string>, auth_code: string|null, registrant: string|null}
     */
    private function ownedInfo(Service $service, Registrar $registrar): array
    {
        $info = $registrar->info($this->domain($service));
        if ($info === null || !$this->owns($service->remote, $info)) {
            throw new IntegrationException('The domain is not (or no longer) managed by this service.');
        }

        return $info;
    }

    private function heldByOtherService(string $domain, int $exceptServiceId): bool
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM `nx_services` WHERE `category` = 'domain' AND `id` <> ? AND `status` NOT IN ('terminated', 'cancelled', 'failed')
             AND (JSON_UNQUOTE(JSON_EXTRACT(`config`, '$._options.domain')) = ? OR JSON_UNQUOTE(JSON_EXTRACT(`remote`, '$.domain')) = ?)",
            [$exceptServiceId, $domain, $domain],
        ) > 0;
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function merge(Service $service, array $changes): array
    {
        $this->services->mergeRemote($service->id, $changes);

        return $this->services->find($service->id)->remote ?? [];
    }

    public function status(Service $service): array
    {
        [, $registrar] = $this->client($service);
        $domain = $this->domain($service);
        $info = $registrar->info($domain);
        if ($info === null) {
            return ['state' => ($service->remote['submitted'] ?? null) === 'transfer' ? 'transfer pending' : 'pending', 'connection' => ['Domain' => $domain]];
        }
        if (!$this->owns($service->remote, $info)) {
            throw new IntegrationException('The domain is not (or no longer) managed by this service.');
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
        $info = $this->ownedInfo($service, $registrar);

        if ($action === 'nameservers') {
            $nameservers = array_values(array_filter(array_map(static fn (mixed $v): string => mb_strtolower(trim((string) $v)), [$input['ns1'] ?? '', $input['ns2'] ?? '', $input['ns3'] ?? '', $input['ns4'] ?? '']), static fn (string $v): bool => $v !== ''));
            $registrar->setNameservers($domain, $nameservers);

            return 'The name servers have been updated. Changes can take up to 24 hours to propagate.';
        }
        if ($action === 'auth_code') {
            $code = $info['auth_code'];
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
