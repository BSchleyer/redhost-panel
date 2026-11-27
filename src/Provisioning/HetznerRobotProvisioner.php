<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Auth\User;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Integration\Hetzner\RobotClient;
use Nebrix\Integration\Integration;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;

/**
 * Dedicated servers through the Hetzner Robot API.
 *
 * Modes (product setting "mode"):
 * - pool:  a server of the Robot account whose name starts with the pool
 *          prefix is reserved for the service (database lock, never two
 *          services per server) and renamed to "nebrix-service-<id>".
 * - order: a new server is ordered (only if the integration allows orders;
 *          test mode by default). The order carries the comment
 *          "nebrix-service-<id>" so a retry finds it instead of ordering twice.
 *
 * Then the Linux installation is activated (Hetzner generates the root
 * password), the server is reset and the installation is awaited. Every step
 * is persisted, so retries continue where they stopped.
 *
 * Suspension enables the Robot firewall without rules (incoming traffic is
 * dropped) and restores the previous firewall afterwards. Termination never
 * deletes data on its own: the server boots the rescue system and is renamed
 * to "nebrix-wipe-<number>" for staff to wipe it before returning it to the
 * pool; ordered servers can optionally be cancelled.
 */
final class HetznerRobotProvisioner implements Provisioner
{
    private const STATUS_TTL = 300;
    private const INSTALL_MIN_SECONDS = 600;
    private const INSTALL_START_TIMEOUT = 1800;

    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly ServiceRepository $services,
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    public static function marker(Service $service): string
    {
        return 'nebrix-service-' . $service->id;
    }

    public function provision(Service $service, User $owner): ProvisionResult
    {
        [$integration, $client] = $this->client($service);
        $remote = $service->remote;
        $mode = (string) $service->config('mode', 'pool');

        if (!isset($remote['server_number'])) {
            if ($mode === 'order') {
                $remote = $this->order($service, $integration, $client, $remote);
            } else {
                $remote = $this->reserveFromPool($service, $integration, $client);
            }
        }

        $number = (int) $remote['server_number'];
        $step = (string) ($remote['step'] ?? 'name');

        if ($step === 'name') {
            $server = $client->server($number);
            $name = (string) ($server['server_name'] ?? '');
            $prefix = (string) $integration->config('pool_prefix', 'nebrix-free');
            $mayClaim = $name === self::marker($service)
                || ($mode === 'pool' && $prefix !== '' && str_starts_with($name, $prefix))
                || ($mode === 'order' && ($remote['ordered'] ?? false) === true);
            if (!$mayClaim) {
                throw new IntegrationException(sprintf('Server #%d is named "%s" and is not available for this service.', $number, $name));
            }
            if ($name !== self::marker($service)) {
                $client->renameServer($number, self::marker($service));
            }
            $remote = $this->merge($service, ['step' => 'install', 'server_ip' => (string) ($server['server_ip'] ?? ''), 'server_ipv6' => (string) ($server['server_ipv6_net'] ?? '')]);
            $step = 'install';
        }

        if ($step === 'install') {
            $image = (string) ($remote['image'] ?? $service->config('image', ''));
            if ($image === '') {
                throw new IntegrationException('No operating system image is configured on the product.');
            }
            // Never install on a server that is not (or no longer) this service's.
            $this->assertMarker($service, $client, $number);
            $credentials = $this->services->credentials($service);
            if (($credentials['password'] ?? '') === '') {
                if (($client->rescue($number)['active'] ?? false) === true) {
                    $client->deactivateRescue($number); // only one boot configuration can be active
                }
                $linux = $client->linuxInstall($number);
                if (($linux['active'] ?? false) === true) {
                    // Activated by an earlier attempt whose password was not stored: start over.
                    $client->deactivateLinuxInstall($number);
                }
                $linux = $client->activateLinuxInstall($number, $image);
                $password = (string) ($linux['password'] ?? '');
                if ($password === '') {
                    throw new IntegrationException('Hetzner did not return an installation password.');
                }
                $credentials = ['host' => (string) ($remote['server_ip'] ?? ''), 'username' => 'root', 'password' => $password];
                $this->services->setCredentials($service->id, $credentials);
            }
            $remote = $this->merge($service, ['step' => 'reset', 'image' => $image]);
            $step = 'reset';
        }

        if ($step === 'reset') {
            $this->assertMarker($service, $client, $number);
            $client->reset($number, 'hw');
            $remote = $this->merge($service, ['step' => 'installing', 'reset_at' => $this->clock->now()->getTimestamp()]);
            $step = 'installing';
        }

        if ($step === 'installing') {
            $elapsed = $this->clock->now()->getTimestamp() - (int) ($remote['reset_at'] ?? 0);
            $linux = $client->linuxInstall($number);
            if (($linux['active'] ?? false) === true) {
                if ($elapsed > self::INSTALL_START_TIMEOUT) {
                    throw new IntegrationException(sprintf('The installation on server #%d did not start within 30 minutes after the reset.', $number));
                }

                throw new RetryLaterException('Waiting for the server to boot the installation system.', 60);
            }
            if ($elapsed < self::INSTALL_MIN_SECONDS) {
                throw new RetryLaterException('Waiting for the installation to finish.', 120);
            }
            $this->merge($service, ['step' => 'done', 'status_cache' => null]);
        }

        $credentials = $this->services->credentials($service);

        return new ProvisionResult('hetzner:' . $number, ['server_number' => $number, 'server_ip' => (string) ($remote['server_ip'] ?? '')], $credentials);
    }

