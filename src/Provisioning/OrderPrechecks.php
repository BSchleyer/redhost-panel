<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Auth\User;
use Nebrix\Billing\OrderException;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationManager;

/**
 * Runs the order precheck of the provisioner responsible for a product.
 */
class OrderPrechecks
{
    public function __construct(
        private readonly ProvisionerRegistry $provisioners,
        private readonly IntegrationManager $integrations,
    ) {
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $options
     *
     * @throws OrderException
     */
    public function check(array $product, User $user, array $options): void
    {
        $category = (string) $product['category'];
        if (!(bool) $product['auto_provision'] || $product['integration_id'] === null || !$this->provisioners->supports($category)) {
            return;
        }
        $provisioner = $this->provisioners->for($category);
        if (!$provisioner instanceof OrderPrecheck) {
            return;
        }

        try {
            $integration = $this->integrations->get((int) $product['integration_id']);
        } catch (IntegrationException) {
            throw new OrderException('This product is currently not available.');
        }
        $config = is_array($product['provisioning_config'] ?? null) ? $product['provisioning_config'] : [];
        $provisioner->checkOrder($integration, $config, $user, $options);
    }
}
