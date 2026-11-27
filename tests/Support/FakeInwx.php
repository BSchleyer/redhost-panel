<?php

declare(strict_types=1);

namespace Nebrix\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Stateful fake of the INWX DomRobot JSON-RPC API.
 */
final class FakeInwx
{
    /** @var array<string, array<string, mixed>> domains in the account */
    public array $domains = [];

    /** @var list<string> domains registered elsewhere */
    public array $registeredElsewhere = [];

    /** @var array<int, array<string, mixed>> */
    public array $contacts = [];

    /** @var list<array{method: string, params: array<string, mixed>}> */
    public array $calls = [];

    public bool $twoFactor = false;

    private bool $unlocked = false;

    private ?string $session = null;

    public function register(FakeHttp $http): void
    {
        $http->on('POST', '/jsonrpc/', function (RequestInterface $request): Response {
            $call = FakeHttp::json($request);
            $method = (string) ($call['method'] ?? '');
            $params = is_array($call['params'] ?? null) ? $call['params'] : [];
            $this->calls[] = ['method' => $method, 'params' => $params];

            if ($method === 'account.login') {
                if (($params['user'] ?? '') !== 'reseller' || ($params['pass'] ?? '') !== 'inwx-pass') {
                    return $this->result(2200, 'Authentication error');
                }
                $this->session = 'sess' . bin2hex(random_bytes(4));
                $this->unlocked = !$this->twoFactor;

                return new Response(200, ['Content-Type' => 'application/json', 'Set-Cookie' => 'domrobot=' . $this->session . '; path=/; secure; HttpOnly'], json_encode(['code' => 1000, 'msg' => 'Command completed successfully', 'resData' => ['customerId' => 1, 'tfa' => $this->twoFactor ? 'GOOGLE-AUTH' : '0']], JSON_THROW_ON_ERROR));
            }
            if ($this->session === null || $request->getHeaderLine('Cookie') !== 'domrobot=' . $this->session) {
                return $this->result(2200, 'Authentication error');
            }
            if ($method === 'account.unlock') {
                $this->unlocked = preg_match('/^\d{6}$/', (string) ($params['tan'] ?? '')) === 1;

                return $this->result($this->unlocked ? 1000 : 2200, $this->unlocked ? 'OK' : 'Invalid TAN');
            }
            if (!$this->unlocked) {
                return $this->result(2200, 'Account locked (2FA)');
            }

            $domain = (string) ($params['domain'] ?? '');

            return match ($method) {
                'account.info' => $this->result(1000, 'OK', ['customerNo' => 12345]),
                'account.logout' => $this->result(1500, 'Command completed successfully; ending session'),
                'domain.check' => $this->result(1000, 'OK', ['domain' => [['domain' => $domain, 'avail' => isset($this->domains[$domain]) || in_array($domain, $this->registeredElsewhere, true) ? 0 : 1]]]),
                'domain.info' => isset($this->domains[$domain]) ? $this->result(1000, 'OK', $this->domains[$domain]) : $this->result(2303, 'Object does not exist'),
                'contact.create' => (function () use ($params): Response {
                    $id = 1000 + count($this->contacts);
                    $this->contacts[$id] = $params;

                    return $this->result(1000, 'OK', ['id' => $id]);
                })(),
                'domain.create' => (function () use ($domain, $params): Response {
                    if (isset($this->domains[$domain]) || in_array($domain, $this->registeredElsewhere, true)) {
                        return $this->result(2302, 'Object exists');
                    }
                    $this->domains[$domain] = ['domain' => $domain, 'status' => 'OK', 'exDate' => '2027-11-28 00:00:00', 'ns' => $params['ns'] ?? [], 'renewalMode' => $params['renewalMode'] ?? '', 'authCode' => 'AUTH-' . strtoupper(substr(md5($domain), 0, 8)), 'registrant' => $params['registrant'] ?? null];

                    return $this->result(1000, 'OK', ['roId' => 1]);
                })(),
                'domain.transfer' => (function () use ($domain, $params): Response {
                    if (($params['authCode'] ?? '') !== 'S3cr3t-Auth!') {
                        return $this->result(2202, 'Invalid authorization information');
                    }
                    $this->domains[$domain] = ['domain' => $domain, 'status' => 'TRANSFER PENDING', 'exDate' => null, 'ns' => $params['ns'] ?? [], 'renewalMode' => 'AUTORENEW', 'registrant' => $params['registrant'] ?? null];

                    return $this->result(1001, 'Command completed successfully; action pending');
                })(),
                'domain.update' => (function () use ($domain, $params): Response {
                    if (!isset($this->domains[$domain])) {
                        return $this->result(2303, 'Object does not exist');
                    }
                    foreach (['ns', 'renewalMode'] as $key) {
                        if (isset($params[$key])) {
                            $this->domains[$domain][$key] = $params[$key];
                        }
                    }

                    return $this->result(1000, 'OK');
                })(),
                default => $this->result(2400, 'Command failed'),
            };
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function callsTo(string $method): array
    {
        return array_values(array_map(static fn (array $c): array => $c['params'], array_filter($this->calls, static fn (array $c): bool => $c['method'] === $method)));
    }

    /**
     * @param array<string, mixed> $resData
     */
    private function result(int $code, string $message, array $resData = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['code' => $code, 'msg' => $message, 'resData' => $resData], JSON_THROW_ON_ERROR));
    }
}
