<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Catalog\ProductCategory;
use Nebrix\Core\Container;
use Nebrix\Service\Service;

/**
 * Maps product categories to provisioners. A service is provisioned
 * automatically only when it has an integration assigned; without one (e.g.
 * dedicated servers that are not managed through Hetzner) staff fulfil it
 * manually.
 */
class ProvisionerRegistry
{
    private const MAP = [
        ProductCategory::VPS => ProxmoxProvisioner::class,
        ProductCategory::LXC => ProxmoxProvisioner::class,
        ProductCategory::MINECRAFT => PterodactylProvisioner::class,
        ProductCategory::NEXTCLOUD => NextcloudProvisioner::class,
        ProductCategory::TEAMSPEAK => TeamSpeakProvisioner::class,
        ProductCategory::DEDICATED => HetznerRobotProvisioner::class,
        ProductCategory::WEBHOSTING => PleskProvisioner::class,
        ProductCategory::DOMAIN => DomainProvisioner::class,
    ];

    public function __construct(private readonly Container $container)
    {
    }

    public function supports(string $category): bool
    {
        return isset(self::MAP[$category]);
    }

    /** True when the service is managed through an integration. */
    public function supportsService(Service $service): bool
    {
        return isset(self::MAP[$service->category]) && $service->integrationId !== null;
    }

    public function for(string $category): Provisioner
    {
        if (!isset(self::MAP[$category])) {
            throw new \InvalidArgumentException(sprintf('Category "%s" is provisioned manually.', $category));
        }

        return $this->container->get(self::MAP[$category]);
    }
}
