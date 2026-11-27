<?php

declare(strict_types=1);

namespace Nebrix\Controller\Customer;

use Nebrix\Billing\OrderException;
use Nebrix\Billing\OrderService;
use Nebrix\Catalog\BillingCycle;
use Nebrix\Catalog\ProductCategory;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Repository\ProductRepository;
use Nebrix\Security\RateLimiter;

final class StoreController extends Controller
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly OrderService $orders,
        private readonly RateLimiter $limiter,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $grouped = [];
        foreach ($this->products->orderable($user->customerGroupId) as $product) {
            $product['quote'] = $this->orders->quote($product, $user->customerGroupId);
            $product['feature_list'] = array_values(array_filter(array_map('trim', explode("\n", (string) $product['features']))));
            $grouped[(string) $product['category']][] = $product;
        }

        return $this->render($request, 'customer/store/index.twig', [
            'grouped' => $grouped,
            'categories' => ProductCategory::labels(),
            'cycles' => BillingCycle::labels(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $user = $this->user($request);
        $product = $this->findOrderable($slug, $user->customerGroupId);

        return $this->render($request, 'customer/store/order.twig', [
            'product' => $product,
            'options' => ProductCategory::get((string) $product['category'])['options'],
            'quote' => $this->orders->quote($product, $user->customerGroupId),
            'cycles' => BillingCycle::labels(),
            'require_legal' => true,
        ]);
    }

    public function order(Request $request, string $slug): Response
    {
        $user = $this->user($request);
        $product = $this->findOrderable($slug, $user->customerGroupId);
        $this->validate($request, ['accept_terms' => 'bool']);
        if ($request->input('accept_terms') !== '1') {
            throw \Nebrix\Security\ValidationException::withMessages(['accept_terms' => 'Please accept the terms to place the order.']);
        }

        $key = 'order:' . $user->id;
        if (!$this->limiter->attempt($key, 10, 3600)) {
            throw HttpException::tooManyRequests('You have placed many orders in a short time. Please try again later.');
        }

        $options = [];
        foreach (array_keys(ProductCategory::get((string) $product['category'])['options']) as $name) {
            $options[$name] = $request->input($name);
        }

        try {
            $result = $this->orders->place($user, (int) $product['id'], $options, $request->clientIp());
        } catch (OrderException $exception) {
            $this->flash($request, 'error', $exception->getMessage());

            return $this->redirect('/store');
        }

        $this->audit($request, 'order.placed', 'order', $result['order_id'], ['product' => (int) $product['id']]);
        $this->flash($request, 'success', $result['paid']
            ? 'Thank you for your order! Your service is being set up.'
            : 'Thank you for your order! Please pay the invoice to start the setup.');

        return $this->redirect('/invoices/' . $result['invoice_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrderable(string $slug, ?int $groupId): array
    {
        $product = $this->products->findBySlug($slug);
        if ($product === null || !$product['is_active'] || !$product['is_visible']
            || ($product['customer_group_id'] !== null && (int) $product['customer_group_id'] !== $groupId)
            || ($product['stock'] !== null && (int) $product['stock'] <= 0)) {
            throw HttpException::notFound();
        }

        return $product;
    }
}
