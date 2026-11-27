<?php

declare(strict_types=1);

namespace Nebrix\RemoteConsole;

use Nebrix\Auth\User;
use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Integration\IntegrationException;
use Nebrix\Provisioning\ConsoleProvider;
use Nebrix\Provisioning\ProvisionerRegistry;
use Nebrix\Provisioning\ServiceStateException;
use Nebrix\Security\Encrypter;
use Nebrix\Service\Service;

/**
 * Single-use console sessions.
 *
 * The panel asks the provisioner for a console ticket and stores the
 * upstream target (including the API credentials) encrypted in the
 * database, keyed by the SHA-256 hash of a random 256-bit token. The browser
 * receives only the token and the VNC password; the console proxy redeems
 * the token exactly once within 60 seconds.
 */
class ConsoleSessions
{
    public const TTL_SECONDS = 60;

    public function __construct(
        private readonly Connection $db,
        private readonly Encrypter $encrypter,
        private readonly ProvisionerRegistry $provisioners,
        private readonly Clock $clock,
        private readonly Config $config,
    ) {
    }

    public function supports(Service $service): bool
    {
        return $service->isActive()
            && $this->provisioners->supportsService($service)
            && $this->provisioners->for($service->category) instanceof ConsoleProvider;
    }

    /**
     * @return array{url: string, password: string, expires_in: int}
     *
     * @throws ServiceStateException|IntegrationException
     */
    public function open(Service $service, User $user, ?string $ip): array
    {
        if (!$this->supports($service)) {
            throw new ServiceStateException('A console is not available for this service.');
        }
        $provider = $this->provisioners->for($service->category);
        if (!$provider instanceof ConsoleProvider) {
            throw new ServiceStateException('A console is not available for this service.');
        }

        $target = $provider->openConsole($service);
        $password = $target['password'];
        unset($target['password']);

        $token = bin2hex(random_bytes(32));
        $now = $this->clock->now();
        $id = $this->db->insert('nx_console_sessions', [
            'token_hash' => hash('sha256', $token),
            'service_id' => $service->id,
            'user_id' => $user->id,
            'integration_id' => (int) $service->integrationId,
            'target' => '',
            'ip' => $ip,
            'expires_at' => $now->modify('+' . self::TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);
        $this->db->update('nx_console_sessions', ['target' => $this->encrypter->encryptArray($target, 'console:' . $id)], ['id' => $id]);

        return [
            'url' => $this->config->string('console.ws_url') . '?token=' . $token,
            'password' => $password,
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    /**
     * Redeems a token (once). Returns the upstream target or null when the
     * token is unknown, used, expired, or the service/user is no longer
     * active.
     *
     * @return array{session_id: int, service_id: int, user_id: int, scheme: string, host: string, ip: string, port: int, path: string, headers: array<string, string>, verify_tls: bool}|null
     */
    public function redeem(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $hash = hash('sha256', $token);
        $now = $this->clock->nowString();

        $claimed = $this->db->run(
            'UPDATE `nx_console_sessions` SET `used_at` = ? WHERE `token_hash` = ? AND `used_at` IS NULL AND `expires_at` > ?',
            [$now, $hash, $now],
        )->rowCount();
        if ($claimed !== 1) {
            return null;
        }

        $row = $this->db->fetchOne(
            "SELECT c.`id`, c.`service_id`, c.`user_id`, c.`target`, s.`status` AS service_status, u.`status` AS user_status
             FROM `nx_console_sessions` c JOIN `nx_services` s ON s.`id` = c.`service_id` JOIN `nx_users` u ON u.`id` = c.`user_id`
             WHERE c.`token_hash` = ?",
            [$hash],
        );
        if ($row === null || $row['service_status'] !== Service::STATUS_ACTIVE || $row['user_status'] !== User::STATUS_ACTIVE) {
            return null;
        }

        $target = $this->encrypter->decryptArray((string) $row['target'], 'console:' . $row['id']);
        if (!isset($target['scheme'], $target['host'], $target['ip'], $target['port'], $target['path'])) {
            return null;
        }

        return [
            'session_id' => (int) $row['id'],
            'service_id' => (int) $row['service_id'],
            'user_id' => (int) $row['user_id'],
            'scheme' => (string) $target['scheme'],
            'host' => (string) $target['host'],
            'ip' => (string) $target['ip'],
            'port' => (int) $target['port'],
            'path' => (string) $target['path'],
            'headers' => array_map('strval', is_array($target['headers'] ?? null) ? $target['headers'] : []),
            'verify_tls' => (bool) ($target['verify_tls'] ?? true),
        ];
    }

    /** Removes sessions older than a day (scheduler cleanup). */
    public function prune(): int
    {
        return $this->db->run('DELETE FROM `nx_console_sessions` WHERE `expires_at` < ?', [$this->clock->now()->modify('-1 day')->format('Y-m-d H:i:s')])->rowCount();
    }
}