    public function suspend(Service $service): void
    {
        [, $client] = $this->client($service);
        $number = $this->ownedServer($service, $client);
        if (($service->remote['firewall_backup'] ?? null) === null) {
            $firewall = $client->firewall($number);
            if (($firewall['status'] ?? '') === 'in process') {
                throw new RetryLaterException('The Robot firewall is being updated.', 30);
            }
            $this->merge($service, ['firewall_backup' => [
                'status' => (string) ($firewall['status'] ?? 'disabled'),
                'whitelist_hos' => (bool) ($firewall['whitelist_hos'] ?? true),
                'input' => array_values(array_filter((array) ($firewall['rules']['input'] ?? []), 'is_array')),
                'output' => array_values(array_filter((array) ($firewall['rules']['output'] ?? []), 'is_array')),
            ]]);
        }

        try {
            // Active firewall without rules: all incoming traffic is dropped.
            $client->setFirewall($number, 'active', true);
        } catch (IntegrationException $exception) {
            if ($exception->httpStatus() === 409) {
                throw new RetryLaterException('The Robot firewall is being updated.', 30);
            }
            throw $exception;
        }
    }

    public function unsuspend(Service $service): void
    {
        [, $client] = $this->client($service);
        $number = $this->ownedServer($service, $client);
        $backup = $service->remote['firewall_backup'] ?? null;
        if (!is_array($backup)) {
            return; // never suspended remotely
        }

        try {
            /** @var list<array<string, scalar|null>> $input */
            $input = is_array($backup['input'] ?? null) ? $backup['input'] : [];
            /** @var list<array<string, scalar|null>> $output */
            $output = is_array($backup['output'] ?? null) ? $backup['output'] : [];
            $client->setFirewall($number, ($backup['status'] ?? 'disabled') === 'active' ? 'active' : 'disabled', (bool) ($backup['whitelist_hos'] ?? true), $input, $output);
        } catch (IntegrationException $exception) {
            if ($exception->httpStatus() === 409) {
                throw new RetryLaterException('The Robot firewall is being updated.', 30);
            }
            throw $exception;
        }
        $this->merge($service, ['firewall_backup' => null]);
    }

