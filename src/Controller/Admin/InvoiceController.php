<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Authorization\Permission;
use Nebrix\Billing\InvoiceRepository;
use Nebrix\Billing\InvoiceService;
use Nebrix\Billing\Money;
use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Payment\PaymentService;
use Nebrix\Security\ValidationException;
use RuntimeException;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceService $invoiceService,
        private readonly PaymentService $payments,
        private readonly Connection $db,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'status' => in_array($request->string('status'), ['unpaid', 'paid', 'cancelled', 'refunded'], true) ? $request->string('status') : '',
            'type' => in_array($request->string('type'), ['order', 'renewal', 'credit'], true) ? $request->string('type') : '',
            'search' => mb_substr($request->string('search'), 0, 100),
        ];
        $page = $this->page($request);

        return $this->render($request, 'admin/invoices/index.twig', ['result' => $this->invoices->paginate($filters, $page), 'filters' => $filters, 'page' => $page]);
    }

    /** Archived invoice PDF. */
    public function pdf(Request $request, int $id): Response
    {
        return $this->document(($this->invoices->find($id) ?? throw HttpException::notFound()), 'pdf');
    }

    /** Archived electronic invoice (XRechnung, CII XML). */
    public function xml(Request $request, int $id): Response
    {
        return $this->document(($this->invoices->find($id) ?? throw HttpException::notFound()), 'xml');
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

    public function show(Request $request, int $id): Response
    {
        $invoice = $this->invoices->find($id) ?? throw HttpException::notFound();
        $billing = json_decode((string) ($invoice['billing_details'] ?? '{}'), true);

        return $this->render($request, 'admin/invoices/show.twig', [
            'invoice' => $invoice,
            'items' => $this->invoices->items($id),
            'billing' => is_array($billing) ? $billing : [],
            'transactions' => $this->db->fetchAll('SELECT * FROM `nx_transactions` WHERE `invoice_id` = ? ORDER BY `id`', [$id]),
            'events' => $this->db->fetchAll('SELECT * FROM `nx_payment_events` WHERE `invoice_id` = ? ORDER BY `id`', [$id]),
        ]);
    }

    public function cancel(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::INVOICES_MANAGE);
        try {
            $this->invoiceService->cancel($id);
            $this->audit($request, 'invoice.cancelled', 'invoice', $id);
            $this->flash($request, 'success', 'Invoice cancelled.');
        } catch (RuntimeException $exception) {
            $this->flash($request, 'error', $exception->getMessage());
        }

        return $this->redirect('/admin/invoices/' . $id);
    }

    /**
     * Records an offline payment (e.g. bank transfer) with its reference.
     */
    public function recordPayment(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::INVOICES_MANAGE);
        $invoice = $this->invoices->find($id) ?? throw HttpException::notFound();
        $data = $this->validate($request, ['amount' => 'required|decimal|min:0', 'reference' => 'required|string|max:100|regex:/^[A-Za-z0-9._\/ -]+$/']);
        $amount = Money::parse((string) $data['amount'], (string) $invoice['currency']);
        if ($amount !== (int) $invoice['total']) {
            throw ValidationException::withMessages(['amount' => 'The amount must equal the invoice total. Partial payments are not supported.']);
        }

        $outcome = $this->payments->recordPayment('manual', 'manual-' . $id . '-' . (string) $data['reference'], $id, $amount, (string) $invoice['currency'], ['recorded_by' => $this->user($request)->id, 'reference' => $data['reference']]);
        $this->audit($request, 'invoice.manual_payment', 'invoice', $id, ['amount' => $amount, 'reference' => $data['reference'], 'status' => $outcome->status]);
        $this->flash($request, $outcome->isSuccessful() ? 'success' : 'error', $outcome->isSuccessful() ? 'Payment recorded.' : $outcome->message);

        return $this->redirect('/admin/invoices/' . $id);
    }
}
