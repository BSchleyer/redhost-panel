<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Billing\CreditService;
use Nebrix\Billing\InvoiceService;
use Nebrix\Billing\OrderRepository;
use Nebrix\Payment\PaymentOutcome;
use Nebrix\Payment\PaymentService;
use Nebrix\Repository\UserRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\FeatureTestCase;
use Nebrix\Tests\Support\FakeHttp;
use Nebrix\Tests\Support\FakeStripeHttp;
use Stripe\ApiRequestor;

final class PaymentFlowTest extends FeatureTestCase
{
    private const WEBHOOK_SECRET = 'whsec_testsecret123';

    private FakeStripeHttp $stripe;

    private FakeHttp $paypal;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $settings = $this->app->container()->get(SettingsRepository::class);
        foreach ([
            'payments.stripe.enabled' => true, 'payments.stripe.mode' => 'test', 'payments.stripe.secret_key' => 'sk_test_abc123',
            'payments.stripe.webhook_secret' => self::WEBHOOK_SECRET,
            'payments.paypal.enabled' => true, 'payments.paypal.mode' => 'sandbox', 'payments.paypal.client_id' => 'client-id',
            'payments.paypal.client_secret' => 'client-secret', 'payments.paypal.webhook_id' => 'WH-123',
            'payments.credit.enabled' => true,
        ] as $key => $value) {
            $settings->set($key, $value);
        }

        $this->stripe = new FakeStripeHttp();
        ApiRequestor::setHttpClient($this->stripe);

        $this->paypal = (new FakeHttp())->on('POST', '/v1/oauth2/token', ['access_token' => 'A21AA-token', 'expires_in' => 3600]);
        $this->app->container()->set(ClientInterface::class, fn (): ClientInterface => $this->paypal->client());

        $this->customer = $this->createUser(Role::CUSTOMER, ['company' => 'ACME GmbH', 'city' => 'Gelsenkirchen', 'country' => 'DE']);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    /**
     * Creates an order with one item and its invoice (19 % tax): 10.00 net + 1.90 tax.
     *
     * @return array<string, mixed> invoice row
     */
    private function orderInvoice(?User $user = null): array
    {
        $user ??= $this->customer;
        [$orderId] = $this->app->container()->get(OrderRepository::class)->create(
            ['number' => 'ORD-' . bin2hex(random_bytes(3)), 'user_id' => $user->id, 'status' => 'pending', 'currency' => 'EUR', 'subtotal' => 1000, 'tax' => 190, 'total' => 1190],
            [['description' => 'LXC Small', 'unit_price' => 1000, 'tax_rate_bp' => 1900, 'billing_cycle' => 'monthly']],
        );
        $id = $this->app->container()->get(InvoiceService::class)->createForOrder($orderId, $user);

        return (array) $this->db->fetchOne('SELECT * FROM nx_invoices WHERE id = ?', [$id]);
    }

