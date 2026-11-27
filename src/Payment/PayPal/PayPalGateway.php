<?php

declare(strict_types=1);

namespace Nebrix\Payment\PayPal;

use GuzzleHttp\ClientInterface;
use Nebrix\Auth\User;
use Nebrix\Billing\Money;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Integration\HealthResult;
use Nebrix\Payment\PaymentGateway;
use Nebrix\Payment\PaymentOutcome;
use Nebrix\Payment\PaymentService;
use Nebrix\Payment\WebhookEventRepository;
use Nebrix\Settings\SettingsRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * PayPal Checkout with Orders v2.
 *
 * Flow: create order (amount from the invoice, custom_id = invoice ID) →
 * customer approves at PayPal → server-side capture on return → capture
 * amount/currency/custom_id validated → PaymentService. Webhooks
 * (PAYMENT.CAPTURE.*) are verified with PayPal's verification API and
 * settle payments when the customer never returns.
 */
class PayPalGateway implements PaymentGateway
{
    private ?PayPalClient $client = null;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ClientInterface $http,
        private readonly PaymentService $payments,
        private readonly WebhookEventRepository $webhooks,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function key(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function isEnabled(): bool
    {
        return $this->settings->bool('payments.paypal.enabled')
            && $this->settings->string('payments.paypal.client_id') !== ''
            && $this->settings->get('payments.paypal.client_secret') !== null;
    }

    public function isAvailableFor(array $invoice, User $user): bool
    {
        return in_array(strtoupper((string) $invoice['currency']), ['EUR', 'USD', 'GBP', 'CHF', 'AUD', 'CAD', 'PLN', 'SEK', 'DKK', 'NOK', 'CZK'], true);
    }

    public function client(): PayPalClient
    {
        return $this->client ??= new PayPalClient(
            $this->http,
            $this->settings->string('payments.paypal.client_id'),
            (string) $this->settings->get('payments.paypal.client_secret'),
            $this->settings->string('payments.paypal.mode') !== 'live',
        );
    }

    public function start(array $invoice, User $user, string $returnUrl, string $cancelUrl): string|PaymentOutcome
    {
        $currency = strtoupper((string) $invoice['currency']);
        $order = $this->client()->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $invoice['number'],
                'custom_id' => 'invoice:' . $invoice['id'],
                'description' => mb_substr('Invoice ' . $invoice['number'], 0, 127),
                'amount' => ['currency_code' => $currency, 'value' => Money::toDecimal((int) $invoice['total'], $currency)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => mb_substr($this->settings->string('general.company_name'), 0, 127),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ]]],
        ], 'invoice-' . $invoice['id'] . '-' . bin2hex(random_bytes(8)));

        $orderId = (string) ($order['id'] ?? '');
        $approve = null;
        foreach ($order['links'] ?? [] as $link) {
            if (in_array($link['rel'] ?? '', ['payer-action', 'approve'], true)) {
                $approve = (string) $link['href'];
            }
        }
        if ($orderId === '' || $approve === null || !str_starts_with($approve, 'https://')) {
            throw new PayPalException('PayPal did not return an approval link.');
        }

        $this->payments->event('paypal', 'checkout.created', $orderId, (int) $invoice['id'], (int) $invoice['user_id'], (int) $invoice['total'], $currency);

        return $approve;
    }

    public function complete(array $invoice, Request $request): PaymentOutcome
    {
        $orderId = $request->string('token');
        if (preg_match('/^[A-Z0-9-]{5,64}$/i', $orderId) !== 1) {
            return PaymentOutcome::rejected('Invalid PayPal response.');
        }

        try {
            $order = $this->client()->getOrder($orderId);
            if ($this->invoiceIdFromUnit($order['purchase_units'][0] ?? []) !== (int) $invoice['id']) {
                $this->payments->event('paypal', 'checkout.invoice_mismatch', $orderId, (int) $invoice['id'], (int) $invoice['user_id'], null, null);

                return PaymentOutcome::rejected('This PayPal payment does not belong to the invoice.');
            }

            if (($order['status'] ?? '') === 'APPROVED') {
                $order = $this->client()->captureOrder($orderId);
            }

            return $this->settleFromOrder($order, (int) $invoice['id']);
        } catch (PayPalException $exception) {
            $this->logger->warning('PayPal capture failed', ['invoice' => $invoice['id'], 'issue' => $exception->issue(), 'message' => $exception->getMessage()]);
            $this->payments->event('paypal', 'capture.error', $orderId, (int) $invoice['id'], (int) $invoice['user_id'], null, null, ['issue' => $exception->issue()]);

            return PaymentOutcome::rejected($exception->issue() === 'INSTRUMENT_DECLINED'
                ? 'PayPal declined the payment method. Please choose another funding source.'
                : 'The PayPal payment could not be completed.');
        }
    }

    /**
     * @param array<string, mixed> $order
     */
    private function settleFromOrder(array $order, int $expectedInvoiceId): PaymentOutcome
    {
        $unit = $order['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? null;
        if (!is_array($capture)) {
            return PaymentOutcome::pending('The payment has not been captured yet.');
        }

        return $this->settleCapture($capture, $expectedInvoiceId ?: $this->invoiceIdFromUnit($unit));
    }

    /**
     * @param array<string, mixed> $capture
     */
    private function settleCapture(array $capture, ?int $invoiceId): PaymentOutcome
    {
        $captureId = (string) ($capture['id'] ?? '');
        $status = (string) ($capture['status'] ?? '');
        $currency = strtoupper((string) ($capture['amount']['currency_code'] ?? ''));
        $value = (string) ($capture['amount']['value'] ?? '');

        if ($invoiceId === null || $captureId === '') {
            return PaymentOutcome::rejected('Incomplete PayPal capture.');
        }

        if ($status === 'PENDING') {
            $this->payments->event('paypal', 'capture.pending', $captureId, $invoiceId, null, null, $currency, ['reason' => $capture['status_details']['reason'] ?? null]);

            return PaymentOutcome::pending('PayPal is still processing the payment. Your invoice will be updated automatically.');
        }
        if ($status !== 'COMPLETED') {
            $this->payments->recordFailure('paypal', $captureId, $invoiceId, 'Capture status ' . $status);

            return PaymentOutcome::rejected('The PayPal payment was not completed.');
        }

        try {
            $amount = Money::parse($value, $currency);
        } catch (\InvalidArgumentException) {
            return PaymentOutcome::rejected('Invalid amount in PayPal capture.');
        }

        return $this->payments->recordPayment('paypal', $captureId, $invoiceId, $amount, $currency, ['capture_status' => $status]);
    }

    /**
     * @param array<string, mixed> $unit purchase unit or capture resource
     */
    private function invoiceIdFromUnit(array $unit): ?int
    {
        $custom = (string) ($unit['custom_id'] ?? '');

        return preg_match('/^invoice:(\d+)$/', $custom, $match) === 1 ? (int) $match[1] : null;
    }

    public function handleWebhook(Request $request): Response
    {
        $payload = $request->body();
        $event = json_decode($payload, true);
        if (!is_array($event) || !isset($event['id'], $event['event_type'])) {
            return Response::json(['error' => 'invalid payload'], 400);
        }

        $headers = [];
        foreach (['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'] as $name) {
            $headers[$name] = (string) $request->header($name);
        }

        try {
            $verified = $this->client()->verifyWebhookSignature($headers, $this->settings->string('payments.paypal.webhook_id'), $payload);
        } catch (PayPalException $exception) {
            $this->logger->error('PayPal webhook verification request failed', ['message' => $exception->getMessage()]);

            return Response::json(['error' => 'verification unavailable'], 503);
        }
        if (!$verified) {
            $this->payments->event('paypal', 'webhook.invalid_signature', (string) $event['id'], null, null, null, null, ['type' => $event['event_type']]);

            return Response::json(['error' => 'invalid signature'], 400);
        }

        $eventId = (string) $event['id'];
        if (!$this->webhooks->begin('paypal', $eventId, (string) $event['event_type'], $payload)) {
            return Response::json(['status' => 'duplicate']);
        }

        try {
            $status = $this->processEvent($event);
            $this->webhooks->finish('paypal', $eventId, $status);
        } catch (Throwable $exception) {
            $this->webhooks->finish('paypal', $eventId, 'failed', $exception->getMessage());
            $this->logger->error('PayPal webhook processing failed', ['event' => $eventId, 'message' => $exception->getMessage()]);

            return Response::json(['error' => 'processing failed'], 500);
        }

        return Response::json(['status' => $status]);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function processEvent(array $event): string
    {
        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];

        switch ($event['event_type']) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $outcome = $this->settleCapture($resource, $this->invoiceIdFromUnit($resource));

                return $outcome->status === PaymentOutcome::REJECTED ? 'ignored' : 'processed';

            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.DECLINED':
                $this->payments->recordFailure('paypal', (string) ($resource['id'] ?? ''), $this->invoiceIdFromUnit($resource), 'Capture ' . strtolower((string) ($resource['status'] ?? 'denied')));

                return 'processed';

            case 'CHECKOUT.ORDER.APPROVED':
                // The customer approved but never returned: capture server-side.
                $order = $this->client()->captureOrder((string) ($resource['id'] ?? ''));
                $this->settleFromOrder($order, 0);

                return 'processed';

            case 'PAYMENT.CAPTURE.REFUNDED':
            case 'PAYMENT.CAPTURE.REVERSED':
                // The resource is the refund/reversal; its "up" link points to the capture.
                $captureId = null;
                foreach ($resource['links'] ?? [] as $link) {
                    if (($link['rel'] ?? '') === 'up' && preg_match('#/captures/([A-Z0-9-]+)#i', (string) ($link['href'] ?? ''), $m) === 1) {
                        $captureId = $m[1];
                    }
                }
                if ($captureId === null || ($resource['status'] ?? '') !== 'COMPLETED') {
                    $this->logger->warning('PayPal refund/reversal event without a usable capture reference', ['event' => $event['id'] ?? null, 'type' => $event['event_type']]);

                    return 'ignored';
                }
                $currency = strtoupper((string) ($resource['amount']['currency_code'] ?? ''));
                $this->payments->recordRefund('paypal', (string) $resource['id'], $captureId, Money::parse((string) ($resource['amount']['value'] ?? '0'), $currency), $currency, $event['event_type'] === 'PAYMENT.CAPTURE.REVERSED' ? ['reason' => 'reversal'] : []);

                return 'processed';

            case 'CUSTOMER.DISPUTE.CREATED':
                $currency = isset($resource['dispute_amount']['currency_code']) ? strtoupper((string) $resource['dispute_amount']['currency_code']) : null;
                $amount = $currency !== null ? Money::parse((string) ($resource['dispute_amount']['value'] ?? '0'), $currency) : null;
                $handled = false;
                foreach ($resource['disputed_transactions'] ?? [] as $transaction) {
                    $captureId = (string) ($transaction['seller_transaction_id'] ?? '');
                    if ($captureId !== '') {
                        $outcome = $this->payments->recordDispute('paypal', (string) ($resource['dispute_id'] ?? ''), $captureId, $amount ?: null, $currency, ['reason' => (string) ($resource['reason'] ?? '')]);
                        $handled = $handled || $outcome->status !== PaymentOutcome::REJECTED;
                    }
                }

                return $handled ? 'processed' : 'ignored';

            default:
                return 'ignored';
        }
    }

    public function refund(array $transaction, int $amount, string $reason, string $idempotencyKey): PaymentOutcome
    {
        $currency = (string) $transaction['currency'];
        $refund = $this->client()->refundCapture((string) $transaction['reference'], [
            'amount' => ['value' => Money::toDecimal($amount, $currency), 'currency_code' => $currency],
            'note_to_payer' => mb_substr($reason, 0, 255),
        ], $idempotencyKey);

        if (($refund['status'] ?? '') === 'COMPLETED') {
            return $this->payments->recordRefund('paypal', (string) $refund['id'], (string) $transaction['reference'], $amount, $currency, ['reason' => $reason]);
        }

        $this->payments->event('paypal', 'refund.' . strtolower((string) ($refund['status'] ?? 'unknown')), (string) ($refund['id'] ?? ''), $transaction['invoice_id'] !== null ? (int) $transaction['invoice_id'] : null, (int) $transaction['user_id'], $amount, $currency);

        return PaymentOutcome::pending('PayPal accepted the refund; it will be recorded when PayPal confirms it.');
    }

    public function testConnection(): HealthResult
    {
        try {
            $this->client()->accessToken();
        } catch (PayPalException $exception) {
            return HealthResult::down($exception->getMessage());
        }

        $message = sprintf('Authenticated with PayPal (%s).', $this->settings->string('payments.paypal.mode'));
        if ($this->settings->string('payments.paypal.webhook_id') === '') {
            return HealthResult::degraded($message . ' No webhook ID configured – webhooks cannot be verified.');
        }

        return HealthResult::healthy($message);
    }
}
