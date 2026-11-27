<?php

declare(strict_types=1);

namespace Nebrix\Api;

use Nebrix\Auth\User;
use Nebrix\Catalog\ProductCategory;
use Nebrix\Service\Service;

/**
 * JSON representations of API resources.
 *
 * Every field is listed explicitly – database rows are never passed through,
 * so internal state (remote IDs, provisioning config, encrypted secrets,
 * password hashes, notes) cannot leak. Amounts are integers in minor units
 * (cents) together with the currency; timestamps are ISO 8601 in UTC.
 */
final class Resource
{
    public static function date(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $timestamp = strtotime($value . ' UTC');

        return $timestamp === false ? null : gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /**
     * @return array<string, mixed>
     */
    public static function account(User $user): array
    {
        $a = $user->attributes;

        return [
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->username,
            'first_name' => $user->firstName,
            'last_name' => $user->lastName,
            'company' => self::nullableString($a['company'] ?? null),
            'vat_id' => self::nullableString($a['vat_id'] ?? null),
            'address' => [
                'line1' => self::nullableString($a['address_line1'] ?? null),
                'line2' => self::nullableString($a['address_line2'] ?? null),
                'postcode' => self::nullableString($a['postcode'] ?? null),
                'city' => self::nullableString($a['city'] ?? null),
                'country' => self::nullableString($a['country'] ?? null),
            ],
            'phone' => self::nullableString($a['phone'] ?? null),
            'language' => self::nullableString($a['language'] ?? null),
            'credit_balance' => $user->creditBalance,
            'email_verified' => $user->isEmailVerified(),
            'two_factor_enabled' => $user->hasTwoFactor(),
            'created_at' => self::date($a['created_at'] ?? null),
        ];
    }

    /**
     * Admin view of a customer (no secrets, no internal notes).
     *
     * @return array<string, mixed>
     */
    public static function customer(User $user): array
    {
        return [
            'status' => $user->status,
            'role' => $user->roleSlug,
            'customer_group_id' => $user->customerGroupId,
            'last_login_at' => self::date($user->attributes['last_login_at'] ?? null),
        ] + self::account($user);
    }

    /**
     * @param array<string, mixed> $product
     * @param array{price: int, setup_fee: int, discount: int, net: int, tax: int, total: int, recurring: int, tax_rate_bp?: int} $quote
     * @return array<string, mixed>
     */
    public static function product(array $product, array $quote): array
    {
        $category = (string) $product['category'];
        $options = [];
        foreach (ProductCategory::exists($category) ? ProductCategory::get($category)['options'] : [] as $name => $definition) {
            $options[] = [
                'name' => $name,
                'label' => $definition['label'],
                'rules' => $definition['rules'],
                'required' => str_contains($definition['rules'], 'required'),
                'help' => $definition['help'] ?? null,
            ];
        }

        return [
            'id' => (int) $product['id'],
            'slug' => (string) $product['slug'],
            'name' => (string) $product['name'],
            'category' => $category,
            'description' => self::nullableString($product['description'] ?? null),
            'features' => array_values(array_filter(array_map('trim', explode("\n", (string) ($product['features'] ?? ''))))),
            'billing_cycle' => (string) $product['billing_cycle'],
            'currency' => (string) $product['currency'],
            'tax_rate_bp' => $quote['tax_rate_bp'] ?? (int) $product['tax_rate_bp'],
            'pricing' => [
                'price' => $quote['price'],
                'setup_fee' => $quote['setup_fee'],
                'discount' => $quote['discount'],
                'first_payment_total' => $quote['total'],
                'recurring_total' => $quote['recurring'],
            ],
            'in_stock' => $product['stock'] === null || (int) $product['stock'] > 0,
            'order_options' => $options,
        ];
    }

    /**
     * Admin view of a product.
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public static function adminProduct(array $product): array
    {
        return [
            'id' => (int) $product['id'],
            'slug' => (string) $product['slug'],
            'name' => (string) $product['name'],
            'category' => (string) $product['category'],
            'billing_cycle' => (string) $product['billing_cycle'],
            'currency' => (string) $product['currency'],
            'price' => (int) $product['price'],
            'setup_fee' => (int) $product['setup_fee'],
            'tax_rate_bp' => (int) $product['tax_rate_bp'],
            'stock' => $product['stock'] === null ? null : (int) $product['stock'],
            'is_active' => (bool) $product['is_active'],
            'is_visible' => (bool) $product['is_visible'],
            'auto_provision' => (bool) $product['auto_provision'],
            'integration_id' => $product['integration_id'] === null ? null : (int) $product['integration_id'],
            'customer_group_id' => $product['customer_group_id'] === null ? null : (int) $product['customer_group_id'],
            'active_services' => (int) ($product['active_services'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public static function order(array $order, array $items = []): array
    {
        return [
            'id' => (int) $order['id'],
            'number' => (string) $order['number'],
            'user_id' => (int) $order['user_id'],
            'status' => (string) $order['status'],
            'currency' => (string) $order['currency'],
            'subtotal' => (int) $order['subtotal'],
            'discount' => (int) $order['discount'],
            'tax' => (int) $order['tax'],
            'total' => (int) $order['total'],
            'created_at' => self::date($order['created_at'] ?? null),
            'paid_at' => self::date($order['paid_at'] ?? null),
            'cancelled_at' => self::date($order['cancelled_at'] ?? null),
            'items' => array_map(static fn (array $item): array => [
                'id' => (int) $item['id'],
                'product_id' => $item['product_id'] === null ? null : (int) $item['product_id'],
                'description' => (string) $item['description'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => (int) $item['unit_price'],
                'setup_fee' => (int) $item['setup_fee'],
                'discount' => (int) $item['discount'],
                'tax_rate_bp' => (int) $item['tax_rate_bp'],
                'billing_cycle' => (string) $item['billing_cycle'],
                'options' => self::publicOptions(self::decodeObject($item['options'] ?? null), null),
                'service_id' => isset($item['service_id']) ? (int) $item['service_id'] : null,
            ], $items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function service(Service $service): array
    {
        $options = $service->config['_options'] ?? [];

        return [
            'id' => $service->id,
            'user_id' => $service->userId,
            'name' => $service->name,
            'category' => $service->category,
            'status' => $service->status,
            'product_id' => $service->productId,
            'billing_cycle' => $service->billingCycle,
            'recurring_amount' => $service->recurringAmount,
            'tax_rate_bp' => $service->taxRateBp,
            'currency' => $service->currency,
            'next_due_at' => self::date($service->nextDueAt),
            'cancel_at' => self::date($service->attributes['cancel_at'] ?? null),
            'options' => self::publicOptions(is_array($options) ? $options : [], $service->category),
            'has_credentials' => $service->hasCredentials,
            'created_at' => self::date($service->attributes['created_at'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $invoice
     * @param list<array<string, mixed>>|null $items
     * @return array<string, mixed>
     */
    public static function invoice(array $invoice, ?array $items = null): array
    {
        $data = [
            'id' => (int) $invoice['id'],
            'number' => (string) $invoice['number'],
            'user_id' => (int) $invoice['user_id'],
            'type' => (string) $invoice['type'],
            'status' => (string) $invoice['status'],
            'order_id' => $invoice['order_id'] === null ? null : (int) $invoice['order_id'],
            'reference_invoice_id' => isset($invoice['reference_invoice_id']) ? (int) $invoice['reference_invoice_id'] : null,
            'tax_category' => $invoice['tax_category'] ?? null,
            'tax_note' => $invoice['tax_note'] ?? null,
            'service_id' => $invoice['service_id'] === null ? null : (int) $invoice['service_id'],
            'currency' => (string) $invoice['currency'],
            'subtotal' => (int) $invoice['subtotal'],
            'tax' => (int) $invoice['tax'],
            'total' => (int) $invoice['total'],
            'amount_refunded' => (int) $invoice['amount_refunded'],
            'due_at' => self::date($invoice['due_at'] ?? null),
            'paid_at' => self::date($invoice['paid_at'] ?? null),
            'cancelled_at' => self::date($invoice['cancelled_at'] ?? null),
            'created_at' => self::date($invoice['created_at'] ?? null),
        ];
        if ($items !== null) {
            $data['items'] = array_map(static fn (array $item): array => [
                'description' => (string) $item['description'],
                'quantity' => (int) $item['quantity'],
                'unit_amount' => (int) $item['unit_amount'],
                'tax_rate_bp' => (int) $item['tax_rate_bp'],
                'total' => (int) $item['total'],
                'service_id' => $item['service_id'] === null ? null : (int) $item['service_id'],
                'period_start' => self::date($item['period_start'] ?? null),
                'period_end' => self::date($item['period_end'] ?? null),
            ], $items);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $ticket
     * @param list<array<string, mixed>>|null $messages
     * @param bool $staffView staff see who wrote a message; customers see "staff"
     * @return array<string, mixed>
     */
    public static function ticket(array $ticket, ?array $messages = null, bool $staffView = false): array
    {
        $data = [
            'id' => (int) $ticket['id'],
            'user_id' => (int) $ticket['user_id'],
            'subject' => (string) $ticket['subject'],
            'status' => (string) $ticket['status'],
            'priority' => (string) $ticket['priority'],
            'service_id' => $ticket['service_id'] === null ? null : (int) $ticket['service_id'],
            'created_at' => self::date($ticket['created_at'] ?? null),
            'updated_at' => self::date($ticket['updated_at'] ?? null),
            'closed_at' => self::date($ticket['closed_at'] ?? null),
        ];
        if ($staffView) {
            $data['assigned_to'] = $ticket['assigned_to'] === null ? null : (int) $ticket['assigned_to'];
        }
        if ($messages !== null) {
            $data['messages'] = array_map(static fn (array $message): array => [
                'id' => (int) $message['id'],
                'author' => (bool) $message['is_staff'] ? 'staff' : 'customer',
                'author_id' => $staffView && $message['user_id'] !== null ? (int) $message['user_id'] : null,
                'message' => (string) $message['message'],
                'created_at' => self::date($message['created_at'] ?? null),
            ], $messages);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function token(ApiToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'token' => $token->maskedToken(),
            'scopes' => $token->scopes,
            'allowed_ips' => $token->allowedIps,
            'expires_at' => self::date($token->expiresAt),
            'last_used_at' => self::date($token->lastUsedAt),
        ];
    }

    /**
     * Customer order options without secret ones (e.g. domain auth codes).
     *
     * @param array<string, mixed> $options
     * @param string|null $category null = options of any category (order items)
     * @return array<string, scalar|null>
     */
    private static function publicOptions(array $options, ?string $category): array
    {
        if ($category === null) {
            $definitions = [];
            foreach (ProductCategory::all() as $definition) {
                foreach ($definition['options'] as $name => $option) {
                    // A name that is secret in any category is treated as secret.
                    $definitions[$name] = ($definitions[$name]['secret'] ?? false) === true ? $definitions[$name] : $option;
                }
            }
        } else {
            $definitions = ProductCategory::exists($category) ? ProductCategory::get($category)['options'] : [];
        }
        $public = [];
        foreach ($options as $name => $value) {
            $definition = $definitions[$name] ?? null;
            if ($definition === null || ($definition['secret'] ?? false) === true || (!is_scalar($value) && $value !== null)) {
                continue;
            }
            $public[(string) $name] = $value;
        }

        return $public;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeObject(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;

        return is_array($decoded) ? $decoded : [];
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