    public function terminate(Service $service): void
    {
        if (!isset($service->remote['server_number'])) {
            return; // nothing was assigned or ordered (an order still in process is handled by staff)
        }
        [$integration, $client] = $this->client($service);
        $number = (int) $service->remote['server_number'];
        $wipeName = 'nebrix-wipe-' . $number;

        $server = $client->server($number);
        $name = (string) ($server['server_name'] ?? '');
        $terminating = ($service->remote['terminating'] ?? false) === true;
        if ($name !== self::marker($service) && !($terminating && $name === $wipeName)) {
            throw new IntegrationException(sprintf('Server #%d is named "%s" and does not belong to this service; nothing was changed.', $number, $name));
        }
        $this->merge($service, ['terminating' => true]);

        // 1. Stop the customer's operating system: boot the rescue system.
        if (($service->remote['rescue_reset'] ?? false) !== true) {
            if (($client->rescue($number)['active'] ?? false) !== true) {
                $client->activateRescue($number);
            }
            $client->reset($number, 'hw');
            $this->merge($service, ['rescue_reset' => true]);
        }

        // 2. Optionally cancel servers that were ordered for this service.
        if (($service->remote['ordered'] ?? false) === true && (bool) $integration->config('cancel_on_terminate', false)) {
            $cancellation = $client->cancellation($number);
            if (($cancellation['cancelled'] ?? false) !== true) {
                $date = (string) ($cancellation['earliest_cancellation_date'] ?? 'now');
                $client->cancel($number, preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : 'now');
            }
        }

        // 3. Hand the server to staff for wiping before it returns to the pool.
        if ($name !== $wipeName) {
            $client->renameServer($number, $wipeName);
        }
    }

    public function status(Service $service): array
    {
        [, $client] = $this->client($service);
        $number = (int) ($service->remote['server_number'] ?? 0);
        if ($number < 1) {
            throw new IntegrationException('No server has been assigned yet.');
        }

        // The Robot API has hourly request limits: cache the server data.
        $cache = $service->remote['status_cache'] ?? null;
        if (is_array($cache) && (int) ($cache['at'] ?? 0) > $this->clock->now()->getTimestamp() - self::STATUS_TTL && is_array($cache['server'] ?? null)) {
            $server = $cache['server'];
        } else {
            $server = $client->server($number);
            $server = array_intersect_key($server, array_flip(['server_ip', 'server_ipv6_net', 'product', 'dc', 'status', 'cancelled', 'traffic']));
            $this->merge($service, ['status_cache' => ['at' => $this->clock->now()->getTimestamp(), 'server' => $server]]);
        }

        return [
            'state' => ($server['cancelled'] ?? false) === true ? 'cancelled' : ((string) ($server['status'] ?? '') === 'ready' ? 'running' : (string) ($server['status'] ?? 'unknown')),
            'connection' => array_filter([
                'IPv4' => (string) ($server['server_ip'] ?? ''),
                'IPv6' => (string) ($server['server_ipv6_net'] ?? ''),
                'Model' => (string) ($server['product'] ?? ''),
                'Location' => (string) ($server['dc'] ?? ''),
                'Traffic' => (string) ($server['traffic'] ?? ''),
            ]),
        ];
    }

    public function actions(Service $service): array
    {
        $images = $this->reinstallImages($service);

        return [
            'reboot' => ServiceAction::simple('Reboot (CTRL+ALT+DEL)', 'Send a reboot request to the operating system?'),
            'hard_reset' => ServiceAction::simple('Hardware reset', 'Reset the server immediately? Unsaved data may be lost.', true),
            'power' => ServiceAction::simple('Press power button', 'Send a power button signal?'),
            'rescue' => ServiceAction::simple('Boot rescue system', 'Restart the server into the Linux rescue system? The rescue password is shown under access data.', true),
            'rdns' => new ServiceAction('Set reverse DNS', [
                'ptr' => ['label' => 'Host name (FQDN) for the main IPv4 address', 'rules' => 'required|string|max:253|regex:/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'],
            ]),
            'reinstall' => new ServiceAction('Reinstall operating system', [
                'image' => ['label' => 'Operating system', 'rules' => 'required|string|max:100', 'type' => 'select', 'options' => array_combine($images, $images), 'default' => $images[0] ?? ''],
            ], 'Reinstall the server? ALL data on the disks will be deleted.', ServiceAction::REINSTALL, true),
        ];
    }

