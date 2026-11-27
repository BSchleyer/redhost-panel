<?php

declare(strict_types=1);

namespace Nebrix\Controller\Api\Admin;

use Nebrix\Api\ApiException;
use Nebrix\Api\Resource;
use Nebrix\Auth\User;
use Nebrix\Billing\InvoiceRepository;
use Nebrix\Billing\OrderRepository;
use Nebrix\Controller\Api\ApiController;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Repository\ProductRepository;
use Nebrix\Repository\UserRepository;

/**
 * Read-only admin resources: customers, products, orders and invoices.
 * Each route requires its admin scope and the matching staff permission.
 */
final class CatalogController extends ApiController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ProductRepository $products,
        private readonly OrderRepository $orders,
        private readonly InvoiceRepository $invoices,
    ) {
    }

    public function customers(Request $request): Response
    {
        [$page, $perPage] = $this->pagination($request);
        $filters = ['search' => $this->stringQuery($request, 'search'), 'status' => $this->stringQuery($request, 'status', 20), 'staff' => false];
        $group = $this->intQuery($request, 'customer_group_id');
        if ($group !== null) {
            $filters['group'] = $group;
        }
        $result = $this->users->paginate($filters, $page, $perPage);

        return $this->paginated(array_map(static fn (array $row): array => Resource::customer(User::fromRow($row)), $result['items']), $result['total'], $page, $perPage);
    }

    public function customer(Request $request, int $id): Response
    {
        $user = $this->users->find($id);
        // Staff accounts are managed under staff permissions, not as customers.
        if ($user === null || $user->isStaff()) {
            throw ApiException::notFound();
        }

        return $this->data(Resource::customer($user));
    }

    public function products(Request $request): Response
    {
        $products = $this->products->all(['category' => $this->stringQuery($request, 'category', 30), 'active' => $this->stringQuery($request, 'active', 1)]);

        return $this->pageOf($request, $products, Resource::adminProduct(...));
    }

    public function orders(Request $request): Response
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->orders->paginate($this->filters($request, ['status', 'search']), $page, $perPage);

        return $this->paginated(array_map(static fn (array $order): array => Resource::order($order), $result['items']), $result['total'], $page, $perPage);
    }

    public function order(Request $request, int $id): Response
    {
        $order = $this->orders->find($id) ?? throw ApiException::notFound();

        return $this->data(Resource::order($order, $this->orders->items($id)));
    }

    public function invoices(Request $request): Response
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->invoices->paginate($this->filters($request, ['status', 'type', 'search']), $page, $perPage);

        return $this->paginated(array_map(static fn (array $invoice): array => Resource::invoice($invoice), $result['items']), $result['total'], $page, $perPage);
    }

    public function invoice(Request $request, int $id): Response
    {
        $invoice = $this->invoices->find($id) ?? throw ApiException::notFound();

        return $this->data(Resource::invoice($invoice, $this->invoices->items($id)));
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function filters(Request $request, array $keys): array
    {
        $filters = [];
        foreach ($keys as $key) {
            $filters[$key] = $this->stringQuery($request, $key);
        }
        $userId = $this->intQuery($request, 'user_id');
        if ($userId !== null) {
            $filters['user_id'] = $userId;
        }

        return $filters;
    }
}
