<?php

declare(strict_types=1);

namespace Nebrix\Integration\Nextcloud;

use Nebrix\Integration\HealthResult;
use Nebrix\Integration\Http\ApiClient;
use Nebrix\Integration\IntegrationException;

/**
 * Nextcloud user provisioning via the OCS API
 * (https://docs.nextcloud.com/server/latest/admin_manual/configuration_user/instruction_set_for_users.html).
 *
 * Requests use OCS v2 with format=json and the OCS-APIRequest header; the
 * admin authenticates with an app password (HTTP basic auth over TLS).
 */
class NextcloudClient
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    public function healthCheck(): HealthResult
    {
        try {
            $status = $this->api->request('GET', '/status.php', ['action' => 'status'])['json'];
            if (!is_array($status) || ($status['installed'] ?? false) !== true) {
                return HealthResult::down('Nextcloud is not installed or did not answer correctly.');
            }
            $version = (string) ($status['versionstring'] ?? $status['version'] ?? 'unknown');
            if (($status['maintenance'] ?? false) === true) {
                return HealthResult::degraded(sprintf('Nextcloud %s is in maintenance mode.', $version));
            }

            // Verifies that the admin credentials work and have provisioning rights.
            $this->ocs('GET', '/ocs/v2.php/cloud/users', ['query' => ['limit' => 1], 'action' => 'users.list']);
        } catch (IntegrationException $exception) {
            return HealthResult::down($exception->getMessage());
        }

        return HealthResult::healthy(sprintf('Nextcloud %s, provisioning API reachable', $version));
    }

    /**
     * @return array<string, mixed>|null null when the user does not exist
     */
    public function findUser(string $userId): ?array
    {
        try {
            $data = $this->ocs('GET', '/ocs/v2.php/cloud/users/' . $this->userId($userId), ['action' => 'users.show']);
        } catch (IntegrationException $exception) {
            if ($exception->isNotFound()) {
                return null;
            }

            throw $exception;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param list<string> $groups
     */
    public function createUser(string $userId, #[\SensitiveParameter] string $password, string $email, string $displayName, string $quota, array $groups = []): void
    {
        $params = [
            'userid' => $this->userId($userId),
            'password' => $password,
            'email' => $email,
            'displayName' => mb_substr($displayName, 0, 64),
            'quota' => $this->quota($quota),
        ];
        foreach ($groups as $index => $group) {
            $params['groups[' . $index . ']'] = $group;
        }

        $this->ocs('POST', '/ocs/v2.php/cloud/users', ['form_params' => $params, 'action' => 'users.create']);
    }

    public function setQuota(string $userId, string $quota): void
    {
        $this->editUser($userId, 'quota', $this->quota($quota));
    }

    public function setPassword(string $userId, #[\SensitiveParameter] string $password): void
    {
        $this->editUser($userId, 'password', $password);
    }

    public function disableUser(string $userId): void
    {
        $this->ocs('PUT', '/ocs/v2.php/cloud/users/' . $this->userId($userId) . '/disable', ['action' => 'users.disable']);
    }

    public function enableUser(string $userId): void
    {
        $this->ocs('PUT', '/ocs/v2.php/cloud/users/' . $this->userId($userId) . '/enable', ['action' => 'users.enable']);
    }

    public function deleteUser(string $userId): void
    {
        $this->ocs('DELETE', '/ocs/v2.php/cloud/users/' . $this->userId($userId), ['action' => 'users.delete']);
    }

    private function editUser(string $userId, string $key, string $value): void
    {
        $this->ocs('PUT', '/ocs/v2.php/cloud/users/' . $this->userId($userId), [
            'form_params' => ['key' => $key, 'value' => $value],
            'action' => 'users.edit.' . $key,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function ocs(string $method, string $path, array $options): mixed
    {
        $options['query'] = ($options['query'] ?? []) + ['format' => 'json'];
        $options['headers'] = ['OCS-APIRequest' => 'true'];

        $json = $this->api->request($method, $path, $options)['json'];
        if (!is_array($json) || !isset($json['ocs']['meta'])) {
            throw new IntegrationException('Unexpected response from Nextcloud.');
        }

        $status = (int) ($json['ocs']['meta']['statuscode'] ?? 0);
        if ($status !== 200 && $status !== 100) {
            throw new IntegrationException(sprintf('Nextcloud error %d: %s', $status, (string) ($json['ocs']['meta']['message'] ?? 'unknown error')), $status === 998 || $status === 404 ? 404 : 400);
        }

        return $json['ocs']['data'] ?? null;
    }

    private function userId(string $userId): string
    {
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $userId) !== 1) {
            throw new IntegrationException('Invalid Nextcloud user ID.');
        }

        return $userId;
    }

    private function quota(string $quota): string
    {
        if (preg_match('/^(none|\d+ (B|KB|MB|GB|TB))$/', $quota) !== 1) {
            throw new IntegrationException('Invalid quota.');
        }

        return $quota;
    }
}
