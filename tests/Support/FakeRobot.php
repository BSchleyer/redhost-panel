<?php

declare(strict_types=1);

namespace Nebrix\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Stateful fake of the Hetzner Robot webservice (only the endpoints used by
 * Nebrix). Mirrors the response envelopes of the real API ({"server": …}).
 */
final class FakeRobot
{
    /** @var array<int, array{server_name: string, product: string, dc: string, status: string, server_ip: string, cancelled: bool}> */
    public array $servers = [];

    /** @var array<int, array{active: bool, password: ?string, dist: string}> */
    public array $linux = [];

    /** @var array<int, array{active: bool, password: ?string}> */
    public array $rescue = [];

    /** @var array<int, array{status: string, whitelist_hos: bool, rules: array{input: list<array<string, mixed>>}}> */
    public array $firewall = [];

    /** @var list<array{server: int, type: string}> */
    public array $resets = [];

    /** @var array<string, string> */
    public array $rdns = [];

    /** @var array<string, array<string, mixed>> */
    public array $transactions = [];

    /** @var array<int, string> */
    public array $cancelled = [];

    private int $passwordCounter = 0;

    public function addServer(int $number, string $name, string $product = 'AX41-NVMe', string $dc = 'FSN1-DC14'): void
    {
        $this->servers[$number] = ['server_name' => $name, 'product' => $product, 'dc' => $dc, 'status' => 'ready', 'server_ip' => self::ip($number), 'cancelled' => false];
    }

    /** Deterministic, valid IPv4 address for a server number. */
    public static function ip(int $number): string
    {
        return sprintf('198.51.%d.%d', intdiv($number, 256) % 256, $number % 256);
    }

