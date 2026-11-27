<?php

declare(strict_types=1);

namespace Nebrix\Payment;

use Nebrix\Auth\User;
use Nebrix\Core\Container;
use Nebrix\Payment\PayPal\PayPalGateway;
use Nebrix\Payment\Stripe\StripeGateway;

class GatewayRegistry
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const GATEWAYS = [
        'stripe' => StripeGateway::class,
        'paypal' => PayPalGateway::class,
        'credit' => CreditGateway::class,
    ];

    public function __construct(private readonly Container $container)
    {
    }

    public function get(string $key): ?PaymentGateway
    {
        $class = self::GATEWAYS[$key] ?? null;

        return $class === null ? null : $this->container->get($class);
    }

    /**
     * @param array<string, mixed> $invoice
     * @return array<string, PaymentGateway>
     */
    public function availableFor(array $invoice, User $user): array
    {
        $available = [];
        foreach (array_keys(self::GATEWAYS) as $key) {
            $gateway = $this->get($key);
            if ($gateway !== null && $gateway->isEnabled() && $gateway->isAvailableFor($invoice, $user)) {
                $available[$key] = $gateway;
            }
        }

        return $available;
    }
}
