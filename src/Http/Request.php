<?php

declare(strict_types=1);

namespace Nebrix\Http;

use Nebrix\Session\Session;

/**
 * HTTP request value object.
 *
 * The client IP and HTTPS detection only honour X-Forwarded-* headers when
 * the direct peer (REMOTE_ADDR) is a configured trusted proxy, so clients
 * cannot spoof their address (legacy finding H-10).
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    private ?Session $session = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     * @param list<string> $trustedProxies
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $cookies = [],
        private readonly array $files = [],
        private readonly array $server = [],
        private readonly string $body = '',
        private readonly array $trustedProxies = [],
    ) {
    }

    /**
     * @param list<string> $trustedProxies
     */
    public static function fromGlobals(array $trustedProxies = []): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $body = file_get_contents('php://input');

        /** @var array<string, string> $cookies */
        $cookies = array_filter($_COOKIE, static fn ($value): bool => is_string($value));

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            is_string($path) && $path !== '' ? $path : '/',
            $_GET,
            $_POST,
            $cookies,
            $_FILES,
            $_SERVER,
            $body === false ? '' : $body,
            $trustedProxies,
        );
    }

    /**
     * Factory for tests and internal sub-requests.
     *
     * @param array<string, mixed> $data query (GET) or form data (other methods)
     * @param array<string, string> $cookies
     * @param array<string, mixed> $server
     */
    public static function create(string $method, string $uri, array $data = [], array $cookies = [], array $server = [], string $body = ''): self
    {
        $method = strtoupper($method);
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $query = [];
        parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?? ''), $query);
        if ($method === 'GET') {
            $query = array_merge($query, $data);
        }

        return new self(
            $method,
            $path,
            $query,
            $method === 'GET' ? [] : $data,
            $cookies,
            [],
            $server + ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost', 'HTTP_USER_AGENT' => 'NebrixTest'],
            $body,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        $normalized = '/' . trim($this->path, '/');

        return $normalized;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** Returns a trimmed string input or the default when absent/not a string. */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->post + $this->query;
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();

        return array_intersect_key($all, array_flip($keys));
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Content-Type' || $name === 'content-type') {
            $key = 'CONTENT_TYPE';
        }
        $value = $this->server[$key] ?? null;
        // Apache with CGI/FPM passes the Authorization header only via a rewrite rule (REDIRECT_ prefix).
        if ($value === null && $key === 'HTTP_AUTHORIZATION') {
            $value = $this->server['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        }

        return is_string($value) ? $value : null;
    }

    public function server(string $key): ?string
    {
        $value = $this->server[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function userAgent(): string
    {
        return mb_substr($this->header('User-Agent') ?? '', 0, 255);
    }

    private function isFromTrustedProxy(): bool
    {
        $remote = $this->server('REMOTE_ADDR') ?? '';
        if ($remote === '' || $this->trustedProxies === []) {
            return false;
        }

        foreach ($this->trustedProxies as $proxy) {
            if (IpRange::contains($proxy, $remote)) {
                return true;
            }
        }

        return false;
    }

    public function clientIp(): string
    {
        $remote = $this->server('REMOTE_ADDR') ?? '0.0.0.0';
        if (!$this->isFromTrustedProxy()) {
            return $remote;
        }

        $forwarded = $this->header('X-Forwarded-For');
        if ($forwarded === null) {
            return $remote;
        }

        // Walk from right to left and return the first address that is not a trusted proxy.
        $hops = array_reverse(array_map('trim', explode(',', $forwarded)));
        foreach ($hops as $hop) {
            if (filter_var($hop, FILTER_VALIDATE_IP) === false) {
                break;
            }
            $trusted = false;
            foreach ($this->trustedProxies as $proxy) {
                if (IpRange::contains($proxy, $hop)) {
                    $trusted = true;
                    break;
                }
            }
            if (!$trusted) {
                return $hop;
            }
        }

        return $remote;
    }

    public function isSecure(): bool
    {
        $https = strtolower($this->server('HTTPS') ?? '');
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        if ($this->isFromTrustedProxy()) {
            return strtolower($this->header('X-Forwarded-Proto') ?? '') === 'https';
        }

        return false;
    }

    public function expectsJson(): bool
    {
        return str_contains($this->header('Accept') ?? '', 'application/json');
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    public function session(): Session
    {
        if ($this->session === null) {
            throw new \LogicException('Session has not been started for this request.');
        }

        return $this->session;
    }

    public function hasSession(): bool
    {
        return $this->session !== null;
    }
}
