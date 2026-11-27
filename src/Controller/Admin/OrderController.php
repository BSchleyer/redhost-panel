<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Authorization\Permission;
use Nebrix\Billing\OrderRepository;
use Nebrix\Billing\OrderService;
use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use RuntimeException;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly OrderService $orderService,
        private readonly Connection $db,
    ) {
    }

    public function index(Request $request): Response
    {
        $status = in_array($request->string('status'), ['pending', 'paid', 'cancelled'], true) ? $request->string('status') : '';
        $search = mb_substr($request->string('search'), 0, 100);
        $page = $this->page($request);

        return $this->render($request, 'admin/orders/index.twig', [
            'result' => $this->orders->paginate(['status' => $status, 'search' => $search], $page),
            'filters' => ['status' => $status, 'search' => $search],
            'page' => $page,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $order = $this->orders->find($id) ?? throw HttpException::notFound();

        return $this->render($request, 'admin/orders/show.twig', [
            'order' => $order,
            'items' => $this->orders->items($id),
            'customer' => $this->db->fetchOne('SELECT `id`, `email`, `first_name`, `last_name`, `company` FROM `nx_users` WHERE `id` = ?', [(int) $order['user_id']]),
            'invoices' => $this->db->fetchAll('SELECT * FROM `nx_invoices` WHERE `order_id` = ?', [$id]),
            'jobs' => $this->db->fetchAll("SELECT `id`, `type`, `status`, `attempts`, `last_error` FROM `nx_jobs` WHERE `unique_key` = ?", ['order.fulfil:' . $id]),
        ]);
    }

    public function cancel(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::ORDERS_MANAGE);
        $this->orders->find($id) ?? throw HttpException::notFound();

        try {
            $this->orderService->cancel($id);
            $this->audit($request, 'order.cancelled', 'order', $id);
            $this->flash($request, 'success', 'Order cancelled.');
        } catch (RuntimeException $exception) {
            $this->flash($request, 'error', $exception->getMessage());
        }

        return $this->redirect('/admin/orders/' . $id);
    }
}
