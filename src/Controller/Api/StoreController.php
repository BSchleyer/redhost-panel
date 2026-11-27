<?php

declare(strict_types=1);

namespace Nebrix\Controller\Api;

use Nebrix\Api\ApiException;
use Nebrix\Api\Resource;
use Nebrix\Billing\OrderException;
use Nebrix\Billing\OrderRepository;
use Nebrix\Billing\OrderService;
use Nebrix\Catalog\ProductCategory;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Repository\ProductRepository;

/**
 * Products the customer can order, their orders and order placement.
 */
final class StoreController extends ApiController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly OrderService $orderService,
        private readonly OrderRepository $orders,
    ) {
    }

    public function products(Request $request): Response
    {
        $user = $this->user($request);
        $category = $this->stringQuery($request, 'category', 30);
        $items = [];
        foreach ($this->products->orderable($user->customerGroupId) as $product) {
            if ($category === '' || $product['category'] === $category) {
                $items[] = $product;
            }
        }

        return $this->pageOf($request, $items, fn (array $product): array => Resource::product($product, $this->orderService->quote($product, $user->customerGroupId, $user)));
    }

    public function product(Request $request, int $id): Response
    {
        $user = $this->user($request);
        $product = $this->findOrderable($id, $user->customerGroupId);

        return $this->data(Resource::product($product, $this->orderService->quote($product, $user->customerGroupId, $user)));
    }

    public function orders(Request $request): Response
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->orders->paginate(['user_id' => $this->user($request)->id, 'status' => $this->stringQuery($request, 'status', 20)], $page, $perPage);

        return $this->paginated(array_map(fn (array $order): array => Resource::order($order, $this->orders->items((int) $order['id'])), $result['items']), $result['total'], $page, $perPage);
    }

    public function order(Request $request, int $id): Response
    {
        $order = $this->orders->findForUser($id, $this->user($request)->id) ?? throw ApiException::notFound();

        return $this->data(Resource::order($order, $this->orders->items($id)));
    }

    /**
     * Places an order exactly like the store page: same validation, prices,
     * prechecks and rate limit. The response contains the invoice to pay.
     */
    public function placeOrder(Request $request): Response
    {
        $user = $this->user($request);
        $data = $this->validateInput($request, [
            'product_id' => 'required|int|min:1',
            'accept_terms' => 'required|bool',
            'options' => 'nullable|array',
        ]);
        if ($data['accept_terms'] !== true) {
            throw ApiException::unprocessable('The terms must be accepted ("accept_terms": true) to place an order.');
        }
        $product = $this->findOrderable((int) $data['product_id'], $user->customerGroupId);
        $this->throttle('order:' . $user->id, 10, 3600, 'You have placed many orders in a short time. Please try again later.');

        $input = is_array($data['options']) ? $data['options'] : [];
        $options = [];
        foreach (array_keys(ProductCategory::get((string) $product['category'])['options']) as $name) {
            $options[$name] = $input[$name] ?? null;
        }

        try {
            $result = $this->orderService->place($user, (int) $product['id'], $options, $request->clientIp());
        } catch (OrderException $exception) {
            throw new ApiException(422, 'order_rejected', $exception->getMessage());
        }
        $this->apiAudit($request, 'order.placed', 'order', $result['order_id'], ['product' => (int) $product['id']]);

        $order = $this->orders->find($result['order_id']) ?? throw new \RuntimeException('Order not found after placement.');

        return $this->data([
            'order' => Resource::order($order, $this->orders->items($result['order_id'])),
            'invoice_id' => $result['invoice_id'],
            'paid' => $result['paid'],
        ], 201);
    }

    /**
     * Same availability rules as the store page.
     *
     * @return array<string, mixed>
     */
    private function findOrderable(int $id, ?int $groupId): array
    {
        $product = $this->products->find($id);
        if ($product === null || !$product['is_active'] || !$product['is_visible']
            || ($product['customer_group_id'] !== null && (int) $product['customer_group_id'] !== $groupId)
            || ($product['stock'] !== null && (int) $product['stock'] <= 0)) {
            throw ApiException::notFound('The product is not available.');
        }

        return $product;
    }
}
