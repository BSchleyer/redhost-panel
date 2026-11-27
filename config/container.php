<?php

declare(strict_types=1);

use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Core\Container;
use Nebrix\Core\LoggerFactory;
use Nebrix\Database\Connection;
use Nebrix\Database\Migrator;
use Nebrix\Http\HttpKernel;
use Nebrix\Http\Middleware;
use Nebrix\Http\Router;
use Nebrix\Security\Encrypter;
use Nebrix\Security\UrlGuard;
use Nebrix\Session\SessionStore;
use Nebrix\Settings\SettingsRepository;
use Nebrix\View\Translator;
use Nebrix\View\View;
use Psr\Log\LoggerInterface;

/*
 * Service registrations. Classes that only depend on other classes are
 * autowired; everything that needs configuration values is registered here.
 */
return static function (Container $c, Config $config, string $basePath): void {
    $c->set(Clock::class, static fn (): Clock => new Clock());

    $c->set(LoggerInterface::class, static fn (): LoggerInterface => LoggerFactory::create($config));

    $c->set(Connection::class, static fn (): Connection => new Connection($config->array('database')));

    $c->set(Encrypter::class, static function () use ($config): Encrypter {
        $key = $config->string('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is not set. Run "bin/nebrix key:generate" and add the key to .env.');
        }

        return Encrypter::fromAppKey($key, $config->array('app.previous_keys'));
    });

    $c->set(SessionStore::class, static fn (Container $c): SessionStore => new SessionStore(
        $c->get(Connection::class),
        $c->get(Clock::class),
        max(5, $config->int('session.idle_minutes', 60)) * 60,
        max(1, $config->int('session.lifetime_hours', 12)) * 3600,
    ));

    $c->set(SettingsRepository::class, static fn (Container $c): SettingsRepository => new SettingsRepository(
        $c->get(Connection::class),
        $c->get(Encrypter::class),
        $c->get(Clock::class),
        require $basePath . '/config/settings.php',
    ));

    $c->set(Translator::class, static fn (): Translator => new Translator($config->string('paths.lang')));

    $c->set(View::class, static fn (Container $c): View => new View(
        $config->string('paths.views'),
        $config->bool('app.debug') ? null : $config->string('paths.cache') . '/twig',
        $config->bool('app.debug'),
        $c->get(SettingsRepository::class),
        $c->get(Nebrix\Authorization\Gate::class),
        $c->get(Translator::class),
        $config->string('paths.public') . '/assets',
    ));

    $c->set(Migrator::class, static fn (Container $c): Migrator => new Migrator(
        $c->get(Connection::class),
        $config->string('paths.migrations'),
        $c->get(Clock::class),
    ));

    $c->set(UrlGuard::class, static fn (): UrlGuard => new UrlGuard(
        $config->bool('integrations.allow_http'),
        $config->bool('integrations.allow_loopback'),
    ));

    $c->set(Router::class, static function () use ($basePath): Router {
        $router = new Router();
        (require $basePath . '/config/routes.php')($router);

        return $router;
    });

    $c->set(HttpKernel::class, static fn (Container $c): HttpKernel => new HttpKernel(
        $c,
        $c->get(Router::class),
        $config,
        $c->get(LoggerInterface::class),
        [
            Middleware\StartSession::class,
            Middleware\LoadAuthenticatedUser::class,
            Middleware\SetLocale::class,
            Middleware\MaintenanceMode::class,
            Middleware\VerifyCsrfToken::class,
        ],
        [
            'auth' => Middleware\Authenticate::class,
            'guest' => Middleware\RedirectIfAuthenticated::class,
            'verified' => Middleware\EnsureEmailVerified::class,
            'staff' => Middleware\RequireStaff::class,
            'permission' => Middleware\RequirePermission::class,
            'api.auth' => Nebrix\Api\Middleware\AuthenticateApiToken::class,
            'api.scope' => Nebrix\Api\Middleware\RequireApiScope::class,
            'api.idempotent' => Nebrix\Api\Middleware\IdempotentRequest::class,
        ],
        // REST API: no session, no CSRF (token-authenticated), JSON errors.
        [
            Nebrix\Api\Middleware\PrepareApiRequest::class,
        ],
    ));

    $extra = $basePath . '/config/services.php';
    if (is_file($extra)) {
        (require $extra)($c, $config, $basePath);
    }
};