    private function stripeEvent(string $type, array $object, ?string $id = null): string
    {
        return json_encode([
            'id' => $id ?? 'evt_' . bin2hex(random_bytes(6)), 'object' => 'event', 'type' => $type, 'api_version' => '2026-11-18.endive',
            'created' => time(), 'livemode' => false, 'data' => ['object' => $object],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $invoice
     * @return array<string, mixed>
     */
    private function paidSession(array $invoice, string $intent = 'pi_123', ?int $amount = null, string $currency = 'eur'): array
    {
        return [
            'id' => 'cs_test_abc', 'object' => 'checkout.session', 'payment_status' => 'paid', 'status' => 'complete',
            'amount_total' => $amount ?? (int) $invoice['total'], 'currency' => $currency, 'payment_intent' => $intent,
            'metadata' => ['invoice_id' => (string) $invoice['id']], 'livemode' => false,
        ];
    }

    // ------------------------------------------------------- core bookkeeping

    public function testInvoiceTotalsAndGaplessNumbers(): void
    {
        $first = $this->orderInvoice();
        $second = $this->orderInvoice();

        self::assertSame(1190, (int) $first['total']);
        self::assertSame(190, (int) $first['tax']);
        self::assertMatchesRegularExpression('/^NX-\d{4}-\d{6}$/', (string) $first['number']);
        self::assertSame((int) substr((string) $first['number'], -6) + 1, (int) substr((string) $second['number'], -6));
        $billing = json_decode((string) $first['billing_details'], true);
        self::assertSame('ACME GmbH', $billing['company']);
    }

    public function testPaymentsAreIdempotentAndValidated(): void
    {
        $invoice = $this->orderInvoice();
        $payments = $this->app->container()->get(PaymentService::class);

        $wrongAmount = $payments->recordPayment('stripe', 'pi_wrong', (int) $invoice['id'], 100, 'EUR');
        $wrongCurrency = $payments->recordPayment('stripe', 'pi_usd', (int) $invoice['id'], 1190, 'USD');
        self::assertSame(PaymentOutcome::REJECTED, $wrongAmount->status);
        self::assertSame(PaymentOutcome::REJECTED, $wrongCurrency->status);
        self::assertSame('unpaid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));

        $first = $payments->recordPayment('stripe', 'pi_ok', (int) $invoice['id'], 1190, 'eur');
        $second = $payments->recordPayment('stripe', 'pi_ok', (int) $invoice['id'], 1190, 'EUR');

        self::assertSame(PaymentOutcome::COMPLETED, $first->status);
        self::assertSame(PaymentOutcome::DUPLICATE, $second->status);
        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));
        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_orders WHERE id = ?', [$invoice['order_id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_transactions WHERE reference = 'pi_ok'"));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'order.fulfil'"), 'Provisioning is queued exactly once.');
        self::assertSame(['payment.amount_mismatch', 'payment.amount_mismatch', 'payment.completed', 'payment.duplicate_ignored'], array_column(
            $this->db->fetchAll('SELECT event_type FROM nx_payment_events WHERE invoice_id = ? ORDER BY id', [$invoice['id']]),
            'event_type',
        ));
        self::assertNotNull($this->lastMail('payment_confirmation'));
    }

    // ------------------------------------------------------------------ Stripe

