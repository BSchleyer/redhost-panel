<?php

declare(strict_types=1);

use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Core\Container;
use Nebrix\Database\Connection;
use Nebrix\Mail\MailTemplates;
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
    ));

    $c->set(BrandingAssets::class, static fn (): BrandingAssets => new BrandingAssets($config->string('paths.uploads')));
};
