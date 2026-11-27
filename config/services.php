<?php

declare(strict_types=1);

use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Core\Container;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use Nebrix\Database\Connection;
use Nebrix\Integration\IntegrationLogger;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\IntegrationRepository;
use Nebrix\Mail\MailTemplates;
use Nebrix\Provisioning\JobRegistry;
use Nebrix\Queue\JobQueue;
use Nebrix\Queue\Worker;
use Psr\Log\LoggerInterface;
use Nebrix\Security\UrlGuard;
use Nebrix\Settings\BrandingAssets;
use Nebrix\Settings\SettingsRepository;

/*
 * Domain service registrations that need configuration values.
 */
return static function (Container $c, Config $config, string $basePath): void {
    $c->set(MailTemplates::class, static fn (Container $c): MailTemplates => new MailTemplates(
        $c->get(Connection::class),
        $c->get(SettingsRepository::class),
        $c->get(Clock::class),
        $config,
        require $basePath . '/config/mail_templates.php',
        ['de' => require $basePath . '/config/mail_templates_de.php'],
    ));

    $c->set(BrandingAssets::class, static fn (): BrandingAssets => new BrandingAssets($config->string('paths.uploads')));

    // Shared HTTP client for integrations and payment providers (tests replace it with a mock handler).
    $c->set(ClientInterface::class, static fn (): ClientInterface => new GuzzleClient(['http_errors' => false, 'allow_redirects' => false]));

    $c->set(IntegrationManager::class, static fn (Container $c): IntegrationManager => new IntegrationManager(
        $c->get(IntegrationRepository::class),
        $c->get(ClientInterface::class),
        $c->get(UrlGuard::class),
        $c->get(IntegrationLogger::class),
        $config,
    ));

    $c->set(Worker::class, static fn (Container $c): Worker => new Worker(
        $c->get(JobQueue::class),
        $c,
        $c->get(LoggerInterface::class),
        JobRegistry::handlers(),
    ));
};