    public function perform(Service $service, string $action, array $input = []): string
    {
        [, $client] = $this->client($service);
        $number = $this->ownedServer($service, $client);

        switch ($action) {
            case 'reboot':
                $client->reset($number, 'sw');

                return 'The reboot request has been sent.';
            case 'hard_reset':
                $client->reset($number, 'hw');

                return 'The server is being reset.';
            case 'power':
                $client->reset($number, 'power');

                return 'The power button signal has been sent.';
            case 'rescue':
                if (($client->rescue($number)['active'] ?? false) === true) {
                    throw new IntegrationException('The rescue system is already activated.');
                }
                $rescue = $client->activateRescue($number);
                $client->reset($number, 'hw');
                $this->services->setCredentials($service->id, ['rescue_password' => (string) ($rescue['password'] ?? '')] + $this->services->credentials($service));

                return 'The server restarts into the rescue system. The rescue password is shown under access data.';
            case 'rdns':
                $ip = (string) ($service->remote['server_ip'] ?? '');
                $client->setReverseDns($ip, (string) ($input['ptr'] ?? ''));

                return 'The reverse DNS entry has been updated.';
            default:
                throw new IntegrationException('Unsupported action.');
        }
    }

    public function prepareReinstall(Service $service, array $input): void
    {
        $image = (string) ($input['image'] ?? '');
        if (!in_array($image, $this->reinstallImages($service), true)) {
            throw new ServiceStateException('Choose one of the offered operating systems.');
        }
        if (!isset($service->remote['server_number'])) {
            throw new ServiceStateException('No server has been assigned yet.');
        }
        [, $client] = $this->client($service);
        try {
            $this->assertMarker($service, $client, (int) $service->remote['server_number']);
        } catch (IntegrationException) {
            throw new ServiceStateException('The server cannot be reinstalled right now. Please contact support.');
        }

        // Clearing the password makes the provisioning run activate a new installation.
        $this->services->setCredentials($service->id, []);
        $this->merge($service, ['step' => 'install', 'image' => $image, 'reset_at' => null, 'status_cache' => null]);
    }

