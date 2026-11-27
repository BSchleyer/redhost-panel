<?php

declare(strict_types=1);

namespace Nebrix\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Route-based fake HTTP server for integration tests. No real network
 * traffic is generated; every request is recorded for assertions.
 */
final class FakeHttp
{
    /** @var list<array{method: string, pattern: string, handler: \Closure(RequestInterface, array<string, string>): Response}> */
    private array $routes = [];

    /** @var list<array{request: RequestInterface, options: array<string, mixed>}> */
    public array $history = [];

    /**
     * @param array<mixed>|\Closure(RequestInterface, array<string, string>): Response $response JSON body or a handler
     */
    public function on(string $method, string $pathPattern, array|\Closure $response, int $status = 200): self
    {
        $handler = $response instanceof \Closure
            ? $response
            : static fn (): Response => new Response($status, ['Content-Type' => 'application/json'], json_encode($response, JSON_THROW_ON_ERROR));
        $this->routes[] = ['method' => strtoupper($method), 'pattern' => $pathPattern, 'handler' => $handler];

        return $this;
    }

    public function client(): ClientInterface
    {
        $stack = HandlerStack::create(function (RequestInterface $request, array $options) {
            $this->history[] = ['request' => $request, 'options' => $options];
            $path = $request->getUri()->getPath();

            foreach (array_reverse($this->routes) as $route) {
                if ($route['method'] === $request->getMethod() && preg_match('#^' . $route['pattern'] . '$#', $path, $matches) === 1) {
                    return Create::promiseFor(($route['handler'])($request, array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)));
                }
            }

            return Create::promiseFor(new Response(404, ['Content-Type' => 'application/json'], '{"message":"no fake route for ' . $request->getMethod() . ' ' . $path . '"}'));
        });

        return new Client(['handler' => $stack]);
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(string $method, string $pathPattern): array
    {
        $found = [];
        foreach ($this->history as $entry) {
            $request = $entry['request'];
            if ($request->getMethod() === strtoupper($method) && preg_match('#^' . $pathPattern . '$#', $request->getUri()->getPath()) === 1) {
                $found[] = $request;
            }
        }

        return $found;
    }

    public function last(): RequestInterface
    {
        $entry = end($this->history);
        if ($entry === false) {
            throw new RuntimeException('No request was sent.');
        }

        return $entry['request'];
    }

    /**
     * @return array<string, string>
     */
    public static function form(RequestInterface $request): array
    {
        parse_str((string) $request->getBody(), $form);

        /** @var array<string, string> */
        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    public static function json(RequestInterface $request): array
    {
        $decoded = json_decode((string) $request->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