    public function testStripeCheckoutStartsWithServerSideAmount(): void
    {
        $invoice = $this->orderInvoice();
        $this->stripe->on('post', '/v1/checkout/sessions', static fn (array $p): array => [200, ['id' => 'cs_test_abc', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_abc']]);
        $this->actingAs($this->customer);

        $response = $this->post('/invoices/' . $invoice['id'] . '/pay', ['gateway' => 'stripe', 'amount' => '0.01']);

        $this->assertRedirect($response, 'https://checkout.stripe.com/c/pay/cs_test_abc');
        $params = $this->stripe->callsTo('post', '/v1/checkout/sessions')[0]['params'];
        self::assertSame(1190, (int) $params['line_items'][0]['price_data']['unit_amount'], 'The amount comes from the invoice, never from the request.');
        self::assertSame((string) $invoice['id'], $params['metadata']['invoice_id']);
        self::assertSame('unpaid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));
    }

    public function testStripeReturnIsVerifiedServerSide(): void
    {
        $invoice = $this->orderInvoice();
        $this->actingAs($this->customer);

        // Unpaid session: the redirect alone must not mark the invoice as paid.
        $this->stripe->on('get', '/v1/checkout/sessions/cs_test_abc', fn (): array => [200, ['payment_status' => 'unpaid'] + $this->paidSession($invoice)]);
        $this->get('/invoices/' . $invoice['id'] . '/return/stripe', ['session_id' => 'cs_test_abc']);
        self::assertSame('unpaid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));

        // Session of another invoice cannot be replayed.
        $this->stripe->on('get', '/v1/checkout/sessions/cs_test_abc', fn (): array => [200, ['metadata' => ['invoice_id' => '999999']] + $this->paidSession($invoice)]);
        $this->get('/invoices/' . $invoice['id'] . '/return/stripe', ['session_id' => 'cs_test_abc']);
        self::assertSame('unpaid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));

        $this->stripe->on('get', '/v1/checkout/sessions/cs_test_abc', fn (): array => [200, $this->paidSession($invoice)]);
        $page = $this->followRedirect($this->get('/invoices/' . $invoice['id'] . '/return/stripe', ['session_id' => 'cs_test_abc']));

        self::assertStringContainsString('Your payment has been received', $page->body());
        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));
    }

    public function testStripeWebhookSignatureIsEnforced(): void
    {
        $invoice = $this->orderInvoice();
        $payload = $this->stripeEvent('checkout.session.completed', $this->paidSession($invoice), 'evt_paid_1');

        // No/invalid signature, tampered payload, expired timestamp.
        self::assertSame(400, $this->postRaw('/webhooks/stripe', $payload)->status());
        self::assertSame(400, $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, 'whsec_wrong')])->status());
        $tampered = str_replace('"amount_total":1190', '"amount_total":1', $payload);
        self::assertSame(400, $this->postRaw('/webhooks/stripe', $tampered, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)])->status());
        self::assertSame(400, $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET, time() - 3600)])->status());
        self::assertSame('unpaid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));

        // Valid signature (no CSRF token needed for webhooks).
        $response = $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET), 'Content-Type' => 'application/json']);
        self::assertSame(200, $response->status());
        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));

        // Re-delivery of the same event is acknowledged but not processed again.
        $replay = $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)]);
        self::assertSame('{"status":"duplicate"}', $replay->body());
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_transactions WHERE gateway = 'stripe' AND status = 'completed'"));
        self::assertSame('processed', $this->db->fetchValue("SELECT status FROM nx_webhook_events WHERE event_id = 'evt_paid_1'"));
    }

    public function testStripeWebhookWithManipulatedAmountDoesNotSettle(): void
    {
        $invoice = $this->orderInvoice();
        $payload = $this->stripeEvent('checkout.session.completed', $this->paidSession($invoice, 'pi_cheap', 50));

        $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)]);

        self::assertSame('unpaid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));
        self::assertSame('failed', $this->db->fetchValue("SELECT status FROM nx_transactions WHERE reference = 'pi_cheap'"));
    }

    public function testStripeRefundFromAdminAndViaWebhook(): void
    {
        $invoice = $this->orderInvoice();
        $this->app->container()->get(PaymentService::class)->recordPayment('stripe', 'pi_refund', (int) $invoice['id'], 1190, 'EUR');
        $transactionId = (int) $this->db->fetchValue("SELECT id FROM nx_transactions WHERE reference = 'pi_refund'");
        $this->stripe->on('post', '/v1/refunds', static fn (array $p): array => [200, ['id' => 're_1', 'object' => 'refund', 'status' => 'succeeded', 'amount' => (int) $p['amount'], 'currency' => 'eur', 'payment_intent' => 'pi_refund']]);

        $key = str_repeat('ab', 16);
        $this->actingAs($this->createUser(Role::SUPPORT));
        self::assertSame(403, $this->post('/admin/transactions/' . $transactionId . '/refund', ['amount' => '5.00', 'reason' => 'x', 'refund_key' => $key])->status());

        $this->actingAs($this->createUser(Role::FINANCE));
        $this->post('/admin/transactions/' . $transactionId . '/refund', ['amount' => '20.00', 'reason' => 'too much', 'refund_key' => $key]);
        self::assertSame([], $this->stripe->callsTo('post', '/v1/refunds'), 'Refunds above the paid amount are rejected before calling Stripe.');

        $this->post('/admin/transactions/' . $transactionId . '/refund', ['amount' => '5.00', 'reason' => 'Goodwill']);
        self::assertSame([], $this->stripe->callsTo('post', '/v1/refunds'), 'A refund without the form key is rejected.');

        $this->post('/admin/transactions/' . $transactionId . '/refund', ['amount' => '5.00', 'reason' => 'Goodwill', 'refund_key' => $key]);
        self::assertSame(500, (int) $this->db->fetchValue("SELECT amount FROM nx_transactions WHERE type = 'refund' AND reference = 're_1'"));
        self::assertSame(500, (int) $this->db->fetchValue('SELECT amount_refunded FROM nx_invoices WHERE id = ?', [$invoice['id']]));
        $calls = $this->stripe->callsTo('post', '/v1/refunds');
        self::assertCount(1, $calls);
        self::assertContains('Idempotency-Key: refund-' . $transactionId . '-' . $key, $calls[0]['headers'], 'The form key is sent as the Stripe idempotency key.');

        // The same refund arriving by webhook is not booked twice.
        $payload = $this->stripeEvent('refund.updated', ['id' => 're_1', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 500, 'currency' => 'eur', 'payment_intent' => 'pi_refund']);
        $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)]);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_transactions WHERE type = 'refund'"));

        // Refund of the remaining amount made in the Stripe dashboard.
        $payload = $this->stripeEvent('refund.created', ['id' => 're_2', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 690, 'currency' => 'eur', 'payment_intent' => 'pi_refund']);
        $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)]);
        self::assertSame('refunded', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));
    }

    // ------------------------------------------------------------------ PayPal

    private function paypalOrder(array $invoice, string $status, ?array $capture = null): array
    {
        $unit = ['reference_id' => $invoice['number'], 'custom_id' => 'invoice:' . $invoice['id'], 'amount' => ['currency_code' => 'EUR', 'value' => '11.90']];
        if ($capture !== null) {
            $unit['payments'] = ['captures' => [$capture]];
        }

        return ['id' => '5O190127TN364715T', 'status' => $status, 'purchase_units' => [$unit], 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T']]];
    }

    public function testPayPalCheckoutCaptureAndValidation(): void
    {
        $invoice = $this->orderInvoice();
        $capture = ['id' => '3C679366HH908993F', 'status' => 'COMPLETED', 'custom_id' => 'invoice:' . $invoice['id'], 'amount' => ['currency_code' => 'EUR', 'value' => '11.90']];
        $this->paypal
            ->on('POST', '/v2/checkout/orders', fn (): Psr7Response => new Psr7Response(201, [], json_encode($this->paypalOrder($invoice, 'PAYER_ACTION_REQUIRED'), JSON_THROW_ON_ERROR)))
            ->on('GET', '/v2/checkout/orders/5O190127TN364715T', fn (): Psr7Response => new Psr7Response(200, [], json_encode($this->paypalOrder($invoice, 'APPROVED'), JSON_THROW_ON_ERROR)))
            ->on('POST', '/v2/checkout/orders/5O190127TN364715T/capture', fn (): Psr7Response => new Psr7Response(201, [], json_encode($this->paypalOrder($invoice, 'COMPLETED', $capture), JSON_THROW_ON_ERROR)));
        $this->actingAs($this->customer);

        $this->assertRedirect($this->post('/invoices/' . $invoice['id'] . '/pay', ['gateway' => 'paypal']), 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T');
        $order = FakeHttp::json($this->paypal->requests('POST', '/v2/checkout/orders')[0]);
        self::assertSame('11.90', $order['purchase_units'][0]['amount']['value']);
        self::assertSame('invoice:' . $invoice['id'], $order['purchase_units'][0]['custom_id']);
        self::assertSame('Bearer A21AA-token', $this->paypal->requests('POST', '/v2/checkout/orders')[0]->getHeaderLine('Authorization'));

        $this->get('/invoices/' . $invoice['id'] . '/return/paypal', ['token' => '5O190127TN364715T', 'PayerID' => 'X']);

        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));
        self::assertSame('3C679366HH908993F', $this->db->fetchValue("SELECT reference FROM nx_transactions WHERE gateway = 'paypal'"));
        self::assertSame('capture-5O190127TN364715T', $this->paypal->requests('POST', '/v2/checkout/orders/5O190127TN364715T/capture')[0]->getHeaderLine('PayPal-Request-Id'));
    }

    public function testPayPalOrderOfAnotherInvoiceIsRejected(): void
    {
        $mine = $this->orderInvoice();
        $other = $this->orderInvoice();
        $this->paypal->on('GET', '/v2/checkout/orders/5O190127TN364715T', fn (): Psr7Response => new Psr7Response(200, [], json_encode($this->paypalOrder($other, 'APPROVED'), JSON_THROW_ON_ERROR)));
        $this->actingAs($this->customer);

        $this->get('/invoices/' . $mine['id'] . '/return/paypal', ['token' => '5O190127TN364715T']);

        self::assertSame('unpaid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$mine['id']]));
        self::assertSame([], $this->paypal->requests('POST', '/v2/checkout/orders/5O190127TN364715T/capture'));
    }

    public function testPayPalWebhookIsVerifiedWithPayPal(): void
    {
        $invoice = $this->orderInvoice();
        $event = ['id' => 'WH-EVT-1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'CAPTURE-1', 'status' => 'COMPLETED', 'custom_id' => 'invoice:' . $invoice['id'], 'amount' => ['currency_code' => 'EUR', 'value' => '11.90']]];
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $headers = ['PAYPAL-AUTH-ALGO' => 'SHA256withRSA', 'PAYPAL-CERT-URL' => 'https://api.paypal.com/v1/notifications/certs/CERT', 'PAYPAL-TRANSMISSION-ID' => 'tid', 'PAYPAL-TRANSMISSION-SIG' => 'sig', 'PAYPAL-TRANSMISSION-TIME' => '2026-11-27T10:00:00Z'];

        $verification = 'FAILURE';
        $this->paypal->on('POST', '/v1/notifications/verify-webhook-signature', function ($request) use (&$verification, $payload): Psr7Response {
            $body = FakeHttp::json($request);
            self::assertSame('WH-123', $body['webhook_id']);
            self::assertStringEndsWith('"webhook_event":' . $payload . '}', (string) $request->getBody(), 'The event is forwarded byte for byte.');

            return new Psr7Response(200, [], json_encode(['verification_status' => $verification], JSON_THROW_ON_ERROR));
        });

        self::assertSame(400, $this->postRaw('/webhooks/paypal', $payload, $headers)->status());
        self::assertSame('unpaid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));

        self::assertSame(400, $this->postRaw('/webhooks/paypal', $payload, ['PAYPAL-TRANSMISSION-ID' => 'only-one-header'])->status());

        $verification = 'SUCCESS';
        self::assertSame(200, $this->postRaw('/webhooks/paypal', $payload, $headers)->status());
        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));
        self::assertSame('{"status":"duplicate"}', $this->postRaw('/webhooks/paypal', $payload, $headers)->body());
    }

    // ------------------------------------------------------------------ Credit

    public function testPayingWithAccountCredit(): void
    {
        $invoice = $this->orderInvoice();
        $credit = $this->app->container()->get(CreditService::class);
        $this->actingAs($this->customer);

        // Not enough credit: the method is not offered and cannot be forced.
        $this->post('/invoices/' . $invoice['id'] . '/pay', ['gateway' => 'credit']);
        self::assertSame('unpaid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));

        $credit->adjust($this->customer->id, 2000, 'test');
        $this->post('/invoices/' . $invoice['id'] . '/pay', ['gateway' => 'credit']);

        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));
        self::assertSame(810, $credit->balance($this->customer->id));

        // Paying again does not debit twice.
        $this->post('/invoices/' . $invoice['id'] . '/pay', ['gateway' => 'credit']);
        self::assertSame(810, $credit->balance($this->customer->id));
    }

    public function testCreditTopUpPaidByStripeAddsBalance(): void
    {
        $this->actingAs($this->customer);
        $this->post('/invoices/top-up', ['amount' => '25.00']);
        $invoice = (array) $this->db->fetchOne("SELECT * FROM nx_invoices WHERE type = 'credit' AND user_id = ?", [$this->customer->id]);
        self::assertSame(2500, (int) $invoice['total']);

        $payload = $this->stripeEvent('checkout.session.completed', $this->paidSession($invoice, 'pi_topup'));
        $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)]);

        self::assertSame(2500, $this->app->container()->get(CreditService::class)->balance($this->customer->id));
        // Top-up invoices cannot be paid with credit.
        $user = $this->app->container()->get(UserRepository::class)->find($this->customer->id);
        self::assertNotNull($user);
    }

    public function testRefundingACreditTopUpRemovesTheCredit(): void
    {
        $this->actingAs($this->customer);
        $this->post('/invoices/top-up', ['amount' => '25.00']);
        $invoice = (array) $this->db->fetchOne("SELECT * FROM nx_invoices WHERE type = 'credit' AND user_id = ?", [$this->customer->id]);
        $payload = $this->stripeEvent('checkout.session.completed', $this->paidSession($invoice, 'pi_topup'));
        $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)]);
        $credit = $this->app->container()->get(CreditService::class);
        $credit->adjust($this->customer->id, -2000, 'Spent on a service');
        $transactionId = (int) $this->db->fetchValue("SELECT id FROM nx_transactions WHERE reference = 'pi_topup'");
        $this->stripe->on('post', '/v1/refunds', static fn (array $p): array => [200, ['id' => 're_top_' . $p['amount'], 'object' => 'refund', 'status' => 'succeeded', 'amount' => (int) $p['amount'], 'currency' => 'eur', 'payment_intent' => 'pi_topup']]);

        // Staff cannot pay out credit that was already spent.
        $this->actingAs($this->createUser(Role::FINANCE));
        $this->post('/admin/transactions/' . $transactionId . '/refund', ['amount' => '25.00', 'reason' => 'Customer request', 'refund_key' => str_repeat('cd', 16)]);
        self::assertSame([], $this->stripe->callsTo('post', '/v1/refunds'));

        // The unspent part can be refunded and is removed from the balance.
        $this->post('/admin/transactions/' . $transactionId . '/refund', ['amount' => '5.00', 'reason' => 'Customer request', 'refund_key' => str_repeat('ce', 16)]);
        self::assertSame(0, $credit->balance($this->customer->id));

        // A refund issued in the Stripe dashboard is reversed too – the balance becomes a debt.
        $payload = $this->stripeEvent('refund.created', ['id' => 're_dash', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 2000, 'currency' => 'eur', 'payment_intent' => 'pi_topup']);
        $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)]);
        self::assertSame(-2000, $credit->balance($this->customer->id));
        self::assertSame(2, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_payment_events WHERE event_type = 'credit.reversed'"));
    }

    /**
     * @return array{0: array<string, mixed>, 1: int} invoice and active service
     */
    private function paidOrderWithActiveService(string $intent): array
    {
        $invoice = $this->orderInvoice();
        $this->app->container()->get(PaymentService::class)->recordPayment('stripe', $intent, (int) $invoice['id'], 1190, 'EUR');
        $itemId = (int) $this->db->fetchValue('SELECT id FROM nx_order_items WHERE order_id = ?', [$invoice['order_id']]);
        $serviceId = $this->app->container()->get(\Nebrix\Service\ServiceRepository::class)->create([
            'user_id' => $this->customer->id, 'order_item_id' => $itemId, 'category' => 'dedicated', 'name' => 'Dedicated', 'status' => 'active',
            'billing_cycle' => 'monthly', 'currency' => 'EUR', 'config' => [],
        ]);
        $this->db->run("DELETE FROM nx_jobs WHERE type = 'order.fulfil'");

        return [$invoice, $serviceId];
    }

    public function testFullRefundSuspendsWhatThePaymentBought(): void
    {
        [$invoice, $serviceId] = $this->paidOrderWithActiveService('pi_full');

        // A partial (goodwill) refund keeps the service running.
        $payload = $this->stripeEvent('refund.created', ['id' => 're_part', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 190, 'currency' => 'eur', 'payment_intent' => 'pi_full']);
        $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)]);
        self::assertSame('active', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));

        $payload = $this->stripeEvent('refund.created', ['id' => 're_rest', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 1000, 'currency' => 'eur', 'payment_intent' => 'pi_full']);
        $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)]);

        self::assertSame('refunded', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]));
        self::assertSame('suspended', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertStringContainsString('Payment refunded', (string) $this->db->fetchValue('SELECT suspension_reason FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertSame('cancelled', $this->db->fetchValue('SELECT status FROM nx_orders WHERE id = ?', [$invoice['order_id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'service.suspend'"));
    }

    public function testStripeDisputeSuspendsServicesOnce(): void
    {
        [$invoice, $serviceId] = $this->paidOrderWithActiveService('pi_dispute');
        $dispute = ['id' => 'dp_1', 'object' => 'dispute', 'amount' => 1190, 'currency' => 'eur', 'payment_intent' => 'pi_dispute', 'reason' => 'fraudulent', 'status' => 'needs_response'];

        foreach (['evt_d1', 'evt_d2'] as $eventId) {
            $payload = $this->stripeEvent('charge.dispute.created', $dispute, $eventId);
            self::assertSame(200, $this->postRaw('/webhooks/stripe', $payload, ['Stripe-Signature' => FakeStripeHttp::signature($payload, self::WEBHOOK_SECRET)])->status());
        }

        self::assertSame('suspended', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertStringContainsString('Payment disputed', (string) $this->db->fetchValue('SELECT suspension_reason FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_payment_events WHERE event_type = 'payment.disputed'"));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'service.suspend'"));
        self::assertSame('paid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoice['id']]), 'A dispute is not a refund; the invoice keeps its status.');
    }

    public function testPayPalDisputeIsRecorded(): void
    {
        [, $serviceId] = $this->paidOrderWithActiveService('PAYPAL-CAPTURE-9');
        $this->db->run("UPDATE nx_transactions SET gateway = 'paypal' WHERE reference = 'PAYPAL-CAPTURE-9'");
        $this->paypal->on('POST', '/v1/notifications/verify-webhook-signature', ['verification_status' => 'SUCCESS']);
        $event = ['id' => 'WH-DSP-1', 'event_type' => 'CUSTOMER.DISPUTE.CREATED', 'resource' => [
            'dispute_id' => 'PP-D-1', 'reason' => 'UNAUTHORISED', 'dispute_amount' => ['currency_code' => 'EUR', 'value' => '11.90'],
            'disputed_transactions' => [['seller_transaction_id' => 'PAYPAL-CAPTURE-9']],
        ]];
        $headers = ['PAYPAL-AUTH-ALGO' => 'SHA256withRSA', 'PAYPAL-CERT-URL' => 'https://api.paypal.com/v1/notifications/certs/CERT', 'PAYPAL-TRANSMISSION-ID' => 'tid2', 'PAYPAL-TRANSMISSION-SIG' => 'sig', 'PAYPAL-TRANSMISSION-TIME' => '2026-11-27T10:00:00Z'];

        self::assertSame(200, $this->postRaw('/webhooks/paypal', json_encode($event, JSON_THROW_ON_ERROR), $headers)->status());
        self::assertSame('suspended', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_payment_events WHERE event_type = 'payment.disputed' AND gateway = 'paypal'"));
    }

    // --------------------------------------------------------------- access

    public function testCustomersCannotAccessOrPayForeignInvoices(): void
    {
        $foreign = $this->orderInvoice($this->createUser());
        $this->actingAs($this->customer);

        self::assertSame(404, $this->get('/invoices/' . $foreign['id'])->status());
        self::assertSame(404, $this->post('/invoices/' . $foreign['id'] . '/pay', ['gateway' => 'stripe'])->status());
        self::assertSame(404, $this->get('/invoices/' . $foreign['id'] . '/return/stripe', ['session_id' => 'cs_test_x'])->status());
    }

    public function testAdminPaymentPagesRender(): void
    {
        $invoice = $this->orderInvoice();
        $this->app->container()->get(PaymentService::class)->recordPayment('stripe', 'pi_render', (int) $invoice['id'], 1190, 'EUR');
        $this->actingAs($this->createUser(Role::SUPER_ADMIN));

        foreach (['/admin/transactions', '/admin/transactions?gateway=stripe', '/admin/payments/webhooks', '/admin/settings/payments'] as $page) {
            self::assertSame(200, $this->get($page)->status(), $page);
        }
        $this->actingAs($this->customer);
        self::assertSame(200, $this->get('/invoices')->status());
        self::assertSame(200, $this->get('/invoices/' . $invoice['id'])->status());
    }
}