    /**
     * @return list<string>
     */
    private function reinstallImages(Service $service): array
    {
        $images = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $service->config('reinstall_images', '')) ?: []), static fn (string $image): bool => preg_match('/^[A-Za-z0-9 ._()+-]{2,100}$/', $image) === 1));
        $default = (string) $service->config('image', '');
        if ($default !== '' && !in_array($default, $images, true)) {
            array_unshift($images, $default);
        }

        return $images;
    }

    /**
     * @param array<string, mixed> $remote
     * @return array<string, mixed>
     */
    private function order(Service $service, Integration $integration, RobotClient $client, array $remote): array
    {
        if (!(bool) $integration->config('allow_orders', false)) {
            throw new IntegrationException('Ordering new servers is disabled for this Hetzner integration.');
        }
        if (($remote['order_test'] ?? false) === true) {
            if ((bool) $integration->config('order_test_mode', true)) {
                throw new IntegrationException('Hetzner accepted the order in test mode (not processed). Disable test mode on the integration and retry to order the server.');
            }
            // Test mode was switched off: place the real order.
            $remote = $this->merge($service, ['order_test' => null, 'transaction_id' => null]);
        }

        if (!isset($remote['transaction_id'])) {
            $product = (string) $service->config('server_product', '');
            $image = (string) $service->config('image', '');
            if ($product === '' || $image === '') {
                throw new IntegrationException('The product needs a Hetzner product ID and an operating system image for ordering.');
            }
            $transaction = null;
            foreach ($client->orderTransactions() as $existing) {
                if ((string) ($existing['comment'] ?? '') === self::marker($service) && ($existing['status'] ?? '') !== 'cancelled') {
                    $transaction = $existing;
                }
            }
            if ($transaction === null) {
                $fingerprint = (string) $integration->config('ssh_key_fingerprint', '');
                $test = (bool) $integration->config('order_test_mode', true);
                $transaction = $client->orderServer($product, (string) $service->config('location', ''), $image, $fingerprint !== '' ? [$fingerprint] : [], self::marker($service), $test);
                if ($test) {
                    $this->merge($service, ['order_test' => true, 'transaction_id' => (string) ($transaction['id'] ?? '')]);

                    throw new IntegrationException('Hetzner accepted the order in test mode (not processed). Disable test mode on the integration and retry to order the server.');
                }
            }
            $remote = $this->merge($service, ['transaction_id' => (string) ($transaction['id'] ?? ''), 'mode' => 'order']);
        }

        $transaction = $client->orderTransaction((string) $remote['transaction_id']);
        $status = (string) ($transaction['status'] ?? '');
        if ($status === 'cancelled') {
            throw new IntegrationException(sprintf('Hetzner cancelled the server order %s.', (string) $remote['transaction_id']));
        }
        if ($status !== 'ready' || (int) ($transaction['server_number'] ?? 0) < 1) {
            throw new RetryLaterException('Waiting for Hetzner to deliver the ordered server.', 600);
        }

        return $this->merge($service, [
            'server_number' => (int) $transaction['server_number'],
            'server_ip' => (string) ($transaction['server_ip'] ?? ''),
            'ordered' => true,
            'step' => 'name',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reserveFromPool(Service $service, Integration $integration, RobotClient $client): array
    {
        $prefix = (string) $integration->config('pool_prefix', 'nebrix-free');
        if (!self::isSafePoolPrefix($prefix)) {
            throw new IntegrationException('The pool prefix of the Hetzner integration overlaps the names of assigned servers; change it.');
        }
        $productFilter = mb_strtolower((string) $service->config('server_product', ''));
        $location = (string) $service->config('location', '');

        return $this->db->withLock('nebrix:hetzner-pool:' . $integration->id, 15, function () use ($service, $integration, $client, $prefix, $productFilter, $location): array {
            $reserved = $this->services->reservedRemoteValues($integration->id, 'server_number', $service->id);
            foreach ($client->servers() as $server) {
                $number = (int) ($server['server_number'] ?? 0);
                if ($number < 1 || in_array($number, $reserved, true)
                    || !str_starts_with((string) ($server['server_name'] ?? ''), $prefix)
                    || ($server['cancelled'] ?? false) === true
                    || ($server['status'] ?? '') !== 'ready'
                    || ($productFilter !== '' && !str_contains(mb_strtolower((string) ($server['product'] ?? '')), $productFilter))
                    || ($location !== '' && !str_starts_with((string) ($server['dc'] ?? ''), $location))) {
                    continue;
                }

                return $this->merge($service, ['server_number' => $number, 'server_ip' => (string) ($server['server_ip'] ?? ''), 'mode' => 'pool', 'step' => 'name']);
            }

            throw new IntegrationException('No free server matching the product is left in the Hetzner pool. Add servers to the pool and retry provisioning.');
        });
    }

    /**
     * Server number of the service after checking that the server still
     * carries the service's marker name (never act on foreign servers).
     */
    private function ownedServer(Service $service, RobotClient $client): int
    {
        $number = (int) ($service->remote['server_number'] ?? 0);
        if ($number < 1) {
            throw new IntegrationException('No server has been assigned yet.');
        }
        $this->assertMarker($service, $client, $number);

        return $number;
    }

    /** Checks (uncached) that the server still carries the service's marker name. */
    private function assertMarker(Service $service, RobotClient $client, int $number): void
    {
        $name = (string) ($client->server($number)['server_name'] ?? '');
        if ($name !== self::marker($service)) {
            throw new IntegrationException(sprintf('Server #%d is no longer named "%s"; the action was not executed.', $number, self::marker($service)));
        }
    }

    /**
     * The pool prefix must not match the names the panel gives to assigned
     * or returned servers.
     */
    public static function isSafePoolPrefix(string $prefix): bool
    {
        foreach (['nebrix-service-', 'nebrix-wipe-'] as $reserved) {
            if ($prefix === '' || str_starts_with($reserved, $prefix) || str_starts_with($prefix, rtrim($reserved, '-'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed> the updated remote state
     */
    private function merge(Service $service, array $changes): array
    {
        $this->services->mergeRemote($service->id, $changes);

        return $this->services->find($service->id)->remote ?? [];
    }

    /**
     * @return array{0: Integration, 1: RobotClient}
     */
    private function client(Service $service): array
    {
        if ($service->integrationId === null) {
            throw new IntegrationException('No Hetzner integration is assigned to this service.');
        }
        $integration = $this->integrations->get($service->integrationId);

        return [$integration, $this->integrations->hetznerRobot($integration)];
    }
}
