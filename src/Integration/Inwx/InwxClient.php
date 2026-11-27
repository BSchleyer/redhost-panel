<?php

declare(strict_types=1);

namespace Nebrix\Integration\Inwx;

use Nebrix\Domain\Registrar;
use Nebrix\Integration\HealthResult;
use Nebrix\Integration\Http\ApiClient;
use Nebrix\Integration\IntegrationException;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

/**
 * INWX DomRobot JSON-RPC API (https://api.domrobot.com/jsonrpc/, test system
 * https://api.ote.domrobot.com/jsonrpc/).
 *
 * The session cookie from account.login is kept for the lifetime of this
 * object; accounts with two-factor authentication are unlocked with a TOTP
 * code generated from the configured secret.
 */
class InwxClient implements Registrar
{
    public const ENDPOINTS = ['ote' => 'https://api.ote.domrobot.com', 'live' => 'https://api.domrobot.com'];

    /** Command completed successfully / successfully but pending / session ended. */
    private const SUCCESS = [1000, 1001, 1500];
    private const OBJECT_NOT_FOUND = 2303;

    private const RENEWAL_MODES = [Registrar::RENEW => 'AUTORENEW', Registrar::EXPIRE => 'AUTOEXPIRE', Registrar::DELETE => 'AUTODELETE'];

    private ?string $cookie = null;

    public function __construct(
        private readonly ApiClient $api,
        private readonly string $username,
        #[\SensitiveParameter] private readonly string $password,
        #[\SensitiveParameter] private readonly ?string $totpSecret = null,
    ) {
    }

    public function healthCheck(): HealthResult
    {
        try {
            $info = $this->call('account.info');
        } catch (IntegrationException $exception) {
            return HealthResult::down($exception->getMessage());
        }

        return HealthResult::healthy(sprintf('Signed in to INWX (customer %s).', (string) ($info['customerNo'] ?? $info['customerId'] ?? '?')));
    }

    public function isAvailable(string $domain): bool
    {
        $result = $this->call('domain.check', ['domain' => $this->domain($domain)]);
        $entry = $result['domain'][0] ?? null;
        if (!is_array($entry)) {
            throw new IntegrationException('INWX returned no availability information.');
        }

        return (int) ($entry['avail'] ?? 0) === 1;
    }

    public function info(string $domain): ?array
    {
        try {
            $result = $this->call('domain.info', ['domain' => $this->domain($domain), 'wide' => 1]);
        } catch (InwxException $exception) {
            if ($exception->resultCode === self::OBJECT_NOT_FOUND) {
                return null;
            }
            throw $exception;
        }

        return [
            'status' => (string) ($result['status'] ?? 'unknown'),
            'expires' => isset($result['exDate']) ? (string) $result['exDate'] : null,
            'nameservers' => array_values(array_map('strval', is_array($result['ns'] ?? null) ? $result['ns'] : [])),
            'auth_code' => isset($result['authCode']) && $result['authCode'] !== '' ? (string) $result['authCode'] : null,
            'registrant' => isset($result['registrant']) && $result['registrant'] !== '' ? (string) $result['registrant'] : null,
        ];
    }

    public function createContact(array $contact): string
    {
        $params = [
            'type' => $contact['type'],
            'name' => $contact['name'],
            'street' => $contact['street'],
            'city' => $contact['city'],
            'pc' => $contact['postcode'],
            'cc' => $contact['country'],
            'voice' => $contact['phone'],
            'email' => $contact['email'],
        ];
        if ($contact['org'] !== '') {
            $params['org'] = $contact['org'];
        }
        $result = $this->call('contact.create', $params);
        if (!isset($result['id'])) {
            throw new IntegrationException('INWX did not return a contact ID.');
        }

        return (string) $result['id'];
    }

    public function register(string $domain, string $contactId, array $nameservers, int $years): void
    {
        $this->call('domain.create', [
            'domain' => $this->domain($domain),
            'period' => max(1, min(10, $years)) . 'Y',
            'registrant' => (int) $contactId,
            'admin' => (int) $contactId,
            'tech' => (int) $contactId,
            'billing' => (int) $contactId,
            'ns' => $this->nameservers($nameservers),
            'renewalMode' => 'AUTORENEW',
        ]);
    }