    public function register(FakeHttp $http): void
    {
        $json = static fn (mixed $data, int $status = 200): Response => new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
        $notFound = static fn (string $code): Response => new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => ['status' => 404, 'code' => $code, 'message' => strtolower($code)]], JSON_THROW_ON_ERROR));

        $http
            ->on('GET', '/server', fn (): Response => $json(array_values(array_map(fn (int $n): array => ['server' => $this->serverData($n)], array_keys($this->servers)))))
            ->on('GET', '/server/(?<n>\d+)', fn ($r, array $m): Response => isset($this->servers[(int) $m['n']]) ? $json(['server' => $this->serverData((int) $m['n'])]) : $notFound('SERVER_NOT_FOUND'))
            ->on('POST', '/server/(?<n>\d+)', function (RequestInterface $r, array $m) use ($json): Response {
                $this->servers[(int) $m['n']]['server_name'] = FakeHttp::form($r)['server_name'];

                return $json(['server' => $this->serverData((int) $m['n'])]);
            })
            ->on('GET', '/boot/(?<n>\d+)/linux', fn ($r, array $m): Response => $json(['linux' => ($this->linux[(int) $m['n']] ?? ['active' => false, 'password' => null, 'dist' => '']) + ['dist_list' => ['Debian 12 base', 'Ubuntu 24.04 LTS base']]]))
            ->on('POST', '/boot/(?<n>\d+)/linux', function (RequestInterface $r, array $m) use ($json): Response {
                $n = (int) $m['n'];
                if (($this->linux[$n]['active'] ?? false) === true) {
                    return $json(['error' => ['status' => 409, 'code' => 'BOOT_ALREADY_ENABLED', 'message' => 'already enabled']], 409);
                }
                $this->linux[$n] = ['active' => true, 'password' => 'linuxPw' . (++$this->passwordCounter), 'dist' => FakeHttp::form($r)['dist']];

                return $json(['linux' => $this->linux[$n]]);
            })
            ->on('DELETE', '/boot/(?<n>\d+)/linux', function ($r, array $m) use ($json): Response {
                $this->linux[(int) $m['n']] = ['active' => false, 'password' => null, 'dist' => ''];

                return $json(['linux' => $this->linux[(int) $m['n']]]);
            })
            ->on('GET', '/boot/(?<n>\d+)/rescue', fn ($r, array $m): Response => $json(['rescue' => $this->rescue[(int) $m['n']] ?? ['active' => false, 'password' => null]]))
            ->on('POST', '/boot/(?<n>\d+)/rescue', function ($r, array $m) use ($json): Response {
                $this->rescue[(int) $m['n']] = ['active' => true, 'password' => 'rescuePw' . (++$this->passwordCounter)];

                return $json(['rescue' => $this->rescue[(int) $m['n']]]);
            })
            ->on('DELETE', '/boot/(?<n>\d+)/rescue', function ($r, array $m) use ($json): Response {
                $this->rescue[(int) $m['n']] = ['active' => false, 'password' => null];

                return $json(['rescue' => $this->rescue[(int) $m['n']]]);
            })
            ->on('POST', '/reset/(?<n>\d+)', function (RequestInterface $r, array $m) use ($json): Response {
                $this->resets[] = ['server' => (int) $m['n'], 'type' => FakeHttp::form($r)['type']];

                return $json(['reset' => ['server_number' => (int) $m['n'], 'type' => FakeHttp::form($r)['type']]]);
            })
            ->on('GET', '/firewall/(?<n>\d+)', fn ($r, array $m): Response => $json(['firewall' => $this->firewall[(int) $m['n']] ?? ['status' => 'disabled', 'whitelist_hos' => true, 'rules' => ['input' => []]]]))
            ->on('POST', '/firewall/(?<n>\d+)', function (RequestInterface $r, array $m) use ($json): Response {
                $form = FakeHttp::form($r);
                $rules = [];
                foreach ((array) ($form['rules']['input'] ?? []) as $rule) {
                    $rules[] = $rule;
                }
                $this->firewall[(int) $m['n']] = ['status' => $form['status'], 'whitelist_hos' => $form['whitelist_hos'] === 'true', 'rules' => ['input' => $rules]];

                return $json(['firewall' => $this->firewall[(int) $m['n']]]);
            })
            ->on('POST', '/rdns/(?<ip>[0-9.]+)', function (RequestInterface $r, array $m) use ($json): Response {
                $this->rdns[$m['ip']] = FakeHttp::form($r)['ptr'];

                return $json(['rdns' => ['ip' => $m['ip'], 'ptr' => $this->rdns[$m['ip']]]]);
            })
            ->on('GET', '/server/(?<n>\d+)/cancellation', fn ($r, array $m): Response => $json(['cancellation' => ['server_number' => (int) $m['n'], 'cancelled' => isset($this->cancelled[(int) $m['n']]), 'earliest_cancellation_date' => '2026-12-31']]))
            ->on('POST', '/server/(?<n>\d+)/cancellation', function (RequestInterface $r, array $m) use ($json): Response {
                $this->cancelled[(int) $m['n']] = FakeHttp::form($r)['cancellation_date'];

                return $json(['cancellation' => ['cancelled' => true]]);
            })
            ->on('GET', '/order/server/transaction', fn (): Response => $json(array_values(array_map(static fn (array $t): array => ['transaction' => $t], $this->transactions))))
            ->on('GET', '/order/server/transaction/(?<id>[A-Za-z0-9-]+)', fn ($r, array $m): Response => isset($this->transactions[$m['id']]) ? $json(['transaction' => $this->transactions[$m['id']]]) : $notFound('TRANSACTION_NOT_FOUND'))
            ->on('POST', '/order/server/transaction', function (RequestInterface $r) use ($json): Response {
                $form = FakeHttp::form($r);
                $id = 'B2026112' . (count($this->transactions) + 1);
                $transaction = ['id' => $id, 'status' => 'in process', 'server_number' => null, 'server_ip' => null, 'comment' => $form['comment'] ?? '', 'product' => ['id' => $form['product_id']], 'test' => ($form['test'] ?? '') === 'true', 'authorized_key' => $form['authorized_key'] ?? []];
                if (!$transaction['test']) {
                    $this->transactions[$id] = $transaction;
                }

                return $json(['transaction' => $transaction], 201);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function serverData(int $number): array
    {
        $s = $this->servers[$number];

        return ['server_number' => $number, 'server_ip' => $s['server_ip'], 'server_ipv6_net' => '2a01:4f8:1:' . $number . '::', 'server_name' => $s['server_name'], 'product' => $s['product'], 'dc' => $s['dc'], 'traffic' => 'unlimited', 'status' => $s['status'], 'cancelled' => $s['cancelled'], 'paid_until' => '2026-12-31'];
    }
}
