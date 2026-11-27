<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Auth\User;
use Nebrix\Billing\OrderException;
use Nebrix\Integration\Integration;

/**
 * Provisioners that validate an order before it is placed (e.g. domain
 * availability), so customers do not pay for something that cannot be set up.
 */
interface OrderPrecheck
{
    /**
     * @param array<string, mixed> $config product provisioning configuration
     * @param array<string, mixed> $options validated customer options
     *
     * @throws OrderException with a customer-facing message
     */
    public function checkOrder(Integration $integration, array $config, User $user, array $options): void;
}
