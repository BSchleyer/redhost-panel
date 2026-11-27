<?php

declare(strict_types=1);

namespace Nebrix\Payment\Stripe;

use Nebrix\Auth\User;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Integration\HealthResult;
use Nebrix\Payment\PaymentGateway;
use Nebrix\Payment\PaymentOutcome;
use Nebrix\Payment\PaymentService;
use Nebrix\Payment\WebhookEventRepository;
use Nebrix\Settings\SettingsRepository;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;

/**
 * Stripe Checkout (official stripe-php SDK).
 *
 * Flow: Checkout Session with the invoice total and invoice ID in metadata
 * → customer pays at Stripe → on return the session is retrieved
 * server-side (never trusted from the URL) → PaymentService. Webhooks are
 * verified with Webhook::constructEvent (HMAC-SHA256 + timestamp tolerance)
 * and settle asynchronous payments and refunds made in the Stripe dashboard.
 */
class StripeGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly PaymentService $payments,
        private readonly WebhookEventRepository $webhooks,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Credit card & more (Stripe)';
    }

    public function isEnabled(): bool
    {
        return $this->settings->bool('payments.stripe.enabled') && $this->settings->get('payments.stripe.secret_key') !== null;
    }

    public function isAvailableFor(array $invoice, User $user): bool
    {
        return (int) $invoice['total'] >= 50; // Stripe minimum charge (0.50 in most currencies)
    }

    public function client(): StripeClient
    {
        return $this->client ??= new StripeClient([
            'api_key' => (string) $this->settings->get('payments.stripe.secret_key'),
            'max_network_retries' => 2,
        ]);
    }

    public function start(array $invoice, User $user, string $returnUrl, string $cancelUrl): string|PaymentOutcome
    {
        $currency = strtolower((string) $invoice['currency']);
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => (int) $invoice['total'],
                    'product_data' => ['name' => 'Invoice ' . $invoice['number']],
                ],
            ]],
            'client_reference_id' => (string) $invoice['id'],
            'customer_email' => $user->email,
            'metadata' => ['invoice_id' => (string) $invoice['id'], 'user_id' => (string) $user->id],
            'payment_intent_data' => ['metadata' => ['invoice_id' => (string) $invoice['id'], 'user_id' => (string) $user->id]],
            // Stripe replaces {CHECKOUT_SESSION_ID}; the server retrieves the session itself.
            'success_url' => $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ], ['idempotency_key' => 'invoice-' . $invoice['id'] . '-' . bin2hex(random_bytes(8))]);

        if (!is_string($session->url) || !str_starts_with($session->url, 'https://')) {
            throw new \RuntimeException('Stripe did not return a checkout URL.');
        }

        $this->payments->event('stripe', 'checkout.created', (string) $session->id, (int) $invoice['id'], (int) $invoice['user_id'], (int) $invoice['total'], strtoupper($currency));

        return $session->url;
    }

    public function complete(array $invoice, Request $request): PaymentOutcome
    {
        $sessionId = $request->string('session_id');
        if (preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId) !== 1) {
            return PaymentOutcome::rejected('Invalid Stripe response.');
        }

        try {
            $session = $this->client()->checkout->sessions->retrieve($sessionId);
        } catch (ApiErrorException $exception) {
            $this->logger->warning('Stripe session lookup failed', ['message' => $exception->getMessage()]);

            return PaymentOutcome::rejected('The Stripe payment could not be verified.');
        }

        /** @var array<string, mixed> $data */
        $data = $session->toArray();
        if ((string) ($data['metadata']['invoice_id'] ?? '') !== (string) $invoice['id']) {
            $this->payments->event('stripe', 'checkout.invoice_mismatch', $sessionId, (int) $invoice['id'], (int) $invoice['user_id'], null, null);

            return PaymentOutcome::rejected('This Stripe payment does not belong to the invoice.');
        }

        return $this->settleSession($data);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function settleSession(array $session): PaymentOutcome
    {
        $invoiceId = (int) ($session['metadata']['invoice_id'] ?? 0);
        $paymentIntent = is_array($session['payment_intent'] ?? null) ? ($session['payment_intent']['id'] ?? '') : (string) ($session['payment_intent'] ?? '');

        if ($invoiceId === 0 || $paymentIntent === '') {
            return PaymentOutcome::rejected('Incomplete Stripe checkout session.');
        }
        if (($session['payment_status'] ?? '') !== 'paid') {
            return PaymentOutcome::pending('Stripe is still processing the payment. Your invoice will be updated automatically.');
        }

        return $this->payments->recordPayment(
            'stripe',
            $paymentIntent,
            $invoiceId,
            (int) ($session['amount_total'] ?? 0),
            strtoupper((string) ($session['currency'] ?? '')),
            ['session' => (string) ($session['id'] ?? ''), 'livemode' => (bool) ($session['livemode'] ?? false)],
        );
    }

    public function handleWebhook(Request $request): Response
    {
        $secret = (string) $this->settings->get('payments.stripe.webhook_secret');
        if ($secret === '') {
            return Response::json(['error' => 'webhook secret not configured'], 503);
        }

        $payload = $request->body();
        try {
            $event = Webhook::constructEvent($payload, (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            $this->payments->event('stripe', 'webhook.invalid_signature', null, null, null, null, null);

            return Response::json(['error' => 'invalid signature'], 400);
        }

        /** @var array<string, mixed> $data */
        $data = $event->toArray();
        $eventId = (string) $data['id'];
        if (!$this->webhooks->begin('stripe', $eventId, (string) $data['type'], $payload)) {
            return Response::json(['status' => 'duplicate']);
        }

        try {
            $status = $this->processEvent((string) $data['type'], is_array($data['data']['object'] ?? null) ? $data['data']['object'] : []);
            $this->webhooks->finish('stripe', $eventId, $status);
        } catch (Throwable $exception) {
            $this->webhooks->finish('stripe', $eventId, 'failed', $exception->getMessage());
            $this->logger->error('Stripe webhook processing failed', ['event' => $eventId, 'message' => $exception->getMessage()]);

            return Response::json(['error' => 'processing failed'], 500);
        }

        return Response::json(['status' => $status]);
    }

    /**
     * @param array<string, mixed> $object
     */
    private function processEvent(string $type, array $object): string
    {
        switch ($type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $outcome = $this->settleSession($object);

                return $outcome->status === PaymentOutcome::REJECTED ? 'ignored' : 'processed';

            case 'checkout.session.async_payment_failed':
                $invoiceId = (int) ($object['metadata']['invoice_id'] ?? 0);
                $this->payments->recordFailure('stripe', (string) ($object['payment_intent'] ?? $object['id'] ?? ''), $invoiceId > 0 ? $invoiceId : null, 'Asynchronous payment failed');

                return 'processed';

            case 'refund.created':
            case 'refund.updated':
                if (($object['status'] ?? '') !== 'succeeded' || !is_string($object['payment_intent'] ?? null)) {
                    return 'ignored';
                }
                $this->payments->recordRefund('stripe', (string) $object['id'], $object['payment_intent'], (int) $object['amount'], strtoupper((string) $object['currency']));

                return 'processed';

            case 'charge.dispute.created':
                if (!is_string($object['payment_intent'] ?? null)) {
                    return 'ignored';
                }
                $outcome = $this->payments->recordDispute('stripe', (string) ($object['id'] ?? ''), $object['payment_intent'], (int) ($object['amount'] ?? 0) ?: null, isset($object['currency']) ? strtoupper((string) $object['currency']) : null, ['reason' => (string) ($object['reason'] ?? '')]);

                return $outcome->status === PaymentOutcome::REJECTED ? 'ignored' : 'processed';

            case 'charge.refunded':
                $intent = is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : null;
                foreach ($object['refunds']['data'] ?? [] as $refund) {
                    if ($intent !== null && ($refund['status'] ?? '') === 'succeeded') {
                        $this->payments->recordRefund('stripe', (string) $refund['id'], $intent, (int) $refund['amount'], strtoupper((string) $refund['currency']));
                    }
                }

                return 'processed';

            default:
                return 'ignored';
        }
    }

    public function refund(array $transaction, int $amount, string $reason, string $idempotencyKey): PaymentOutcome
    {
        $refund = $this->client()->refunds->create([
            'payment_intent' => (string) $transaction['reference'],
            'amount' => $amount,
            'metadata' => ['transaction_id' => (string) $transaction['id'], 'reason' => mb_substr($reason, 0, 200)],
        ], ['idempotency_key' => $idempotencyKey]);

        if ($refund->status === 'succeeded') {
            return $this->payments->recordRefund('stripe', (string) $refund->id, (string) $transaction['reference'], (int) $refund->amount, strtoupper((string) $refund->currency), ['reason' => $reason]);
        }

        $this->payments->event('stripe', 'refund.' . $refund->status, (string) $refund->id, $transaction['invoice_id'] !== null ? (int) $transaction['invoice_id'] : null, (int) $transaction['user_id'], $amount, (string) $transaction['currency']);

        return PaymentOutcome::pending('Stripe accepted the refund; it will be recorded when Stripe confirms it.');
    }

    public function testConnection(): HealthResult
    {
        try {
            $balance = $this->client()->balance->retrieve();
        } catch (ApiErrorException $exception) {
            return HealthResult::down('Stripe: ' . $exception->getMessage());
        }

        $configured = $this->settings->string('payments.stripe.mode');
        $live = (bool) $balance->livemode;
        if (($configured === 'live') !== $live) {
            return HealthResult::degraded(sprintf('The key is a %s key but the mode is set to %s.', $live ? 'live' : 'test', $configured));
        }
        if ($this->settings->get('payments.stripe.webhook_secret') === null) {
            return HealthResult::degraded('Connected, but no webhook signing secret is configured.');
        }

        return HealthResult::healthy(sprintf('Connected to Stripe (%s mode).', $live ? 'live' : 'test'));
    }
}
