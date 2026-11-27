<?php

declare(strict_types=1);

namespace Nebrix\Tests\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Fake transport for the official Stripe SDK (installed through
 * ApiRequestor::setHttpClient). Records every API call.
 */
final class FakeStripeHttp implements ClientInterface
{
    /** @var list<array{method: string, path: string, params: array<string, mixed>, headers: list<string>}> */
    public array $calls = [];

    /** @var array<string, \Closure(array<string, mixed>): array{0: int, 1: array<string, mixed>}> */
    private array $routes = [];

    /**
     * @param \Closure(array<string, mixed>): array{0: int, 1: array<string, mixed>} $handler
     */
    public function on(string $method, string $path, \Closure $handler): self
    {
        $this->routes[strtolower($method) . ' ' . $path] = $handler;

        return $this;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = (string) parse_url($absUrl, PHP_URL_PATH);
        if ($method === 'get' && is_string(parse_url($absUrl, PHP_URL_QUERY))) {
            parse_str((string) parse_url($absUrl, PHP_URL_QUERY), $query);
            $params = $query + (array) $params;
        }
        $this->calls[] = ['method' => strtolower($method), 'path' => $path, 'params' => (array) $params, 'headers' => (array) $headers];

        $handler = $this->routes[strtolower($method) . ' ' . $path] ?? null;
        if ($handler === null) {
            return [json_encode(['error' => ['message' => 'No such route: ' . $path, 'type' => 'invalid_request_error']]), 404, []];
        }

        [$status, $body] = $handler((array) $params);

        return [json_encode($body, JSON_THROW_ON_ERROR), $status, ['request-id' => 'req_test']];
    }

    /**
     * @return list<array{method: string, path: string, params: array<string, mixed>, headers: list<string>}>
     */
    public function callsTo(string $method, string $path): array
    {
        return array_values(array_filter($this->calls, static fn (array $call): bool => $call['method'] === strtolower($method) && $call['path'] === $path));
    }

    /** Builds a Stripe-Signature header exactly as Stripe does (HMAC-SHA256 over "timestamp.payload"). */
    public static function signature(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp . '.' . $payload, $secret));
    }
}
