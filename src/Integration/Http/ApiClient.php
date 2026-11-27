<?php

declare(strict_types=1);

namespace Nebrix\Integration\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationLogger;
use Nebrix\Security\UnsafeUrlException;
use Nebrix\Security\UrlGuard;

/**
 * JSON/REST client used by all HTTP integrations.
 *
 * - The base URL is validated by the UrlGuard on every request; the resolved
 *   IP is pinned with CURLOPT_RESOLVE so DNS cannot change between the check
 *   and the connection (DNS rebinding).
 * - Redirects are never followed; timeouts are always set.
 * - Each call is logged (method, path, status, duration) without bodies.
 */
class ApiClient
{
    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly UrlGuard $guard,
        private readonly IntegrationLogger $logger,
        private readonly int $integrationId,
        private readonly string $baseUrl,
        private readonly array $defaultHeaders,
        private readonly bool $verifyTls = true,
        private readonly int $timeout = 20,
    ) {
    }

    /**
     * @param array<string, mixed> $options query, json, form_params, headers, action
     * @return array{status: int, json: mixed, body: string, headers: array<string, list<string>>}
     *
     * @throws IntegrationException
     */
    public function request(string $method, string $path, array $options = []): array
    {
        try {
            $target = $this->guard->validateUrl($this->baseUrl);
        } catch (UnsafeUrlException $exception) {
            throw new IntegrationException('Integration URL rejected: ' . $exception->getMessage());
        }

        $url = $target['url'] . '/' . ltrim($path, '/');
        $requestOptions = [
            'headers' => ($options['headers'] ?? []) + $this->defaultHeaders + ['Accept' => 'application/json', 'User-Agent' => 'NebrixPanel/2.0'],
            'timeout' => $this->timeout,
            'connect_timeout' => min(10, $this->timeout),
            'allow_redirects' => false,
            'http_errors' => false,
            'verify' => $this->verifyTls,
        ];
        if (filter_var($target['host'], FILTER_VALIDATE_IP) === false) {
            $requestOptions['curl'] = [CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $target['host'], $target['port'], str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'])]];
        }
        foreach (['query', 'json', 'form_params', 'body'] as $key) {
            if (array_key_exists($key, $options)) {
                $requestOptions[$key] = $options[$key];
            }
        }

        $action = (string) ($options['action'] ?? $method . ' ' . strtok($path, '?'));
        $started = hrtime(true);

        try {
            $response = $this->http->request($method, $url, $requestOptions);
        } catch (GuzzleException $exception) {
            $duration = (int) ((hrtime(true) - $started) / 1_000_000);
            $message = 'Connection failed: ' . $this->sanitize($exception->getMessage());
            $this->logger->log($this->integrationId, 'error', $action, $message, ['method' => $method, 'path' => strtok($path, '?')], $duration);

            throw new IntegrationException($message, null, $exception);
        }

        $duration = (int) ((hrtime(true) - $started) / 1_000_000);
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $json = $body !== '' ? json_decode($body, true) : null;

        $level = $status >= 400 ? 'warning' : 'debug';
        $this->logger->log($this->integrationId, $level, $action, sprintf('HTTP %d', $status), ['method' => $method, 'path' => strtok($path, '?'), 'status' => $status], $duration);

        if ($status >= 300) {
            throw new IntegrationException(
                sprintf('The remote API returned HTTP %d%s', $status, ($detail = $this->errorDetail($json, $body)) !== '' ? ': ' . $detail : '.'),
                $status,
            );
        }

        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = array_values(array_map('strval', $values));
        }

        return ['status' => $status, 'json' => $json, 'body' => $body, 'headers' => $headers];
    }

    /**
     * Connection data for a WebSocket on the integration's host (used by the
     * console proxy). The address is validated and pinned like any request.
     *
     * @return array{scheme: string, host: string, ip: string, port: int, path: string, headers: array<string, string>, verify_tls: bool}
     *
     * @throws IntegrationException
     */
    public function webSocketTarget(string $path): array
    {
        try {
            $target = $this->guard->validateUrl($this->baseUrl);
        } catch (UnsafeUrlException $exception) {
            throw new IntegrationException('Integration URL rejected: ' . $exception->getMessage());
        }

        return [
            'scheme' => $target['scheme'] === 'https' ? 'wss' : 'ws',
            'host' => $target['host'],
            'ip' => $target['ip'],
            'port' => $target['port'],
            'path' => $target['path'] . '/' . ltrim($path, '/'),
            'headers' => $this->defaultHeaders,
            'verify_tls' => $this->verifyTls,
        ];
    }

    /**
     * Extracts a short, human-readable error from common API error shapes.
     */
    private function errorDetail(mixed $json, string $body): string
    {
        $candidates = [];
        if (is_array($json)) {
            $candidates[] = $json['errors'][0]['detail'] ?? null;          // Pterodactyl
            $candidates[] = $json['ocs']['meta']['message'] ?? null;       // Nextcloud
            $candidates[] = isset($json['error']['code'], $json['error']['message']) && is_string($json['error']['message'])
                ? $json['error']['code'] . ': ' . $json['error']['message'] : null;  // Hetzner Robot
            $candidates[] = $json['message'] ?? null;                      // generic
            $candidates[] = is_array($json['errors'] ?? null) && !isset($json['errors'][0]) ? json_encode($json['errors']) : null; // Proxmox parameter errors
        } elseif ($body !== '' && strlen($body) < 300 && !str_contains($body, '<')) {
            $candidates[] = $body;
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $this->sanitize($candidate);
            }
        }

        return '';
    }

    private function sanitize(string $message): string
    {
        // Strip anything that looks like a credential or token from error messages.
        $message = (string) preg_replace('/(PVEAPIToken=)[^\s"]+|(?:Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+|ptl[ac]_[A-Za-z0-9]+/i', '$1[redacted]', $message);

        return mb_substr(trim($message), 0, 300);
    }
}
