<?php

declare(strict_types=1);

namespace Nebrix\Billing;

use Nebrix\Auth\User;
use Nebrix\Catalog\BillingCycle;
use Nebrix\Catalog\ProductCategory;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Provisioning\OrderPrechecks;
use Nebrix\Security\Encrypter;
use Nebrix\Mail\Mailer;
use Nebrix\Payment\PaymentService;
use Nebrix\Repository\CustomerGroupRepository;
use Nebrix\Repository\ProductRepository;
use Nebrix\Security\ValidationException;
use Nebrix\Security\Validator;
use RuntimeException;

/**
 * Places orders. Prices, tax and discounts are always computed on the
 * server from the product and customer group – never taken from the request.
 */
class OrderService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CustomerGroupRepository $groups,
        private readonly OrderRepository $orders,
        private readonly InvoiceService $invoices,
        private readonly PaymentService $payments,
        private readonly Mailer $mailer,
        private readonly Connection $db,
        private readonly Config $config,
        private readonly Encrypter $encrypter,
        private readonly OrderPrechecks $prechecks,
        private readonly \Nebrix\Billing\Tax\TaxService $taxes,
        private readonly \Nebrix\View\Translator $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $input customer-supplied product options
     * @return array{order_id: int, invoice_id: int, paid: bool}
     *
     * @throws ValidationException
     * @throws OrderException
     */
    public function place(User $user, int $productId, array $input, string $ip): array
    {
        $product = $this->products->find($productId);
        if ($product === null || !$product['is_active'] || !$product['is_visible']
            || ($product['customer_group_id'] !== null && (int) $product['customer_group_id'] !== $user->customerGroupId)) {
            throw new OrderException('This product is not available.');
        }

        $group = $user->customerGroupId !== null ? $this->groups->find($user->customerGroupId) : null;
        if ($group !== null && !(bool) $group['can_order']) {
            throw new OrderException('Your account is not allowed to place orders. Please contact support.');
        }

        $category = ProductCategory::get((string) $product['category']);
        $options = Validator::validate($input, array_map(static fn (array $o): string => $o['rules'], $category['options']), array_map(static fn (array $o): string => $o['label'], $category['options']));
        // Secret options (e.g. a domain transfer auth code) are never stored in plaintext.
        foreach ($category['options'] as $name => $definition) {
            if (($definition['secret'] ?? false) === true && is_string($options[$name] ?? null) && $options[$name] !== '') {
                $options[$name] = ['encrypted' => $this->encrypter->encrypt($options[$name], 'order-option:' . $name)];
            }
        }
        $this->prechecks->check($product, $user, $options);

        $discountBp = $group !== null ? (int) $group['discount_bp'] : 0;
        $price = (int) $product['price'];
        $setupFee = (int) $product['setup_fee'];
        $discount = Money::discount($price, $discountBp);
        $net = $price + $setupFee - $discount;
        // Tax by customer: reverse charge, OSS rates, small business (TaxService).
        $taxRate = $this->taxes->decide($user)->rate((int) $product['tax_rate_bp']);
        $tax = Money::tax($net, $taxRate);

        $result = $this->db->transaction(function () use ($user, $product, $options, $price, $setupFee, $discount, $net, $tax, $taxRate, $ip): array {
            if (!$this->products->reserveStock((int) $product['id'])) {
                throw new OrderException('This product is sold out.');
            }

            // Stored on the order and the invoice: written in the customer's language.
            $cycleLabel = $this->translator->translateTo((string) ($user->attribute('language') ?? 'en'), BillingCycle::labels()[(string) $product['billing_cycle']] ?? (string) $product['billing_cycle']);
            $label = (string) ($options['hostname'] ?? $options['server_name'] ?? $options['domain'] ?? '');
            [$orderId] = $this->orders->create([
                'number' => $this->invoices->nextNumber('order'),
                'user_id' => $user->id,
                'status' => OrderRepository::STATUS_PENDING,
                'currency' => (string) $product['currency'],
                'subtotal' => $price + $setupFee,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $net + $tax,
                'ip' => $ip,
            ], [[
                'product_id' => (int) $product['id'],
                'description' => trim(sprintf('%s (%s)%s', $product['name'], $cycleLabel, $label !== '' ? ' – ' . $label : '')),
                'quantity' => 1,
                'unit_price' => $price,
                'setup_fee' => $setupFee,
                'discount' => $discount,
                'tax_rate_bp' => $taxRate,
                'billing_cycle' => (string) $product['billing_cycle'],
                'options' => $options,
            ]]);

            $invoiceId = $this->invoices->createForOrder($orderId, $user);
            $paid = false;
            if ($net + $tax === 0) {
                // Free products are settled immediately through a zero-amount transaction.
                $paid = $this->payments->recordPayment('free', 'free-invoice-' . $invoiceId, $invoiceId, 0, (string) $product['currency'])->isSuccessful();
            }

            return ['order_id' => $orderId, 'invoice_id' => $invoiceId, 'paid' => $paid];
        });

        $order = $this->orders->find($result['order_id']);
        if ($order !== null) {
            $this->mailer->queue($user->email, $user->displayName(), 'order_confirmation', [
                'name' => $user->displayName(),
                'order_number' => (string) $order['number'],
                'order_total' => Money::format((int) $order['total'], (string) $order['currency']),
                'invoice_url' => rtrim($this->config->string('app.url'), '/') . '/invoices/' . $result['invoice_id'],
            ], null, [], $result['invoice_id']);
        }

        return $result;
    }

    /**
     * Price preview for the order page.
     *
     * @param array<string, mixed> $product
     * @return array{price: int, setup_fee: int, discount: int, net: int, tax: int, total: int, recurring: int, tax_rate_bp: int}
     */
    public function quote(array $product, ?int $customerGroupId, ?User $user = null): array
    {
        $group = $customerGroupId !== null ? $this->groups->find($customerGroupId) : null;
        $price = (int) $product['price'];
        $discount = Money::discount($price, $group !== null ? (int) $group['discount_bp'] : 0);
        $net = $price + (int) $product['setup_fee'] - $discount;
        $rate = $user !== null ? $this->taxes->decide($user)->rate((int) $product['tax_rate_bp']) : (int) $product['tax_rate_bp'];
        $tax = Money::tax($net, $rate);
        $recurringNet = $price - $discount;

        return [
            'price' => $price,
            'setup_fee' => (int) $product['setup_fee'],
            'discount' => $discount,
            'net' => $net,
            'tax' => $tax,
            'total' => $net + $tax,
            'recurring' => $recurringNet + Money::tax($recurringNet, $rate),
            'tax_rate_bp' => $rate,
        ];
    }

    public function cancel(int $orderId): void
    {
        $invoice = $this->db->fetchOne("SELECT `id` FROM `nx_invoices` WHERE `order_id` = ? AND `type` = 'order' AND `status` = 'unpaid'", [$orderId]);
        if ($invoice === null) {
            throw new RuntimeException('Only unpaid orders can be cancelled.');
        }
        $this->invoices->cancel((int) $invoice['id']);
    }
}
