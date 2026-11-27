<?php

declare(strict_types=1);

namespace Nebrix\Controller;

use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Payment\PayPal\PayPalGateway;
use Nebrix\Payment\Stripe\StripeGateway;

/**
 * Payment provider webhooks. These routes are exempt from CSRF checks
 * because every request is authenticated by the provider's signature.
 */
final class WebhookController extends Controller
{
    private const MAX_BODY = 512 * 1024;

    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly PayPalGateway $paypal,
    ) {
    }

    public function stripe(Request $request): Response
    {
        if (strlen($request->body()) > self::MAX_BODY) {
            return Response::json(['error' => 'payload too large'], 413);
        }

        return $this->stripe->handleWebhook($request);
    }

    public function paypal(Request $request): Response
    {
        if (strlen($request->body()) > self::MAX_BODY) {
            return Response::json(['error' => 'payload too large'], 413);
        }

        return $this->paypal->handleWebhook($request);
    }
}
