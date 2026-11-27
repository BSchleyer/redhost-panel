<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Auth\User;
use Nebrix\Database\Connection;
use Nebrix\Integration\Integration;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\Plesk\PleskClient;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;

/**
 * Webhosting on Plesk: one Plesk customer per Nebrix customer (external ID
 * "nebrix-user-<id>", login "nx<id>") and one subscription (webspace) per
 * service for the ordered domain with the product's service plan.
 *
 * Idempotency: passwords are stored before the remote calls; a retry finds
 * the customer by its external ID and the subscription by its domain and
 * adopts it only if it belongs to that customer.
 */
final class PleskProvisioner implements Provisioner
{
    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly ServiceRepository $services,
        private readonly Connection $db,
    ) {
    }

    public function provision(Service $service, User $owner): ProvisionResult
    {
        [$integration, $client] = $this->client($service);
        $domain = $this->domain($service);
        $plan = (string) $service->config('plan', '');
        if ($plan === '') {
            throw new IntegrationException('The product needs the name of a Plesk service plan.');
        }

        // 1. Customer account (shared by all webhosting services of the customer).
        $credentials = $this->services->credentials($service);
        $customerId = $client->findCustomerByExternalId('nebrix-user-' . $owner->id);
        if ($customerId === null) {
            if (($credentials['password'] ?? '') === '') {
                $credentials = ['panel_url' => $this->panelUrl($integration), 'username' => 'nx' . $owner->id, 'password' => ProxmoxProvisioner::generatePassword(20)];
                $this->services->setCredentials($service->id, $credentials);
            }
            $customerId = $client->createCustomer([
                'name' => mb_substr(trim($owner->displayName()) !== '' ? $owner->displayName() : 'Customer ' . $owner->id, 0, 60),
                'company' => mb_substr((string) ($owner->attribute('company') ?? ''), 0, 60),
                'login' => 'nx' . $owner->id,
                'password' => (string) $credentials['password'],
                'email' => $owner->email,
                'external_id' => 'nebrix-user-' . $owner->id,
            ]);
        } elseif (($credentials['password'] ?? '') === '') {
            $credentials = $this->existingCredentials($owner->id, $integration->id, $service->id)
                ?? ['panel_url' => $this->panelUrl($integration), 'username' => 'nx' . $owner->id, 'note' => 'Use your existing Plesk login.'];
            $this->services->setCredentials($service->id, $credentials);
        }
        $this->services->mergeRemote($service->id, ['customer_id' => $customerId, 'domain' => $domain]);

        // 2. Subscription for the domain.
        $ftpLogin = 'nx' . $service->id;
        if (($credentials['ftp_password'] ?? '') === '') {
            $credentials += ['ftp_user' => $ftpLogin, 'ftp_password' => ProxmoxProvisioner::generatePassword(20)];
            $this->services->setCredentials($service->id, $credentials);
        }
        $webspace = $client->findWebspace($domain);
        if ($webspace !== null) {
            if ($webspace['owner_id'] !== $customerId) {
                throw new IntegrationException(sprintf('The domain %s already exists on the Plesk server for another customer.', $domain));
            }
            $webspaceId = $webspace['id'];
        } else {
            $webspaceId = $client->createWebspace($domain, $customerId, (string) $integration->config('ip_address'), $ftpLogin, (string) $credentials['ftp_password'], $plan);
        }
        $this->services->mergeRemote($service->id, ['webspace_id' => $webspaceId]);

        return new ProvisionResult('plesk:' . $webspaceId, ['webspace_id' => $webspaceId, 'customer_id' => $customerId, 'domain' => $domain], $credentials);
    }

    public function suspend(Service $service): void
    {
        [, $client] = $this->client($service);
        $client->setWebspaceStatus($this->webspaceId($service), PleskClient::STATUS_SUSPENDED);
    }

    public function unsuspend(Service $service): void
    {
        [, $client] = $this->client($service);
        $client->setWebspaceStatus($this->webspaceId($service), PleskClient::STATUS_ACTIVE);
    }

    public function terminate(Service $service): void
    {
        if (!isset($service->remote['webspace_id'])) {
            return;
        }
        [, $client] = $this->client($service);
        $webspace = $client->findWebspace((string) ($service->remote['domain'] ?? ''));
        if ($webspace === null) {
            return; // already deleted
        }
        if ($webspace['id'] !== $this->webspaceId($service) || $webspace['owner_id'] !== (int) ($service->remote['customer_id'] ?? 0)) {
            throw new IntegrationException('The subscription on the Plesk server does not match this service; nothing was deleted.');
        }
        $client->deleteWebspace($webspace['id']);
    }

    public function status(Service $service): array
    {
        [$integration, $client] = $this->client($service);
        $webspace = $client->findWebspace((string) ($service->remote['domain'] ?? ''));
        if ($webspace === null) {
            throw new IntegrationException('The subscription was not found on the Plesk server.');
        }

        return [
            'state' => $webspace['status'] === PleskClient::STATUS_ACTIVE ? 'active' : 'suspended',
            'connection' => ['Domain' => $webspace['name'], 'Control panel' => $this->panelUrl($integration)],
        ];
    }

    public function actions(Service $service): array
    {
        return [
            'login' => new ServiceAction('Open Plesk', [], null, ServiceAction::REDIRECT),
            'reset_password' => ServiceAction::simple('Reset Plesk password', 'Set a new password for your Plesk login? It applies to all your webhosting subscriptions.'),
        ];
    }

    public function perform(Service $service, string $action, array $input = []): string
    {
        [$integration, $client] = $this->client($service);
        $customerId = (int) ($service->remote['customer_id'] ?? 0);
        if ($customerId < 1) {
            throw new IntegrationException('The subscription has not been created yet.');
        }

        if ($action === 'login') {
            $login = 'nx' . $service->userId;
            $session = $client->createSession($login, (string) ($input['_client_ip'] ?? '127.0.0.1'));

            return $this->panelUrl($integration) . '/enterprise/rsession_init.php?PHPSESSID=' . rawurlencode($session);
        }
        if ($action === 'reset_password') {
            $password = ProxmoxProvisioner::generatePassword(20);
            $client->setCustomerPassword($customerId, $password);
            // The Plesk login is shared: update the stored password on every service of the customer.
            foreach ($this->db->fetchAll("SELECT `id` FROM `nx_services` WHERE `user_id` = ? AND `integration_id` = ? AND `status` NOT IN ('terminated', 'cancelled')", [$service->userId, $integration->id]) as $row) {
                $other = $this->services->find((int) $row['id']);
                if ($other !== null) {
                    $this->services->setCredentials($other->id, ['password' => $password, 'username' => 'nx' . $service->userId] + $this->services->credentials($other));
                }
            }

            return 'A new Plesk password has been set. You can view it under access data.';
        }

        throw new IntegrationException('Unsupported action.');
    }

    public function prepareReinstall(Service $service, array $input): void
    {
        throw new ServiceStateException('Reinstalling is not available for webhosting.');
    }

    private function domain(Service $service): string
    {
        $domain = mb_strtolower((string) $service->option('domain', ''));
        if (preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) !== 1) {
            throw new IntegrationException('The service has no valid domain.');
        }

        return $domain;
    }

    private function webspaceId(Service $service): int
    {
        $id = (int) ($service->remote['webspace_id'] ?? 0);
        if ($id < 1) {
            throw new IntegrationException('The subscription has not been created yet.');
        }

        return $id;
    }

    private function panelUrl(Integration $integration): string
    {
        return rtrim((string) $integration->config('base_url'), '/');
    }

    /**
     * @return array<string, scalar|null>|null
     */
    private function existingCredentials(int $userId, int $integrationId, int $exceptServiceId): ?array
    {
        foreach ($this->db->fetchAll('SELECT `id` FROM `nx_services` WHERE `user_id` = ? AND `integration_id` = ? AND `id` <> ? AND `credentials` IS NOT NULL ORDER BY `id`', [$userId, $integrationId, $exceptServiceId]) as $row) {
            $other = $this->services->find((int) $row['id']);
            $credentials = $other !== null ? $this->services->credentials($other) : [];
            if (($credentials['password'] ?? '') !== '') {
                return ['panel_url' => $credentials['panel_url'] ?? null, 'username' => $credentials['username'] ?? null, 'password' => $credentials['password']];
            }
        }

        return null;
    }

    /**
     * @return array{0: Integration, 1: PleskClient}
     */
    private function client(Service $service): array
    {
        if ($service->integrationId === null) {
            throw new IntegrationException('No Plesk integration is assigned to this service.');
        }
        $integration = $this->integrations->get($service->integrationId);

        return [$integration, $this->integrations->plesk($integration)];
    }
}
