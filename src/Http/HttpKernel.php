<?php

declare(strict_types=1);

namespace Nebrix\Http;

use Nebrix\Api\ApiException;
use Nebrix\Core\Config;
use Nebrix\Core\Container;
use Nebrix\Http\Middleware\Middleware;
use Nebrix\Http\Middleware\ParameterizedMiddleware;
use Nebrix\Security\RedirectGuard;
use Nebrix\Security\ValidationException;
use Nebrix\View\View;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Dispatches a request through global middleware, route middleware and the
 * controller. Exceptions are converted into responses at every layer so that
 * outer middleware (session persistence) always runs, and so that no
 * internal error details reach the client.
 *
 * Stateless routes (the REST API, see Router) run through a separate global
 * middleware stack without sessions and CSRF and always answer with JSON
 * errors. Unmatched paths below /api/ are treated as stateless as well.
 */
final class HttpKernel
{
    /**
     * @param list<class-string<Middleware>> $globalMiddleware
     * @param array<string, class-string<Middleware>> $aliases
     * @param list<class-string<Middleware>> $statelessMiddleware
     */
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly array $globalMiddleware,
        private readonly array $aliases,
        private readonly array $statelessMiddleware = [],
    ) {
    }

    public const API_PREFIX = '/api/';

    public function handle(Request $request): Response
    {
        $routeError = null;
        $route = null;
        $params = [];

        try {
            [$route, $params] = $this->router->match($request->method() === 'HEAD' ? 'GET' : $request->method(), $request->path());
            $request->setAttribute('_route', $route);
        } catch (HttpException $exception) {
            $routeError = $exception;
        }
        $stateless = $route !== null ? $route->stateless : str_starts_with($request->path() . '/', self::API_PREFIX);
        $request->setAttribute('_stateless', $stateless);

        $core = function (Request $request) use ($route, $params, $routeError): Response {
            if ($routeError !== null) {
                throw $routeError;
            }
            if ($route === null) {
                throw HttpException::notFound();
            }

            $middleware = array_map($this->resolveMiddleware(...), $route->middleware);

            return $this->pipeline($middleware, fn (Request $r): Response => $this->callController($route, $params, $r))($request);
        };

        $global = array_map(fn (string $class): Middleware => $this->container->get($class), $stateless ? $this->statelessMiddleware : $this->globalMiddleware);
        $response = $this->pipeline($global, $core)($request);

        if ($request->method() === 'HEAD') {
            $response = new Response('', $response->status(), $response->headers());
        }

        return SecurityHeaders::apply($response, $request, $this->config->bool('app.hsts', true));
    }

    /**
     * @param list<Middleware> $middleware
     * @param callable(Request): Response $core
     * @return callable(Request): Response
     */
    private function pipeline(array $middleware, callable $core): callable
    {
        $next = fn (Request $request): Response => $this->safely(static fn (): Response => $core($request), $request);

        foreach (array_reverse($middleware) as $layer) {
            $inner = $next;
            $next = fn (Request $request): Response => $this->safely(
                static fn (): Response => $layer->process($request, $inner),
                $request,
            );
        }

        return $next;
    }

    /**
     * @param callable(): Response $callback
     */
    private function safely(callable $callback, Request $request): Response
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            return $this->renderException($exception, $request);
        }
    }

    private function resolveMiddleware(string $definition): Middleware
    {
        [$alias, $parameterString] = array_pad(explode(':', $definition, 2), 2, null);
        $class = $this->aliases[$alias] ?? null;
        if ($class === null) {
            throw new \RuntimeException(sprintf('Unknown middleware alias "%s".', $alias));
        }

        $middleware = $this->container->get($class);
        if ($parameterString !== null) {
            if (!$middleware instanceof ParameterizedMiddleware) {
                throw new \RuntimeException(sprintf('Middleware "%s" does not accept parameters.', $alias));
            }
            $middleware = $middleware->withParameters(explode(',', $parameterString));
        }

        return $middleware;
    }

    /**
     * @param array<string, string> $params
     */
    private function callController(Route $route, array $params, Request $request): Response
    {
        [$class, $method] = $route->handler;
        $controller = $this->container->get($class);
        if ($controller instanceof Controller) {
            $controller->setContainer($this->container);
        }

        $arguments = [];
        foreach ((new ReflectionMethod($controller, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($typeName === Request::class) {
                $arguments[] = $request;
                continue;
            }

            $name = $parameter->getName();
            if (array_key_exists($name, $params)) {
                $value = $params[$name];
                if ($typeName === 'int') {
                    if (preg_match('/^\d{1,18}$/', $value) !== 1) {
                        throw HttpException::notFound();
                    }
                    $value = (int) $value;
                }
                $arguments[] = $value;
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new \RuntimeException(sprintf('Cannot resolve controller argument $%s for %s::%s().', $name, $class, $method));
        }

        $response = $controller->{$method}(...$arguments);
        if (!$response instanceof Response) {
            throw new \RuntimeException(sprintf('%s::%s() must return a Response.', $class, $method));
        }

        return $response;
    }

    private function renderException(Throwable $exception, Request $request): Response
    {
        if ($request->attribute('_stateless') === true) {
            return $this->renderApiException($exception, $request);
        }
        if ($exception instanceof ValidationException) {
            if ($request->expectsJson()) {
                return Response::json(['message' => $exception->first(), 'errors' => $exception->errors()], 422);
            }
            if ($request->hasSession()) {
                $request->session()->flash('_errors', $exception->errors());
                $request->session()->flash('_old', $exception->input());
                $request->session()->flash('error', $exception->first());
            }

            return Response::redirect($this->previousUrl($request));
        }

        $status = 500;
        $message = 'An unexpected error occurred. The incident has been logged.';
        if ($exception instanceof HttpException) {
            $status = $exception->statusCode();
            $message = $exception->getMessage();
        }

        $reference = null;
        if ($status >= 500) {
            $reference = bin2hex(random_bytes(6));
            $this->logger->error('Unhandled exception', [
                'reference' => $reference,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile() . ':' . $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'path' => $request->path(),
            ]);
        }

        if ($request->expectsJson()) {
            return Response::json(['message' => $message, 'reference' => $reference], $status);
        }

        $debugDetails = $this->config->bool('app.debug') && $this->config->string('app.env') === 'local' && $status >= 500
            ? $exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine() . "\n\n" . $exception->getTraceAsString()
            : null;

        try {
            $template = in_array($status, [403, 404, 419, 429], true) ? 'errors/' . $status . '.twig' : 'errors/error.twig';

            return $this->container->get(View::class)->response($template, [
                'status' => $status,
                'message' => $message,
                'reference' => $reference,
                'debug' => $debugDetails,
            ], $status, $request);
        } catch (Throwable $renderError) {
            $this->logger->critical('Error page could not be rendered', ['message' => $renderError->getMessage()]);

            return Response::text(sprintf("Error %d\n%s%s", $status, $message, $reference !== null ? "\nReference: " . $reference : ''), $status);
        }
    }

    /**
     * JSON error envelope for API clients: {"error": {"code", "message", …}}.
     */
    private function renderApiException(Throwable $exception, Request $request): Response
    {
        if ($exception instanceof ValidationException) {
            return ApiException::response(422, 'validation_failed', $exception->first(), ['fields' => $exception->errors()]);
        }

        if ($exception instanceof HttpException) {
            $status = $exception->statusCode();
            $code = $exception instanceof ApiException ? $exception->errorCode : ApiException::codeFor($status);
            $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'The request could not be processed.';
            if ($status === 404 && !$exception instanceof ApiException) {
                $message = 'The resource was not found.';
            }
            $response = ApiException::response($status, $code, $message);
            if ($exception instanceof ApiException) {
                foreach ($exception->headers as $name => $value) {
                    $response->setHeader($name, $value);
                }
            }

            return $response;
        }

        $reference = bin2hex(random_bytes(6));
        $this->logger->error('Unhandled exception', [
            'reference' => $reference,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile() . ':' . $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'path' => $request->path(),
        ]);

        return ApiException::response(500, 'server_error', 'An unexpected error occurred. The incident has been logged.', ['reference' => $reference]);
    }

    private function previousUrl(Request $request): string
    {
        $referer = $request->header('Referer');
        if ($referer !== null) {
            $host = parse_url($referer, PHP_URL_HOST);
            $requestHost = explode(':', $request->header('Host') ?? '')[0];
            if ($host === $requestHost) {
                $path = (string) parse_url($referer, PHP_URL_PATH);
                $query = parse_url($referer, PHP_URL_QUERY);

                return RedirectGuard::sanitize($path . (is_string($query) ? '?' . $query : ''), $request->path());
            }
        }

        return $request->path();
    }
}
