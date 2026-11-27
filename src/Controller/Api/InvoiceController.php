<?php

declare(strict_types=1);

namespace Nebrix\Controller\Api;

use Nebrix\Api\ApiException;
use Nebrix\Api\Resource;
use Nebrix\Billing\InvoiceRepository;
use Nebrix\Core\Config;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Payment\GatewayRegistry;
use Nebrix\Payment\PaymentOutcome;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The customer's invoices and payment start. A provider payment returns the
 * provider's checkout URL; the invoice is settled only by the verified
 * provider confirmation (webhook or server-side return check), never by the
 * API client.
 */
final class InvoiceController extends ApiController
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly GatewayRegistry $gateways,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function index(Request $request): Response
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->invoices->paginate([
            'user_id' => $this->user($request)->id,
            'status' => $this->stringQuery($request, 'status', 20),
            'type' => $this->stringQuery($request, 'type', 20),
        ], $page, $perPage);

        return $this->paginated(array_map(static fn (array $invoice): array => Resource::invoice($invoice), $result['items']), $result['total'], $page, $perPage);
    }

    public function show(Request $request, int $id): Response
    {
        $user = $this->user($request);
        $invoice = $this->find($request, $id);
        $methods = $invoice['status'] === InvoiceRepository::STATUS_UNPAID ? array_keys($this->gateways->availableFor($invoice, $user)) : [];

        return $this->data(Resource::invoice($invoice, $this->invoices->items($id)) + ['payment_methods' => $methods]);
    }

    public function pdf(Request $request, int $id): Response
    {
        return $this->document($this->find($request, $id), 'pdf');
    }

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
            throw new ApiException(503, 'document_unavailable', $exception->getMessage());
        }

        return \Nebrix\Http\DocumentResponse::make($content, $documents->filename($invoice) . '.' . $type, $type === 'pdf' ? 'application/pdf' : 'application/xml');
    }

    public function pay(Request $request, int $id): Response
    {
        $user = $this->user($request);
        $invoice = $this->find($request, $id);
        $data = $this->validateInput($request, ['gateway' => 'required|alpha_dash|max:20']);

        if ($invoice['status'] !== InvoiceRepository::STATUS_UNPAID) {
            throw ApiException::conflict('This invoice is already settled or cancelled.');
        }
        $gateway = $this->gateways->availableFor($invoice, $user)[(string) $data['gateway']] ?? null;
        if ($gateway === null) {
            throw new ApiException(422, 'payment_method_unavailable', 'This payment method is not available for this invoice.');
        }
        $this->throttle('payment-start:' . $user->id, 20, 3600);

        $base = rtrim($this->config->string('app.url'), '/');
        try {
            $result = $gateway->start($invoice, $user, $base . '/invoices/' . $id . '/return/' . $gateway->key(), $base . '/invoices/' . $id . '?cancelled=1');
        } catch (Throwable $exception) {
            $this->logger->error('Payment start failed (API)', ['gateway' => $gateway->key(), 'invoice' => $id, 'message' => $exception->getMessage()]);

            throw new ApiException(502, 'upstream_error', 'The payment provider is currently not available. Please try again later.');
        }
        $this->apiAudit($request, 'invoice.payment_started', 'invoice', $id, ['gateway' => $gateway->key()]);

        if (is_string($result)) {
            return $this->data(['status' => 'redirect', 'checkout_url' => $result, 'message' => 'Send the customer to the checkout URL to complete the payment.']);
        }

        $status = match ($result->status) {
            PaymentOutcome::COMPLETED, PaymentOutcome::DUPLICATE => 'paid',
            PaymentOutcome::PENDING => 'pending',
            default => 'rejected',
        };
        if ($status === 'rejected') {
            throw new ApiException(422, 'payment_rejected', $result->message);
        }

        return $this->data(['status' => $status, 'checkout_url' => null, 'message' => $status === 'paid' ? 'The invoice has been paid.' : $result->message]);
    }

    /**
     * @return array<string, mixed>
     */
    private function find(Request $request, int $id): array
    {
        return $this->invoices->findForUser($id, $this->user($request)->id) ?? throw ApiException::notFound();
    }
}
