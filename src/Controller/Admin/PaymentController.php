<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Authorization\Permission;
use Nebrix\Billing\InvoiceRepository;
use Nebrix\Billing\Money;
use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Payment\GatewayRegistry;
use Nebrix\Payment\PaymentOutcome;
use Nebrix\Payment\WebhookEventRepository;
use Nebrix\Security\ValidationException;
use Throwable;

/**
 * Transactions, refunds, webhook events, payment event history and
 * provider connection tests.
 */
final class PaymentController extends Controller
{
    public function __construct(
        private readonly Connection $db,
        private readonly GatewayRegistry $gateways,
        private readonly WebhookEventRepository $webhooks,
    ) {
    }

    public function transactions(Request $request): Response
    {
        $where = ['1 = 1'];
        $params = [];
        foreach (['gateway', 'status', 'type'] as $filter) {
            $value = $request->string($filter);
            if ($value !== '' && preg_match('/^[a-z_]{1,30}$/', $value) === 1) {
                $where[] = 't.`' . $filter . '` = ?';
                $params[] = $value;
            }
        }
        $search = mb_substr($request->string('search'), 0, 100);
        if ($search !== '') {
            $where[] = '(t.`reference` LIKE ? OR u.`email` LIKE ?)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $page = $this->page($request);
        $whereSql = implode(' AND ', $where);

        return $this->render($request, 'admin/payments/transactions.twig', [
            'items' => $this->db->fetchAll(
                'SELECT t.*, u.`email`, i.`number` AS invoice_number,
                        (SELECT COALESCE(SUM(r.`amount`), 0) FROM `nx_transactions` r WHERE r.`parent_id` = t.`id` AND r.`type` = \'refund\' AND r.`status` = \'completed\') AS refunded
                 FROM `nx_transactions` t JOIN `nx_users` u ON u.`id` = t.`user_id` LEFT JOIN `nx_invoices` i ON i.`id` = t.`invoice_id`
                 WHERE ' . $whereSql . ' ORDER BY t.`id` DESC LIMIT 50 OFFSET ?',
                [...$params, ($page - 1) * 50],
            ),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_transactions` t JOIN `nx_users` u ON u.`id` = t.`user_id` WHERE ' . $whereSql, $params),
            'page' => $page,
            'filters' => ['gateway' => $request->string('gateway'), 'status' => $request->string('status'), 'type' => $request->string('type'), 'search' => $search],
            'refund_key' => bin2hex(random_bytes(16)),
        ]);
    }

    public function refund(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::PAYMENTS_REFUND);
        $transaction = $this->db->fetchOne("SELECT * FROM `nx_transactions` WHERE `id` = ? AND `type` = 'payment' AND `status` = 'completed'", [$id]) ?? throw HttpException::notFound();
        $data = $this->validate($request, [
            'amount' => 'required|decimal|min:0.01',
            'reason' => 'required|string|max:255',
            // Generated with the form: a double submission carries the same
            // key, so the provider and the ledger treat it as one refund.
            'refund_key' => 'required|regex:/^[a-f0-9]{32}$/',
        ]);
        $amount = Money::parse((string) $data['amount'], (string) $transaction['currency']);
        $gateway = $this->gateways->get((string) $transaction['gateway']) ?? throw HttpException::badRequest('This payment cannot be refunded automatically.');

        // Serialise refunds of one payment so concurrent requests cannot both
        // pass the remaining-amount check before either is recorded.
        $lock = 'nebrix:refund:' . $id;
        if ((int) $this->db->fetchValue('SELECT GET_LOCK(?, 10)', [$lock]) !== 1) {
            $this->flash($request, 'error', 'Another refund for this payment is being processed. Please try again.');

            return $this->redirect('/admin/transactions');
        }

        try {
            $refunded = (int) $this->db->fetchValue("SELECT COALESCE(SUM(`amount`), 0) FROM `nx_transactions` WHERE `parent_id` = ? AND `type` = 'refund' AND `status` = 'completed'", [$id]);
            if ($amount <= 0 || $amount + $refunded > (int) $transaction['amount']) {
                throw ValidationException::withMessages(['amount' => 'The refund cannot exceed the remaining paid amount.']);
            }
            // Refunding a credit top-up removes the credit again; credit that
            // was already spent cannot be paid out a second time.
            $invoiceType = $transaction['invoice_id'] !== null ? $this->db->fetchValue('SELECT `type` FROM `nx_invoices` WHERE `id` = ?', [(int) $transaction['invoice_id']]) : null;
            if ($invoiceType === InvoiceRepository::TYPE_CREDIT) {
                $balance = (int) $this->db->fetchValue('SELECT `credit_balance` FROM `nx_users` WHERE `id` = ?', [(int) $transaction['user_id']]);
                if ($balance < $amount) {
                    throw ValidationException::withMessages(['amount' => sprintf('The customer has already used part of this credit. At most %s can be refunded.', Money::format(max(0, $balance), (string) $transaction['currency']))]);
                }
            }

            $outcome = $gateway->refund($transaction, $amount, (string) $data['reason'], 'refund-' . $id . '-' . $data['refund_key']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->audit($request, 'payment.refund_failed', 'transaction', $id, ['amount' => $amount, 'error' => mb_substr($exception->getMessage(), 0, 200)]);
            $this->flash($request, 'error', 'The refund failed: ' . mb_substr($exception->getMessage(), 0, 200));

            return $this->redirect('/admin/transactions');
        } finally {
            $this->db->fetchValue('SELECT RELEASE_LOCK(?)', [$lock]);
        }

        $this->audit($request, 'payment.refunded', 'transaction', $id, ['amount' => $amount, 'reason' => $data['reason'], 'status' => $outcome->status]);
        $this->flash($request, $outcome->status === PaymentOutcome::REJECTED ? 'error' : 'success', match ($outcome->status) {
            PaymentOutcome::COMPLETED => 'Refund completed.',
            PaymentOutcome::PENDING => $outcome->message,
            default => $outcome->message,
        });

        return $this->redirect('/admin/transactions');
    }

    public function webhooks(Request $request): Response
    {
        $page = $this->page($request);
        $gateway = in_array($request->string('gateway'), ['stripe', 'paypal'], true) ? $request->string('gateway') : '';
        $status = in_array($request->string('status'), ['received', 'processed', 'failed', 'ignored'], true) ? $request->string('status') : '';

        return $this->render($request, 'admin/payments/events.twig', [
            'webhooks' => $this->webhooks->paginate($gateway, $status, $page),
            'events' => $this->db->fetchAll('SELECT e.*, u.`email` FROM `nx_payment_events` e LEFT JOIN `nx_users` u ON u.`id` = e.`user_id` ORDER BY e.`id` DESC LIMIT 100'),
            'gateway' => $gateway,
            'status' => $status,
            'page' => $page,
        ]);
    }

    public function testGateway(Request $request, string $gateway): Response
    {
        $this->authorize($request, Permission::PAYMENTS_SETTINGS);
        $handler = $this->gateways->get($gateway) ?? throw HttpException::notFound();
        $result = $handler->testConnection();
        $this->audit($request, 'payment.gateway_tested', 'gateway', $gateway, ['status' => $result->status]);
        $this->flash($request, $result->isHealthy() ? 'success' : ($result->status === 'degraded' ? 'warning' : 'error'), $handler->label() . ': ' . $result->message);

        return $this->redirect('/admin/settings/payments');
    }
}
