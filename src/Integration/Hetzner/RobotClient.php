<?php

declare(strict_types=1);

namespace Nebrix\Integration\Hetzner;

use Nebrix\Integration\HealthResult;
use Nebrix\Integration\Http\ApiClient;
use Nebrix\Integration\IntegrationException;

/**
 * Hetzner Robot Webservice (https://robot-ws.your-server.de) for dedicated
 * servers: server list, names, Linux installation and rescue system,
 * resets, firewall, reverse DNS, cancellation and server ordering.
 *
 * Authentication: HTTP Basic with a Robot *webservice* user (not the Robot
 * account login). Requests are form encoded; list parameters use the
 * "name[]" notation expected by the Robot API.
 */
class RobotClient
{
    public const RESET_TYPES = ['sw', 'hw', 'power', 'power_long', 'man'];

    public function __construct(private readonly ApiClient $api)
    {
    }

    public function healthCheck(string $poolPrefix): HealthResult
    {
        try {
            $servers = $this->servers();
        } catch (IntegrationException $exception) {
            return HealthResult::down($exception->getMessage());
        }

        $free = count(array_filter($servers, static fn (array $s): bool => $poolPrefix !== '' && str_starts_with((string) ($s['server_name'] ?? ''), $poolPrefix) && !($s['cancelled'] ?? false)));
        $message = sprintf('%d server(s) in the Robot account, %d free in the pool "%s".', count($servers), $free, $poolPrefix);

        return HealthResult::healthy($message, ['servers' => count($servers), 'pool_free' => $free]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function servers(): array
    {
        return $this->unwrapList($this->getList('/server', 'server.list'), 'server');
    }

    /**
     * @return array<string, mixed>
     */
    public function server(int $number): array
    {
        return $this->unwrap($this->get('/server/' . $this->number($number), 'server.get'), 'server');
    }

    public function renameServer(int $number, string $name): void
    {
        if (preg_match('/^[A-Za-z0-9._-]{1,100}$/', $name) !== 1) {
            throw new IntegrationException('Invalid server name.');
        }
        $this->post('/server/' . $this->number($number), ['server_name' => $name], 'server.rename');
    }

    /**
     * @return array<string, mixed> includes "active", "dist" (available images) and, while active, "password"
     */
    public function linuxInstall(int $number): array
    {
        return $this->unwrap($this->get('/boot/' . $this->number($number) . '/linux', 'boot.linux.get'), 'linux');
    }

    /**
     * Activates the Linux installation for the next boot. Hetzner generates
     * the root password and returns it in the response.
     *
     * @param list<string> $authorizedKeys SSH key fingerprints stored in Robot
     * @return array<string, mixed>
     */
    public function activateLinuxInstall(int $number, string $dist, string $lang = 'en', array $authorizedKeys = []): array
    {
        $this->assertImage($dist);

        return $this->unwrap($this->post('/boot/' . $this->number($number) . '/linux', ['dist' => $dist, 'lang' => $lang, 'authorized_key' => $authorizedKeys], 'boot.linux.activate'), 'linux');
    }

    public function deactivateLinuxInstall(int $number): void
    {
        $this->api->request('DELETE', '/boot/' . $this->number($number) . '/linux', ['action' => 'boot.linux.deactivate']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rescue(int $number): array
    {
        return $this->unwrap($this->get('/boot/' . $this->number($number) . '/rescue', 'boot.rescue.get'), 'rescue');
    }

    /**
     * @param list<string> $authorizedKeys
     * @return array<string, mixed> includes the generated "password"
     */
    public function activateRescue(int $number, array $authorizedKeys = []): array
    {
        return $this->unwrap($this->post('/boot/' . $this->number($number) . '/rescue', ['os' => 'linux', 'authorized_key' => $authorizedKeys], 'boot.rescue.activate'), 'rescue');
    }

    public function deactivateRescue(int $number): void
    {
        $this->api->request('DELETE', '/boot/' . $this->number($number) . '/rescue', ['action' => 'boot.rescue.deactivate']);
    }

    public function reset(int $number, string $type): void
    {
        if (!in_array($type, self::RESET_TYPES, true)) {
            throw new IntegrationException('Unsupported reset type.');
        }
        $this->post('/reset/' . $this->number($number), ['type' => $type], 'reset.' . $type);
    }

    /**
     * @return array<string, mixed> status, whitelist_hos, rules{input, output}
     */
    public function firewall(int $number): array
    {
        return $this->unwrap($this->get('/firewall/' . $this->number($number), 'firewall.get'), 'firewall');
    }

    /**
     * @param list<array<string, scalar|null>> $inputRules
     * @param list<array<string, scalar|null>> $outputRules
     */
    public function setFirewall(int $number, string $status, bool $whitelistHetznerServices, array $inputRules = [], array $outputRules = []): void
    {
        if (!in_array($status, ['active', 'disabled'], true)) {
            throw new IntegrationException('Invalid firewall status.');
        }
        $params = ['status' => $status, 'whitelist_hos' => $whitelistHetznerServices ? 'true' : 'false'];
        foreach (['input' => $inputRules, 'output' => $outputRules] as $direction => $rules) {
            foreach (array_values($rules) as $index => $rule) {
                foreach ($rule as $field => $value) {
                    if ($value !== null && $value !== '' && preg_match('/^[a-z_]{1,20}$/', (string) $field) === 1) {
                        $params[sprintf('rules[%s][%d][%s]', $direction, $index, $field)] = (string) $value;
                    }
                }
            }
        }
        $this->post('/firewall/' . $this->number($number), $params, 'firewall.set');
    }

    public function setReverseDns(string $ip, string $ptr): void
    {
        $this->post('/rdns/' . $this->ip($ip), ['ptr' => $ptr], 'rdns.set');
    }

    /**
     * @return array<string, mixed>
     */
    public function cancellation(int $number): array
    {
        return $this->unwrap($this->get('/server/' . $this->number($number) . '/cancellation', 'cancellation.get'), 'cancellation');
    }

    public function cancel(int $number, string $date): void
    {
        if ($date !== 'now' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new IntegrationException('Invalid cancellation date.');
        }
        $this->post('/server/' . $this->number($number) . '/cancellation', ['cancellation_date' => $date], 'cancellation.create');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function orderProducts(): array
    {
        return $this->unwrapList($this->get('/order/server/product', 'order.products'), 'product');
    }

    /**
     * Server order transactions of the last 30 days.
     *
     * @return list<array<string, mixed>>
     */
    public function orderTransactions(): array
    {
        return $this->unwrapList($this->getList('/order/server/transaction', 'order.transactions'), 'transaction');
    }

    /**
     * @return array<string, mixed>
     */
    public function orderTransaction(string $id): array
    {
        if (preg_match('/^[A-Za-z0-9-]{5,64}$/', $id) !== 1) {
            throw new IntegrationException('Invalid transaction ID.');
        }

        return $this->unwrap($this->get('/order/server/transaction/' . $id, 'order.transaction'), 'transaction');
    }

    /**
     * Orders a new dedicated server. With $test = true Hetzner validates the
     * order without processing it (no costs).
     *
     * @param list<string> $authorizedKeys
     * @return array<string, mixed>
     */
    public function orderServer(string $productId, string $location, string $dist, array $authorizedKeys, string $comment, bool $test): array
    {
        if (preg_match('/^[A-Za-z0-9._-]{1,50}$/', $productId) !== 1 || preg_match('/^[A-Z0-9-]{0,20}$/', $location) !== 1) {
            throw new IntegrationException('Invalid product or location.');
        }
        $this->assertImage($dist);
        $params = ['product_id' => $productId, 'dist' => $dist, 'lang' => 'en', 'comment' => mb_substr($comment, 0, 200), 'authorized_key' => $authorizedKeys];
        if ($location !== '') {
            $params['location'] = $location;
        }
        if ($test) {
            $params['test'] = 'true';
        }

        return $this->unwrap($this->post('/order/server/transaction', $params, 'order.create'), 'transaction');
    }

    // ------------------------------------------------------------------

    private function get(string $path, string $action): mixed
    {
        return $this->api->request('GET', $path, ['action' => $action])['json'];
    }

    /**
     * List endpoints of the Robot API answer 404 (NOT_FOUND) when there are
     * no entries.
     */
    private function getList(string $path, string $action): mixed
    {
        try {
            return $this->get($path, $action);
        } catch (IntegrationException $exception) {
            if ($exception->isNotFound()) {
                return [];
            }
            throw $exception;
        }
    }

    /**
     * @param array<string, scalar|list<string>> $params
     */
    private function post(string $path, array $params, string $action): mixed
    {
        return $this->api->request('POST', $path, [
            'body' => self::formEncode($params),
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'action' => $action,
        ])['json'];
    }

    /**
     * Form encoding with "key[]=value" for lists (Robot API convention).
     *
     * @param array<string, scalar|list<string>> $params
     */
    public static function formEncode(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = rawurlencode($key) . '[]=' . rawurlencode((string) $item);
                }
                continue;
            }
            $pairs[] = rawurlencode($key) . '=' . rawurlencode(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }

        return implode('&', $pairs);
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrap(mixed $data, string $key): array
    {
        if (!is_array($data) || !is_array($data[$key] ?? null)) {
            throw new IntegrationException(sprintf('Unexpected response from the Hetzner Robot API (missing "%s").', $key));
        }

        return $data[$key];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unwrapList(mixed $data, string $key): array
    {
        if (!is_array($data)) {
            throw new IntegrationException('Unexpected response from the Hetzner Robot API.');
        }
        $items = [];
        foreach ($data as $entry) {
            if (is_array($entry) && is_array($entry[$key] ?? null)) {
                $items[] = $entry[$key];
            }
        }

        return $items;
    }

    private function number(int $number): string
    {
        if ($number < 1) {
            throw new IntegrationException('Invalid server number.');
        }

        return (string) $number;
    }

    private function ip(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new IntegrationException('Invalid IP address.');
        }

        return $ip;
    }

    private function assertImage(string $dist): void
    {
        if (preg_match('/^[A-Za-z0-9 ._()+-]{2,100}$/', $dist) !== 1) {
            throw new IntegrationException('Invalid operating system image name.');
        }
    }
}
