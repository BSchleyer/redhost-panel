<?php

declare(strict_types=1);

namespace Nebrix\Http;

/**
 * Small regex router.
 *
 * Paths support named parameters with optional patterns: /services/{id:\d+}.
 * Route options:
 *  - middleware: list of middleware aliases (e.g. "auth", "permission:customers.view")
 *  - csrf: false to skip CSRF verification (only for signature-verified webhooks)
 *  - name: route name for URL generation
 *  - stateless: true for token-authenticated API routes (no session, no CSRF,
 *    JSON errors; uses the kernel's stateless middleware stack). Also a group
 *    attribute.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var list<array{prefix: string, middleware: list<string>, stateless: bool}> */
    private array $groupStack = [];

    /** @var array<string, Route> */
    private array $named = [];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{middleware?: list<string>, csrf?: bool, name?: string, stateless?: bool} $options
     */
    public function get(string $path, array $handler, array $options = []): Route
    {
        return $this->add(['GET', 'HEAD'], $path, $handler, $options);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{middleware?: list<string>, csrf?: bool, name?: string, stateless?: bool} $options
     */
    public function post(string $path, array $handler, array $options = []): Route
    {
        return $this->add(['POST'], $path, $handler, $options);
    }

    /**
     * @param list<string> $methods
     * @param array{0: class-string, 1: string} $handler
     * @param array{middleware?: list<string>, csrf?: bool, name?: string, stateless?: bool} $options
     */
    public function add(array $methods, string $path, array $handler, array $options = []): Route
    {
        $prefix = '';
        $middleware = [];
        $stateless = $options['stateless'] ?? false;
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            $middleware = array_merge($middleware, $group['middleware']);
            $stateless = $stateless || $group['stateless'];
        }

        $fullPath = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        $route = new Route(
            $methods,
            $fullPath,
            $handler,
            array_values(array_unique(array_merge($middleware, $options['middleware'] ?? []))),
            $options['csrf'] ?? true,
            $stateless,
        );

        $this->routes[] = $route;
        if (isset($options['name'])) {
            $this->named[$options['name']] = $route;
        }

        return $route;
    }

    /**
     * @param array{prefix?: string, middleware?: list<string>, stateless?: bool} $attributes
     * @param callable(self): void $callback
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix' => isset($attributes['prefix']) ? '/' . trim($attributes['prefix'], '/') : '',
            'middleware' => $attributes['middleware'] ?? [],
            'stateless' => $attributes['stateless'] ?? false,
        ];

        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    /**
     * @return array{0: Route, 1: array<string, string>}
     *
     * @throws HttpException 404 or 405
     */
    public function match(string $method, string $path): array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            $params = $route->match($path);
            if ($params === null) {
                continue;
            }
            if (in_array($method, $route->methods, true)) {
                return [$route, $params];
            }
            $allowed = array_merge($allowed, $route->methods);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Method not allowed.');
        }

        throw HttpException::notFound();
    }

    /**
     * @param array<string, string|int> $params
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown route "%s".', $name));
        }

        return $this->named[$name]->build($params);
    }

    /**
     * @return list<Route>
     */
    public function routes(): array
    {
        return $this->routes;
    }
}
