<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Catalog\ProductCategory;
use Nebrix\Core\Container;

/**
 * Maps product categories to provisioners. Categories without an automated
 * integration (dedicated servers, webhosting, domains) are set up by staff.
 */
class ProvisionerRegistry
{
    private const MAP = [
        ProductCategory::VPS => ProxmoxProvisioner::class,
        ProductCategory::LXC => ProxmoxProvisioner::class,
        ProductCategory::MINECRAFT => PterodactylProvisioner::class,
        ProductCategory::NEXTCLOUD => NextcloudProvisioner::class,
        ProductCategory::TEAMSPEAK => TeamSpeakProvisioner::class,
    ];

    public function __construct(private readonly Container $container)
    {
    }

    public function supports(string $category): bool
    {
        return isset(self::MAP[$category]);
    }

    public function for(string $category): Provisioner
    {
        if (!isset(self::MAP[$category])) {
            throw new \InvalidArgumentException(sprintf('Category "%s" is provisioned manually.', $category));
        }

        return $this->container->get(self::MAP[$category]);
    }
}
