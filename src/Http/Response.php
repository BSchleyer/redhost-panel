<?php

declare(strict_types=1);

namespace Nebrix\Http;

final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<array{name: string, value: string, options: array<string, mixed>}> */
    private array $cookies = [];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param array<mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public static function text(string $body, int $status = 200, string $contentType = 'text/plain; charset=UTF-8'): self
    {
        return new self($body, $status, ['Content-Type' => $contentType]);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function setHeader(string $name, string $value): self
    {
        if (preg_match('/[\r\n]/', $name . $value) === 1) {
            throw new \InvalidArgumentException('Header values must not contain line breaks.');
        }
        $this->headers[strtolower($name)] = $value;

        return $this;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @param array{expires?: int, path?: string, domain?: string, secure?: bool, httponly?: bool, samesite?: string} $options
     */
    public function withCookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options + [
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]];

        return $this;
    }

    /**
     * @return list<array{name: string, value: string, options: array<string, mixed>}>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function cookie(string $name): ?string
    {
        foreach (array_reverse($this->cookies) as $cookie) {
            if ($cookie['name'] === $name) {
                return $cookie['value'];
            }
        }

        return null;
    }

    public function isRedirect(): bool
    {
        return $this->status >= 300 && $this->status < 400 && $this->hasHeader('Location');
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($this->canonicalHeaderName($name) . ': ' . $value, true);
            }
            foreach ($this->cookies as $cookie) {
                /** @var array{expires?: int, path?: string, domain?: string, secure?: bool, httponly?: bool, samesite?: 'Lax'|'lax'|'None'|'none'|'Strict'|'strict'} $options */
                $options = $cookie['options'];
                setcookie($cookie['name'], $cookie['value'], $options);
            }
        }

        echo $this->body;
    }

    private function canonicalHeaderName(string $name): string
    {
        return implode('-', array_map('ucfirst', explode('-', $name)));
    }
}
