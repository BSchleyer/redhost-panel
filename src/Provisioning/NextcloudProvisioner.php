<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Auth\User;
use Nebrix\Integration\Integration;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\Nextcloud\NextcloudClient;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;

/**
 * One Nextcloud account per service with user ID <prefix><service-id>.
 * Suspension disables the account (data is kept); termination deletes it.
 */
final class NextcloudProvisioner implements Provisioner
{
    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly ServiceRepository $services,
    ) {
    }

    /**
     * @return array{0: Integration, 1: NextcloudClient}
     */
    private function client(Service $service): array
    {
        if ($service->integrationId === null) {
            throw new IntegrationException('No Nextcloud integration is assigned to this service.');
        }
        $integration = $this->integrations->get($service->integrationId);

        return [$integration, $this->integrations->nextcloud($integration)];
    }

    private function userId(Integration $integration, Service $service): string
    {
        return (string) $integration->config('user_prefix', 'nx') . $service->id;
    }

    private function quota(Service $service): string
    {
        return max(1, (int) $service->config('quota_gb', 1)) . ' GB';
    }

    public function provision(Service $service, User $owner): ProvisionResult
    {
        [$integration, $client] = $this->client($service);
        $userId = $this->userId($integration, $service);
        $credentials = $this->services->credentials($service);

        $existing = $client->findUser($userId);
        if ($existing === null) {
            $password = ProxmoxProvisioner::generatePassword(24);
            // Store first: if the job dies after the remote call, the password is not lost.
            $credentials = ['url' => rtrim((string) $integration->config('base_url'), '/'), 'username' => $userId, 'password' => $password];
            $this->services->setCredentials($service->id, $credentials);

            $group = (string) $service->config('group', '');
            $client->createUser($userId, $password, $owner->email, $owner->displayName(), $this->quota($service), $group !== '' ? [$group] : []);
        } else {
            // Account exists from an earlier attempt: make sure it matches the product.
            $client->setQuota($userId, $this->quota($service));
            if (($existing['enabled'] ?? true) === false) {
                $client->enableUser($userId);
            }
        }

        return new ProvisionResult($userId, ['user_id' => $userId], $credentials);
    }

    public function suspend(Service $service): void
    {
        [$integration, $client] = $this->client($service);
        if ($client->findUser($this->userId($integration, $service)) !== null) {
            $client->disableUser($this->userId($integration, $service));
        }
    }

    public function unsuspend(Service $service): void
    {
        [$integration, $client] = $this->client($service);
        $client->enableUser($this->userId($integration, $service));
    }

    public function terminate(Service $service): void
    {
        [$integration, $client] = $this->client($service);
        if ($client->findUser($this->userId($integration, $service)) !== null) {
            $client->deleteUser($this->userId($integration, $service));
        }
    }

    public function status(Service $service): array
    {
        [$integration, $client] = $this->client($service);
        $user = $client->findUser($this->userId($integration, $service));
        if ($user === null) {
            return ['state' => 'missing'];
        }

        $quota = is_array($user['quota'] ?? null) ? $user['quota'] : [];

        return [
            'state' => ($user['enabled'] ?? true) ? 'active' : 'disabled',
            'storage_used' => (int) ($quota['used'] ?? 0),
            'storage_total' => (int) ($quota['quota'] ?? 0) > 0 ? (int) $quota['quota'] : (int) $service->config('quota_gb', 0) * 1024 ** 3,
            'storage_percent' => round((float) ($quota['relative'] ?? 0), 1),
            'connection' => ['URL' => rtrim((string) $integration->config('base_url'), '/'), 'User name' => $this->userId($integration, $service)],
        ];
    }

    public function actions(Service $service): array
    {
        return ['reset_password' => ServiceAction::simple('Generate a new password', 'Generate a new password? The current password stops working.')];
    }

    public function perform(Service $service, string $action, array $input = []): string
    {
        if ($action !== 'reset_password') {
            throw new IntegrationException('Unsupported action.');
        }

        [$integration, $client] = $this->client($service);
        $password = ProxmoxProvisioner::generatePassword(24);
        $client->setPassword($this->userId($integration, $service), $password);
        $this->services->setCredentials($service->id, ['password' => $password] + $this->services->credentials($service));

        return 'A new password has been set. You can view it under access data.';
    }

    public function prepareReinstall(Service $service, array $input): void
    {
        throw new IntegrationException('Reinstalling is not supported for this service type.');
    }
}
