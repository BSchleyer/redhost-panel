<?php

declare(strict_types=1);

namespace Nebrix\Controller\Customer;

use Nebrix\Billing\InvoiceRepository;
use Nebrix\Billing\InvoiceService;
use Nebrix\Billing\Money;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Payment\GatewayRegistry;
use Nebrix\Payment\PaymentOutcome;
use Nebrix\Security\RateLimiter;
use Nebrix\Security\ValidationException;
use Nebrix\Settings\SettingsRepository;
use Psr\Log\LoggerInterface;
use Throwable;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceService $invoiceService,
        private readonly GatewayRegistry $gateways,
        private readonly RateLimiter $limiter,
        private readonly SettingsRepository $settings,
        private readonly Config $config,
        private readonly Connection $db,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->render($request, 'customer/invoices/index.twig', ['invoices' => $this->invoices->forUser($this->user($request)->id)]);
    }

    public function show(Request $request, int $id): Response
    {
        $user = $this->user($request);
        $invoice = $this->find($request, $id);
        $billing = json_decode((string) ($invoice['billing_details'] ?? '{}'), true);

        return $this->render($request, 'customer/invoices/show.twig', [
            'invoice' => $invoice,
            'items' => $this->invoices->items($id),
            'billing' => is_array($billing) ? $billing : [],
            'gateways' => $invoice['status'] === InvoiceRepository::STATUS_UNPAID ? $this->gateways->availableFor($invoice, $user) : [],
            'transactions' => $this->db->fetchAll('SELECT `gateway`, `type`, `status`, `amount`, `currency`, `created_at` FROM `nx_transactions` WHERE `invoice_id` = ? AND `user_id` = ? ORDER BY `id`', [$id, $user->id]),
            'company' => [
                'name' => $this->settings->string('general.company_name'),
                'address' => $this->settings->string('general.company_address'),
                'tax_id' => $this->settings->string('general.company_tax_id'),
            ],
            'cancelled' => $request->query('cancelled') === '1',
        ]);
    }

    /** Archived invoice PDF. */
    public function pdf(Request $request, int $id): Response
    {
        return $this->document($this->find($request, $id), 'pdf');
    }

    /** Archived electronic invoice (XRechnung, CII XML). */
    public function xml(Request $request, int $id): Response
    {
        return $this->document($this->find($request, $id), 'xml');
    }

    /**
     * @param array<string, mixed> $invoice
     */
    private function document(array $invoice, string $type): Response
    {
        $documents = $this->container->get(\Nebrix\Billing\Documents\InvoiceDocuments::class);
        try {
            $content = $type === 'pdf' ? $documents->pdf((int) $invoice['id']) : $documents->xml((int) $invoice['id']);
        } catch (\RuntimeException $exception) {
            throw new HttpException(503, $exception->getMessage());
        }

        return \Nebrix\Http\DocumentResponse::make($content, $documents->filename($invoice) . '.' . $type, $type === 'pdf' ? 'application/pdf' : 'application/xml');
    }

    public function pay(Request $request, int $id): Response
    {
        $user = $this->user($request);
        $invoice = $this->find($request, $id);
        $data = $this->validate($request, ['gateway' => 'required|alpha_dash|max:20']);

        if ($invoice['status'] !== InvoiceRepository::STATUS_UNPAID) {
            $this->flash($request, 'info', 'This invoice is already settled.');

            return $this->redirect('/invoices/' . $id);
        }

        $gateway = $this->gateways->availableFor($invoice, $user)[(string) $data['gateway']] ?? null;
        if ($gateway === null) {
            throw ValidationException::withMessages(['gateway' => 'This payment method is not available for this invoice.']);
        }

        $key = 'payment-start:' . $user->id;
        if (!$this->limiter->attempt($key, 20, 3600)) {
            throw HttpException::tooManyRequests();
        }

        $base = rtrim($this->config->string('app.url'), '/');
        try {
            $result = $gateway->start($invoice, $user, $base . '/invoices/' . $id . '/return/' . $gateway->key(), $base . '/invoices/' . $id . '?cancelled=1');
        } catch (Throwable $exception) {
            $this->logger->error('Payment start failed', ['gateway' => $gateway->key(), 'invoice' => $id, 'message' => $exception->getMessage()]);
            $this->flash($request, 'error', 'The payment provider is currently not available. Please try again later or choose another method.');

            return $this->redirect('/invoices/' . $id);
        }

        if (is_string($result)) {
            // External checkout page of the provider (validated https URL).
            return Response::redirect($result, 303);
        }

        $this->flashOutcome($request, $result);

        return $this->redirect('/invoices/' . $id);
    }

    public function complete(Request $request, int $id, string $gateway): Response
    {
        $invoice = $this->find($request, $id);
        $handler = $this->gateways->get($gateway);
        if ($handler === null || !$handler->isEnabled()) {
            throw HttpException::notFound();
        }

        if ($invoice['status'] === InvoiceRepository::STATUS_UNPAID) {
            try {
                $this->flashOutcome($request, $handler->complete($invoice, $request));
            } catch (Throwable $exception) {
                $this->logger->error('Payment completion failed', ['gateway' => $gateway, 'invoice' => $id, 'message' => $exception->getMessage()]);
                $this->flash($request, 'warning', 'We could not confirm the payment yet. If you were charged, the invoice will be updated automatically.');
            }
        } else {
            $this->flash($request, 'success', 'Thank you! The invoice has been paid.');
        }

        return $this->redirect('/invoices/' . $id);
    }

    public function topUp(Request $request): Response
    {
        $user = $this->user($request);
        $currency = $this->settings->string('general.default_currency') ?: 'EUR';
        $data = $this->validate($request, ['amount' => 'required|decimal|between:5,1000']);
        $amount = Money::parse((string) $data['amount'], $currency);

        $id = $this->invoiceService->createCreditTopUp($user, $amount, $currency);
        $this->audit($request, 'customer.credit_topup_requested', 'invoice', $id, ['amount' => $amount]);

        return $this->redirect('/invoices/' . $id);
    }

    private function flashOutcome(Request $request, PaymentOutcome $outcome): void
    {
        match ($outcome->status) {
            PaymentOutcome::COMPLETED, PaymentOutcome::DUPLICATE => $this->flash($request, 'success', 'Thank you! Your payment has been received.'),
            PaymentOutcome::PENDING => $this->flash($request, 'info', $outcome->message),
            default => $this->flash($request, 'error', $outcome->message),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function find(Request $request, int $id): array
    {
        return $this->invoices->findForUser($id, $this->user($request)->id) ?? throw HttpException::notFound();
    }
}
