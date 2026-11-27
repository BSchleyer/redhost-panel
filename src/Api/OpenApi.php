<?php

declare(strict_types=1);

namespace Nebrix\Api;

/**
 * OpenAPI 3.1 description of the REST API (served at /api/v1/openapi.json).
 *
 * The endpoint table below is the single source for the document; a test
 * checks that it matches the route table (paths, methods and scopes).
 */
final class OpenApi
{
    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5?: array<string, mixed>, 6?: list<string>}>
     *         method, path, scope, summary, response schema, request body schema, query parameters
     */
    public static function endpoints(): array
    {
        $list = ['page', 'per_page'];

        return [
            ['GET', '/me', ApiScope::ACCOUNT_READ, 'Your account and the current token', 'Account'],
            ['GET', '/products', ApiScope::PRODUCTS_READ, 'Products you can order', 'ProductList', [], [...$list, 'category']],
            ['GET', '/products/{id}', ApiScope::PRODUCTS_READ, 'One orderable product', 'Product'],
            ['GET', '/orders', ApiScope::ORDERS_READ, 'Your orders', 'OrderList', [], [...$list, 'status']],
            ['GET', '/orders/{id}', ApiScope::ORDERS_READ, 'One of your orders', 'Order'],
            ['POST', '/orders', ApiScope::ORDERS_CREATE, 'Place an order (creates the invoice)', 'OrderPlaced', [
                'type' => 'object',
                'required' => ['product_id', 'accept_terms'],
                'properties' => [
                    'product_id' => ['type' => 'integer'],
                    'accept_terms' => ['type' => 'boolean', 'description' => 'Must be true: the customer accepts the terms.'],
                    'options' => ['type' => 'object', 'description' => 'Order options of the product (see order_options of the product), e.g. {"hostname": "web01"}.'],
                ],
            ]],
            ['GET', '/services', ApiScope::SERVICES_READ, 'Your services', 'ServiceList', [], [...$list, 'status']],
            ['GET', '/services/{id}', ApiScope::SERVICES_READ, 'One of your services', 'Service'],
            ['GET', '/services/{id}/status', ApiScope::SERVICES_READ, 'Live status from the remote system', 'ServiceStatus'],
            ['GET', '/services/{id}/actions', ApiScope::SERVICES_READ, 'Actions available for the service', 'ActionList'],
            ['POST', '/services/{id}/actions/{action}', ApiScope::SERVICES_MANAGE, 'Run a service action (power, password reset, reinstall, …)', 'ActionResult', [
                'type' => 'object',
                'description' => 'Values of the action fields; "confirm": true for actions that require a confirmation.',
                'properties' => ['confirm' => ['type' => 'boolean']],
                'additionalProperties' => true,
            ]],
            ['GET', '/services/{id}/credentials', ApiScope::SERVICES_CREDENTIALS, 'Access data of the service (audit-logged)', 'Credentials'],
            ['POST', '/services/{id}/cancel', ApiScope::SERVICES_MANAGE, 'Cancel the service at the end of the paid term (or a later date)', 'CancellationResult', [
                'type' => 'object',
                'properties' => ['end_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Optional later end date; earlier dates are moved to the end of the paid term.'], 'reason' => ['type' => 'string', 'maxLength' => 5000]],
            ]],
            ['POST', '/services/{id}/cancel/withdraw', ApiScope::SERVICES_MANAGE, 'Take back a scheduled cancellation', 'Service'],
            ['GET', '/invoices', ApiScope::INVOICES_READ, 'Your invoices', 'InvoiceList', [], [...$list, 'status', 'type']],
            ['GET', '/invoices/{id}', ApiScope::INVOICES_READ, 'One of your invoices with items and payment methods', 'Invoice'],
            ['GET', '/invoices/{id}/pdf', ApiScope::INVOICES_READ, 'Invoice as PDF (archived document)', 'Binary'],
            ['GET', '/invoices/{id}/xml', ApiScope::INVOICES_READ, 'Electronic invoice (XRechnung 3.0, CII XML)', 'Binary'],
            ['POST', '/invoices/{id}/pay', ApiScope::INVOICES_PAY, 'Pay with account credit or get the provider checkout URL', 'PaymentStarted', [
                'type' => 'object',
                'required' => ['gateway'],
                'properties' => ['gateway' => ['type' => 'string', 'description' => 'One of payment_methods of the invoice (credit, stripe, paypal).']],
            ]],
            ['GET', '/tickets', ApiScope::TICKETS_READ, 'Your support tickets', 'TicketList', [], [...$list, 'status']],
            ['GET', '/tickets/{id}', ApiScope::TICKETS_READ, 'One ticket with its messages', 'Ticket'],
            ['POST', '/tickets', ApiScope::TICKETS_WRITE, 'Open a support ticket', 'Ticket', [
                'type' => 'object',
                'required' => ['subject', 'message'],
                'properties' => [
                    'subject' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 200],
                    'message' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 20000],
                    'service_id' => ['type' => ['integer', 'null']],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high']],
                ],
            ]],
            ['POST', '/tickets/{id}/replies', ApiScope::TICKETS_WRITE, 'Reply to a ticket', 'Ticket', self::messageBody()],
            ['POST', '/tickets/{id}/close', ApiScope::TICKETS_WRITE, 'Close a ticket', 'Ticket'],

            ['GET', '/admin/customers', ApiScope::ADMIN_CUSTOMERS_READ, 'Admin: customers', 'CustomerList', [], [...$list, 'search', 'status', 'customer_group_id']],
            ['GET', '/admin/customers/{id}', ApiScope::ADMIN_CUSTOMERS_READ, 'Admin: one customer', 'Customer'],
            ['GET', '/admin/products', ApiScope::ADMIN_PRODUCTS_READ, 'Admin: all products', 'AdminProductList', [], [...$list, 'category', 'active']],
            ['GET', '/admin/orders', ApiScope::ADMIN_ORDERS_READ, 'Admin: orders', 'OrderList', [], [...$list, 'status', 'search', 'user_id']],
            ['GET', '/admin/orders/{id}', ApiScope::ADMIN_ORDERS_READ, 'Admin: one order', 'Order'],
            ['GET', '/admin/invoices', ApiScope::ADMIN_INVOICES_READ, 'Admin: invoices', 'InvoiceList', [], [...$list, 'status', 'type', 'search', 'user_id']],
            ['GET', '/admin/invoices/{id}', ApiScope::ADMIN_INVOICES_READ, 'Admin: one invoice', 'Invoice'],
            ['GET', '/admin/services', ApiScope::ADMIN_SERVICES_READ, 'Admin: services', 'ServiceList', [], [...$list, 'status', 'category', 'search', 'user_id', 'integration_id']],
            ['GET', '/admin/services/{id}', ApiScope::ADMIN_SERVICES_READ, 'Admin: one service', 'Service'],
            ['POST', '/admin/services/{id}/suspend', ApiScope::ADMIN_SERVICES_WRITE, 'Admin: suspend a service', 'Service', [
                'type' => 'object',
                'properties' => ['reason' => ['type' => 'string', 'maxLength' => 255], 'notify' => ['type' => 'boolean', 'description' => 'E-mail the customer (default true).']],
            ]],
            ['POST', '/admin/services/{id}/unsuspend', ApiScope::ADMIN_SERVICES_WRITE, 'Admin: reactivate a suspended service', 'Service'],
            ['POST', '/admin/services/{id}/terminate', ApiScope::ADMIN_SERVICES_WRITE, 'Admin: terminate a service (deletes the remote resource)', 'Service', [
                'type' => 'object',
                'required' => ['confirm'],
                'properties' => ['confirm' => ['type' => 'string', 'enum' => ['TERMINATE']], 'reason' => ['type' => 'string', 'maxLength' => 255]],
            ]],
            ['POST', '/admin/services/{id}/retry', ApiScope::ADMIN_SERVICES_WRITE, 'Admin: queue provisioning again', 'Service'],
            ['GET', '/admin/tickets', ApiScope::ADMIN_TICKETS_READ, 'Admin: support tickets', 'TicketList', [], [...$list, 'status', 'search', 'user_id', 'assigned_to']],
            ['GET', '/admin/tickets/{id}', ApiScope::ADMIN_TICKETS_READ, 'Admin: one ticket with messages', 'Ticket'],
            ['POST', '/admin/tickets/{id}/replies', ApiScope::ADMIN_TICKETS_WRITE, 'Admin: reply to a ticket (e-mails the customer)', 'Ticket', self::messageBody()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(): array
    {
        $paths = [];
        foreach (self::endpoints() as $endpoint) {
            [$method, $path, $scope, $summary, $schema] = $endpoint;
            $body = $endpoint[5] ?? [];
            $query = $endpoint[6] ?? [];

            $parameters = [];
            if (preg_match_all('/\{(\w+)\}/', $path, $matches) > 0) {
                foreach ($matches[1] as $name) {
                    $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => $name === 'action' ? ['type' => 'string', 'pattern' => '^[a-z0-9_]{1,30}$'] : ['type' => 'integer']];
                }
            }
            foreach ($query as $name) {
                $parameters[] = ['name' => $name, 'in' => 'query', 'required' => false, 'schema' => in_array($name, ['page', 'per_page', 'user_id', 'customer_group_id', 'integration_id', 'assigned_to'], true) ? ['type' => 'integer', 'minimum' => 1] : ['type' => 'string']];
            }
            if ($method === 'POST') {
                $parameters[] = ['name' => 'Idempotency-Key', 'in' => 'header', 'required' => false, 'description' => 'Optional; repeats with the same key and body return the first response (24 hours).', 'schema' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9_.:-]{8,128}$']];
            }

            $operation = [
                'summary' => $summary,
                'description' => sprintf('Required scope: `%s`.', $scope) . (ApiScope::isAdmin($scope) ? sprintf(' Requires the staff permissions `%s`.', implode('`, `', ApiScope::permissionsFor($scope))) : ''),
                'security' => [['bearerAuth' => []]],
                'x-scope' => $scope,
                'parameters' => $parameters,
                'responses' => [
                    self::successStatus($method, $path) => [
                        'description' => 'Success',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/' . $schema]]],
                    ],
                    '401' => ['$ref' => '#/components/responses/Error'],
                    '403' => ['$ref' => '#/components/responses/Error'],
                    '404' => ['$ref' => '#/components/responses/Error'],
                    '422' => ['$ref' => '#/components/responses/Error'],
                    '429' => ['$ref' => '#/components/responses/Error'],
                ],
            ];
            if ($body !== []) {
                $operation['requestBody'] = ['required' => isset($body['required']), 'content' => ['application/json' => ['schema' => $body]]];
            }
            $paths['/api/v1' . $path][strtolower($method)] = $operation;
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Nebrix API',
                'version' => '1.0.0',
                'description' => "Token-authenticated JSON API. Create a token under Account → API tokens and send it as `Authorization: Bearer nbx_…`.\n\n"
                    . "Amounts are integers in minor units (cents) with a `currency`; timestamps are ISO 8601 in UTC. Lists are paginated (`page`, `per_page` ≤ 100) and return `data` and `meta`. "
                    . "Errors return `{\"error\": {\"code\", \"message\"}}`. Every token is rate-limited per minute (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`).",
            ],
            'servers' => [['url' => '/']],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'nbx_ followed by 48 hex characters']],
                'responses' => [
                    'Error' => ['description' => 'Error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                ],
                'schemas' => self::schemas(),
            ],
        ];
    }

    private static function successStatus(string $method, string $path): string
    {
        if ($method !== 'POST') {
            return '200';
        }
        if (in_array($path, ['/orders', '/tickets', '/services/{id}/cancel'], true) || str_ends_with($path, '/replies')) {
            return '201';
        }

        // Admin lifecycle operations are queued; reinstall actions answer 202 as well.
        return str_starts_with($path, '/admin/services/') ? '202' : '200';
    }

    /**
     * @return array<string, mixed>
     */
    private static function messageBody(): array
    {
        return ['type' => 'object', 'required' => ['message'], 'properties' => ['message' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 20000]]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function schemas(): array
    {
        $int = ['type' => 'integer'];
        $str = ['type' => 'string'];
        $nullableStr = ['type' => ['string', 'null']];
        $nullableInt = ['type' => ['integer', 'null']];
        $date = ['type' => ['string', 'null'], 'format' => 'date-time'];
        $data = static fn (string $ref): array => ['type' => 'object', 'properties' => ['data' => ['$ref' => '#/components/schemas/' . $ref]]];
        $list = static fn (string $ref): array => ['type' => 'object', 'properties' => [
            'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/' . $ref]],
            'meta' => ['$ref' => '#/components/schemas/Meta'],
        ]];

        $account = ['type' => 'object', 'properties' => [
            'id' => $int, 'email' => $str, 'username' => $nullableStr, 'first_name' => $nullableStr, 'last_name' => $nullableStr,
            'company' => $nullableStr, 'vat_id' => $nullableStr, 'address' => ['type' => 'object'], 'phone' => $nullableStr, 'language' => $nullableStr,
            'credit_balance' => $int, 'email_verified' => ['type' => 'boolean'], 'two_factor_enabled' => ['type' => 'boolean'], 'created_at' => $date,
        ]];
        $service = ['type' => 'object', 'properties' => [
            'id' => $int, 'user_id' => $int, 'name' => $str, 'category' => $str, 'status' => ['type' => 'string', 'enum' => ['pending', 'provisioning', 'active', 'suspended', 'terminated', 'failed', 'cancelled']],
            'product_id' => $nullableInt, 'billing_cycle' => $str, 'recurring_amount' => $int, 'tax_rate_bp' => $int, 'currency' => $str, 'next_due_at' => $date,
            'cancel_at' => $date, 'options' => ['type' => 'object'], 'has_credentials' => ['type' => 'boolean'], 'created_at' => $date,
        ]];
        $order = ['type' => 'object', 'properties' => [
            'id' => $int, 'number' => $str, 'user_id' => $int, 'status' => $str, 'currency' => $str, 'subtotal' => $int, 'discount' => $int, 'tax' => $int, 'total' => $int,
            'created_at' => $date, 'paid_at' => $date, 'cancelled_at' => $date, 'items' => ['type' => 'array', 'items' => ['type' => 'object']],
        ]];
        $invoice = ['type' => 'object', 'properties' => [
            'id' => $int, 'number' => $str, 'user_id' => $int, 'type' => $str, 'status' => $str, 'order_id' => $nullableInt, 'service_id' => $nullableInt,
            'currency' => $str, 'subtotal' => $int, 'tax' => $int, 'total' => $int, 'amount_refunded' => $int, 'due_at' => $date, 'paid_at' => $date,
            'cancelled_at' => $date, 'created_at' => $date, 'items' => ['type' => 'array', 'items' => ['type' => 'object']], 'payment_methods' => ['type' => 'array', 'items' => $str],
        ]];
        $ticket = ['type' => 'object', 'properties' => [
            'id' => $int, 'user_id' => $int, 'subject' => $str, 'status' => $str, 'priority' => $str, 'service_id' => $nullableInt,
            'created_at' => $date, 'updated_at' => $date, 'closed_at' => $date,
            'messages' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['id' => $int, 'author' => ['type' => 'string', 'enum' => ['customer', 'staff']], 'message' => $str, 'created_at' => $date]]],
        ]];
        $product = ['type' => 'object', 'properties' => [
            'id' => $int, 'slug' => $str, 'name' => $str, 'category' => $str, 'description' => $nullableStr, 'features' => ['type' => 'array', 'items' => $str],
            'billing_cycle' => $str, 'currency' => $str, 'tax_rate_bp' => $int, 'pricing' => ['type' => 'object'], 'in_stock' => ['type' => 'boolean'],
            'order_options' => ['type' => 'array', 'items' => ['type' => 'object']],
        ]];

        return [
            'Error' => ['type' => 'object', 'properties' => ['error' => ['type' => 'object', 'required' => ['code', 'message'], 'properties' => [
                'code' => $str, 'message' => $str, 'fields' => ['type' => 'object', 'description' => 'Validation errors per field'], 'reference' => $str,
            ]]]],
            'Meta' => ['type' => 'object', 'properties' => ['page' => $int, 'per_page' => $int, 'total' => $int, 'last_page' => $int]],
            'AccountData' => $account,
            'Account' => $data('AccountData'),
            'CustomerData' => $account,
            'Customer' => $data('CustomerData'),
            'CustomerList' => $list('CustomerData'),
            'ProductData' => $product,
            'Product' => $data('ProductData'),
            'ProductList' => $list('ProductData'),
            'AdminProductList' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['type' => 'object']], 'meta' => ['$ref' => '#/components/schemas/Meta']]],
            'OrderData' => $order,
            'Order' => $data('OrderData'),
            'OrderList' => $list('OrderData'),
            'OrderPlaced' => ['type' => 'object', 'properties' => ['data' => ['type' => 'object', 'properties' => ['order' => ['$ref' => '#/components/schemas/OrderData'], 'invoice_id' => $int, 'paid' => ['type' => 'boolean']]]]],
            'ServiceData' => $service,
            'Service' => $data('ServiceData'),
            'ServiceList' => $list('ServiceData'),
            'ServiceStatus' => ['type' => 'object', 'properties' => ['data' => ['type' => 'object', 'properties' => ['live' => ['type' => 'boolean'], 'state' => $str, 'connection' => ['type' => 'object']]]]],
            'ActionList' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'key' => $str, 'label' => $str, 'mode' => ['type' => 'string', 'enum' => ['sync', 'reinstall', 'redirect']], 'dangerous' => ['type' => 'boolean'],
                'requires_confirmation' => ['type' => 'boolean'], 'confirmation_text' => $nullableStr, 'fields' => ['type' => 'array', 'items' => ['type' => 'object']],
            ]]]]],
            'ActionResult' => ['type' => 'object', 'properties' => ['data' => ['type' => 'object', 'properties' => ['message' => $str, 'redirect_url' => $nullableStr, 'service' => ['$ref' => '#/components/schemas/ServiceData']]]]],
            'CancellationResult' => ['type' => 'object', 'properties' => ['data' => ['type' => 'object', 'properties' => [
                'reference' => $str, 'received_at' => $date, 'ends_at' => $date, 'service' => ['$ref' => '#/components/schemas/ServiceData'],
            ]]]],
            'Binary' => ['type' => 'string', 'format' => 'binary', 'description' => 'application/pdf or application/xml'],
            'Credentials' => ['type' => 'object', 'properties' => ['data' => ['type' => 'object', 'additionalProperties' => ['type' => ['string', 'number', 'boolean', 'null']]]]],
            'InvoiceData' => $invoice,
            'Invoice' => $data('InvoiceData'),
            'InvoiceList' => $list('InvoiceData'),
            'PaymentStarted' => ['type' => 'object', 'properties' => ['data' => ['type' => 'object', 'properties' => [
                'status' => ['type' => 'string', 'enum' => ['paid', 'pending', 'redirect']], 'checkout_url' => $nullableStr, 'message' => $str,
            ]]]],
            'TicketData' => $ticket,
            'Ticket' => $data('TicketData'),
            'TicketList' => $list('TicketData'),
        ];
    }
}
