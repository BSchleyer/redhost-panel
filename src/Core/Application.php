<?php

declare(strict_types=1);

namespace Nebrix\Core;

use Nebrix\Http\HttpKernel;
use Nebrix\Http\Request;
use Nebrix\Http\Response;

/**
 * Application bootstrap: environment, configuration, service container and
 * global error handling. Shared by the web front controller, the CLI and the
 * test suite.
 */
final class Application
{
    private readonly Container $container;

    private function __construct(private readonly string $basePath, Config $config)
    {
        $this->container = new Container();
        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Config::class, $config);

        $register = require $basePath . '/config/container.php';
        $register($this->container, $config, $basePath);
    }

    /**
     * @param array<string, mixed> $overrides configuration overrides (tests)
     * @param bool $registerErrorHandlers false in test suites, which manage handlers themselves
     */
    public static function create(string $basePath, array $overrides = [], bool $registerErrorHandlers = true): self
    {
        Env::load($basePath);
        $config = require $basePath . '/config/app.php';
        $config = array_replace_recursive($config, $overrides);

        $app = new self($basePath, new Config($config));
        if ($registerErrorHandlers) {
            ErrorHandler::register($app->container);
        }

        return $app;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(): Config
    {
        return $this->container->get(Config::class);
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public function handle(Request $request): Response
    {
        return $this->container->get(HttpKernel::class)->handle($request);
    }
}