    public function transfer(string $domain, #[\SensitiveParameter] string $authCode, string $contactId, array $nameservers): void
    {
        $this->call('domain.transfer', [
            'domain' => $this->domain($domain),
            'authCode' => $authCode,
            'registrant' => (int) $contactId,
            'admin' => (int) $contactId,
            'tech' => (int) $contactId,
            'billing' => (int) $contactId,
            'ns' => $this->nameservers($nameservers),
        ]);
    }

    public function setNameservers(string $domain, array $nameservers): void
    {
        $this->call('domain.update', ['domain' => $this->domain($domain), 'ns' => $this->nameservers($nameservers)]);
    }

    public function setRenewalMode(string $domain, string $mode): void
    {
        $value = self::RENEWAL_MODES[$mode] ?? throw new IntegrationException('Unsupported renewal mode.');
        $this->call('domain.update', ['domain' => $this->domain($domain), 'renewalMode' => $value]);
    }

    public function logout(): void
    {
        if ($this->cookie !== null) {
            try {
                $this->call('account.logout');
            } finally {
                $this->cookie = null;
            }
        }
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed> resData
     *
     * @throws InwxException|IntegrationException
     */
    private function call(string $method, array $params = []): array
    {
        if ($this->cookie === null && $method !== 'account.login') {
            $this->login();
        }

        $response = $this->api->request('POST', '/jsonrpc/', [
            'json' => ['method' => $method, 'params' => $params],
            'headers' => $this->cookie !== null ? ['Cookie' => $this->cookie] : [],
            'action' => $method,
        ]);
        $json = $response['json'];
        if (!is_array($json) || !isset($json['code'])) {
            throw new IntegrationException('Unexpected response from the INWX API.');
        }
        $code = (int) $json['code'];
        if (!in_array($code, self::SUCCESS, true)) {
            $reason = (string) ($json['reason'] ?? '');
            throw new InwxException(sprintf('INWX error %d: %s%s', $code, mb_substr((string) ($json['msg'] ?? ''), 0, 200), $reason !== '' ? ' (' . mb_substr($reason, 0, 200) . ')' : ''), $code);
        }

        if ($method === 'account.login') {
            foreach ($response['headers']['set-cookie'] ?? [] as $header) {
                if (preg_match('/(?:^|;\s*)(domrobot=[^;]+)/', $header, $match) === 1) {
                    $this->cookie = $match[1];
                }
            }
        }

        return is_array($json['resData'] ?? null) ? $json['resData'] : [];
    }

    private function login(): void
    {
        $result = $this->call('account.login', ['user' => $this->username, 'pass' => $this->password, 'lang' => 'en']);
        if ($this->cookie === null) {
            throw new IntegrationException('INWX did not return a session.');
        }
        $tfa = (string) ($result['tfa'] ?? '0');
        if ($tfa !== '0' && $tfa !== '') {
            if ($this->totpSecret === null || $this->totpSecret === '') {
                throw new IntegrationException('The INWX account uses two-factor authentication; configure its secret on the integration.');
            }
            $code = (new TwoFactorAuth(new BaconQrCodeProvider(), 'Nebrix'))->getCode($this->totpSecret);
            $this->call('account.unlock', ['tan' => $code]);
        }
    }

    private function domain(string $domain): string
    {
        $domain = mb_strtolower(trim($domain));
        if (preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) !== 1) {
            throw new IntegrationException('Invalid domain name.');
        }

        return $domain;
    }

    /**
     * @param list<string> $nameservers
     * @return list<string>
     */
    private function nameservers(array $nameservers): array
    {
        $valid = [];
        foreach ($nameservers as $nameserver) {
            $nameserver = mb_strtolower(trim($nameserver));
            if (preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $nameserver) === 1) {
                $valid[] = $nameserver;
            }
        }
        if (count($valid) < 2) {
            throw new IntegrationException('At least two valid name servers are required.');
        }

        return array_slice(array_values(array_unique($valid)), 0, 6);
    }
}
