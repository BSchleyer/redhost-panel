<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use Nebrix\Api\ApiScope;
use Nebrix\Api\ApiTokenService;
use Nebrix\Api\OpenApi;
use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Billing\CreditService;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Http\Router;
use Nebrix\Repository\ProductRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\FeatureTestCase;

/**
 * REST API: authentication, scopes, data isolation, idempotency, rate
 * limits, admin permissions and the documented route table.
 */
final class ApiTest extends FeatureTestCase
{
    private SettingsRepository $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = $this->app->container()->get(SettingsRepository::class);
        $this->settings->set('api.enabled', true);
    }

    /**
     * @param list<string> $scopes
     * @param list<string> $ips
     */
    private function token(User $user, array $scopes, array $ips = [], int $days = 30): string
    {
        return $this->app->container()->get(ApiTokenService::class)->create($user, 'Test', $scopes, $days, $ips, '127.0.0.1')['secret'];
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    private function api(string $method, string $uri, ?string $token, ?array $json = null, array $headers = []): Response
    {
        $server = ['REMOTE_ADDR' => $this->clientIp, 'HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $body = '';
        if ($json !== null) {
            $body = json_encode($json, JSON_THROW_ON_ERROR);
            $server['CONTENT_TYPE'] = 'application/json';
        }
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[$key === 'CONTENT_TYPE' ? $key : 'HTTP_' . $key] = $value;
        }

        return $this->app->handle(Request::create($method, $uri, [], $this->cookies, $server, $body));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        $decoded = json_decode($response->body(), true);
        self::assertIsArray($decoded, 'Response is not JSON: ' . $response->body());

        return $decoded;
    }

    private function errorCode(Response $response): string
    {
        return (string) ($this->body($response)['error']['code'] ?? '');
    }

    private function service(User $owner, string $name = 'Clan TS', string $status = 'active'): int
    {
        return $this->db->insert('nx_services', [
            'user_id' => $owner->id, 'category' => 'teamspeak', 'name' => $name, 'status' => $status, 'billing_cycle' => 'monthly',
            'recurring_amount' => 300, 'currency' => 'EUR', 'next_due_at' => '2026-12-01 00:00:00',
            'config' => json_encode(['slots' => 32, '_options' => ['server_name' => $name]]),
            'remote' => json_encode(['sid' => 7, 'port' => 9987]),
            'credentials' => $this->app->container()->get(\Nebrix\Security\Encrypter::class)->encryptArray(['token' => 'privilege-key'], 'service-credentials:0'),
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function product(array $overrides = []): int
    {
        return $this->app->container()->get(ProductRepository::class)->create($overrides + [
            'category' => 'teamspeak', 'name' => 'TS 32', 'slug' => 'ts-' . bin2hex(random_bytes(3)), 'price' => 500, 'setup_fee' => 0, 'currency' => 'EUR',
            'billing_cycle' => 'monthly', 'tax_rate_bp' => 1900, 'is_visible' => 1, 'is_active' => 1, 'auto_provision' => 0, 'provisioning_config' => ['slots' => 32],
        ]);
    }

    // ------------------------------------------------------------------

    public function testApiIsDisabledUntilEnabledAndNeverSetsASessionCookie(): void
    {
        $this->settings->set('api.enabled', false);
        $response = $this->api('GET', '/api/v1/me', null);
        self::assertSame(404, $response->status());
        self::assertSame('api_disabled', $this->errorCode($response));

        $this->settings->set('api.enabled', true);
        $customer = $this->createUser();
        $response = $this->api('GET', '/api/v1/me', $this->token($customer, [ApiScope::ACCOUNT_READ]));
        self::assertSame(200, $response->status());
        self::assertSame($customer->email, $this->body($response)['data']['email']);
        self::assertSame([], $response->cookies(), 'The API is stateless.');
        self::assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame('no-store, private', $response->header('Cache-Control'));
    }

    public function testAuthenticationFailures(): void
    {
        $customer = $this->createUser();
        $tokens = $this->app->container()->get(ApiTokenService::class);

        $missing = $this->api('GET', '/api/v1/me', null);
        self::assertSame(401, $missing->status());
        self::assertSame('Bearer realm="nebrix"', $missing->header('WWW-Authenticate'));
        self::assertSame(401, $this->api('GET', '/api/v1/me', 'nbx_' . str_repeat('0', 48))->status());
        self::assertSame(401, $this->api('GET', '/api/v1/me', 'not-a-token')->status());

        // Revoked.
        $secret = $this->token($customer, [ApiScope::ACCOUNT_READ]);
        $token = $tokens->authenticate($secret, $this->clientIp)[0] ?? null;
        self::assertNotNull($token);
        $tokens->revoke($token, $customer->id, null);
        self::assertSame(401, $this->api('GET', '/api/v1/me', $secret)->status());

        // Expired.
        $secret = $this->token($customer, [ApiScope::ACCOUNT_READ], [], 7);
        self::assertSame(200, $this->api('GET', '/api/v1/me', $secret)->status());
        $this->clock()->freeze($this->clock()->now()->modify('+8 days'));
        self::assertSame(401, $this->api('GET', '/api/v1/me', $secret)->status());
        $this->clock()->freeze(new \DateTimeImmutable('now'));

        // IP allowlist.
        $restricted = $this->token($customer, [ApiScope::ACCOUNT_READ], ['203.0.113.0/24']);
        self::assertSame(401, $this->api('GET', '/api/v1/me', $restricted)->status());
        $this->clientIp = '203.0.113.77';
        self::assertSame(200, $this->api('GET', '/api/v1/me', $restricted)->status());

        // Suspended owner.
        $this->db->update('nx_users', ['status' => 'suspended'], ['id' => $customer->id]);
        self::assertSame(401, $this->api('GET', '/api/v1/me', $restricted)->status());

        // The secret is never stored.
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_api_tokens WHERE token_hash = ? OR name = ?', [$restricted, $restricted]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_api_tokens WHERE token_hash = ?', [hash('sha256', $restricted)]));
    }

    public function testRepeatedFailuresBlockTheAddress(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->api('GET', '/api/v1/me', 'nbx_' . str_repeat('a', 48));
        }
        $blocked = $this->api('GET', '/api/v1/me', 'nbx_' . str_repeat('b', 48));
        self::assertSame(429, $blocked->status());
        self::assertNotNull($blocked->header('Retry-After'));

        // Clients with a valid token behind the same address keep working.
        $valid = $this->token($this->createUser(), [ApiScope::ACCOUNT_READ]);
        self::assertSame(200, $this->api('GET', '/api/v1/me', $valid)->status());
    }

    public function testSessionCookieAndCsrfPlayNoRoleInTheApi(): void
    {
        $customer = $this->createUser();
        $this->actingAs($customer);
        self::assertSame(401, $this->api('GET', '/api/v1/me', null)->status(), 'A browser session does not authenticate API requests.');

        $this->cookies = [];
        $response = $this->api('POST', '/api/v1/tickets', $this->token($customer, [ApiScope::TICKETS_WRITE]), ['subject' => 'API ticket', 'message' => 'Created without any CSRF token.']);
        self::assertSame(201, $response->status());
    }

    public function testScopesAreEnforced(): void
    {
        $customer = $this->createUser();
        $token = $this->token($customer, [ApiScope::SERVICES_READ]);
        $id = $this->service($customer);

        self::assertSame(200, $this->api('GET', '/api/v1/services/' . $id, $token)->status());
        $denied = $this->api('GET', '/api/v1/me', $token);
        self::assertSame(403, $denied->status());
        self::assertSame('insufficient_scope', $this->errorCode($denied));
        self::assertSame(403, $this->api('GET', '/api/v1/services/' . $id . '/credentials', $token)->status());
    }

    public function testCustomersOnlySeeTheirOwnResources(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        $bobService = $this->service($bob, 'Bob TS');
        $orders = $this->app->container()->get(\Nebrix\Billing\OrderService::class);
        $bobOrder = $orders->place($bob, $this->product(), ['server_name' => 'Bob'], '127.0.0.1');
        $bobTicket = $this->app->container()->get(\Nebrix\Support\TicketRepository::class)->create($bob->id, 'Bob', 'Bob needs help please.', null, 'normal');

        $token = $this->token($alice, array_keys(ApiScope::customer()));
        foreach (['/api/v1/services/' . $bobService, '/api/v1/services/' . $bobService . '/status', '/api/v1/services/' . $bobService . '/credentials',
            '/api/v1/orders/' . $bobOrder['order_id'], '/api/v1/invoices/' . $bobOrder['invoice_id'], '/api/v1/tickets/' . $bobTicket] as $uri) {
            self::assertSame(404, $this->api('GET', $uri, $token)->status(), $uri);
        }
        self::assertSame(404, $this->api('POST', '/api/v1/invoices/' . $bobOrder['invoice_id'] . '/pay', $token, ['gateway' => 'credit'])->status());
        self::assertSame(404, $this->api('POST', '/api/v1/tickets/' . $bobTicket . '/replies', $token, ['message' => 'Hijack'])->status());

        foreach (['/api/v1/services', '/api/v1/orders', '/api/v1/invoices', '/api/v1/tickets'] as $uri) {
            $list = $this->body($this->api('GET', $uri, $token));
            self::assertSame(0, $list['meta']['total'], $uri);
        }
    }

    public function testServiceRepresentationHidesInternalState(): void
    {
        $customer = $this->createUser();
        $id = $this->service($customer);
        $token = $this->token($customer, [ApiScope::SERVICES_READ, ApiScope::SERVICES_CREDENTIALS]);

        $raw = $this->api('GET', '/api/v1/services/' . $id, $token)->body();
        $service = json_decode($raw, true)['data'];
        self::assertSame(['server_name' => 'Clan TS'], $service['options']);
        self::assertSame('2026-12-01T00:00:00Z', $service['next_due_at']);
        foreach (['"remote"', '"config"', '"credentials"', '"sid"', '9987', 'privilege-key'] as $hidden) {
            self::assertStringNotContainsString($hidden, $raw);
        }

        $list = $this->body($this->api('GET', '/api/v1/services?per_page=1', $token));
        self::assertSame(['page' => 1, 'per_page' => 1, 'total' => 1, 'last_page' => 1], $list['meta']);

        // Manual services have no actions.
        $action = $this->api('POST', '/api/v1/services/' . $id . '/actions/start', $this->token($customer, [ApiScope::SERVICES_MANAGE]));
        self::assertSame(403, $action->status());
    }

    public function testCredentialsAreAuditLogged(): void
    {
        $customer = $this->createUser();
        $id = $this->db->insert('nx_services', [
            'user_id' => $customer->id, 'category' => 'teamspeak', 'name' => 'TS', 'status' => 'active', 'billing_cycle' => 'monthly', 'recurring_amount' => 300,
            'currency' => 'EUR', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);
        $this->app->container()->get(\Nebrix\Service\ServiceRepository::class)->setCredentials($id, ['privilege_key' => 'abc123']);

        $response = $this->api('GET', '/api/v1/services/' . $id . '/credentials', $this->token($customer, [ApiScope::SERVICES_CREDENTIALS]));

        self::assertSame(['privilege_key' => 'abc123'], $this->body($response)['data']);
        $audit = $this->db->fetchOne("SELECT * FROM nx_audit_log WHERE action = 'service.credentials_viewed' AND target_id = ?", [(string) $id]);
        self::assertNotNull($audit);
        self::assertSame('api', json_decode((string) $audit['details'], true)['via']);
    }

    public function testOrderPaymentAndIdempotency(): void
    {
        $customer = $this->createUser();
        $this->app->container()->get(CreditService::class)->adjust($customer->id, 2000, 'test');
        $token = $this->token($customer, [ApiScope::PRODUCTS_READ, ApiScope::ORDERS_CREATE, ApiScope::ORDERS_READ, ApiScope::INVOICES_READ, ApiScope::INVOICES_PAY]);
        $productId = $this->product();
        $this->product(['is_visible' => 0, 'slug' => 'hidden-product']);

        $products = $this->body($this->api('GET', '/api/v1/products', $token));
        self::assertSame([$productId], array_column($products['data'], 'id'), 'Hidden products are not listed.');
        self::assertSame(595, $products['data'][0]['pricing']['first_payment_total']);
        self::assertSame('server_name', $products['data'][0]['order_options'][0]['name']);

        $rejected = $this->api('POST', '/api/v1/orders', $token, ['product_id' => $productId, 'accept_terms' => false, 'options' => ['server_name' => 'Clan']]);
        self::assertSame(422, $rejected->status());
        $invalid = $this->api('POST', '/api/v1/orders', $token, ['product_id' => $productId, 'accept_terms' => true, 'options' => ['server_name' => '<script>']]);
        self::assertSame('validation_failed', $this->errorCode($invalid));
        self::assertArrayHasKey('server_name', $this->body($invalid)['error']['fields']);

        $body = ['product_id' => $productId, 'accept_terms' => true, 'options' => ['server_name' => 'Clan']];
        $first = $this->api('POST', '/api/v1/orders', $token, $body, ['Idempotency-Key' => 'order-0001-abc']);
        self::assertSame(201, $first->status());
        $replay = $this->api('POST', '/api/v1/orders', $token, $body, ['Idempotency-Key' => 'order-0001-abc']);
        self::assertSame(201, $replay->status());
        self::assertSame('true', $replay->header('Idempotent-Replayed'));
        self::assertSame($first->body(), $replay->body());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_orders WHERE user_id = ?', [$customer->id]), 'A retried request does not order twice.');
        $reused = $this->api('POST', '/api/v1/orders', $token, ['options' => ['server_name' => 'Other']] + $body, ['Idempotency-Key' => 'order-0001-abc']);
        self::assertSame('idempotency_key_reused', $this->errorCode($reused));

        $invoiceId = (int) $this->body($first)['data']['invoice_id'];
        $invoice = $this->body($this->api('GET', '/api/v1/invoices/' . $invoiceId, $token))['data'];
        self::assertSame('unpaid', $invoice['status']);
        self::assertContains('credit', $invoice['payment_methods']);

        $paid = $this->api('POST', '/api/v1/invoices/' . $invoiceId . '/pay', $token, ['gateway' => 'credit']);
        self::assertSame('paid', $this->body($paid)['data']['status']);
        self::assertSame('paid', $this->body($this->api('GET', '/api/v1/invoices/' . $invoiceId, $token))['data']['status']);
        self::assertSame(409, $this->api('POST', '/api/v1/invoices/' . $invoiceId . '/pay', $token, ['gateway' => 'credit'])->status());

        $orders = $this->body($this->api('GET', '/api/v1/orders', $token));
        self::assertSame('paid', $orders['data'][0]['status']);
    }

    public function testTickets(): void
    {
        $customer = $this->createUser();
        $token = $this->token($customer, [ApiScope::TICKETS_READ, ApiScope::TICKETS_WRITE]);

        $created = $this->api('POST', '/api/v1/tickets', $token, ['subject' => 'Help', 'message' => 'My server does not start.', 'priority' => 'high']);
        self::assertSame(201, $created->status());
        $ticketId = (int) $this->body($created)['data']['id'];
        self::assertSame(201, $this->api('POST', '/api/v1/tickets/' . $ticketId . '/replies', $token, ['message' => 'Still broken.'])->status());

        $ticket = $this->body($this->api('GET', '/api/v1/tickets/' . $ticketId, $token))['data'];
        self::assertSame(['customer', 'customer'], array_column($ticket['messages'], 'author'));
        self::assertNull($ticket['messages'][0]['author_id'], 'Customers do not see account IDs of authors.');
        self::assertSame('closed', $this->body($this->api('POST', '/api/v1/tickets/' . $ticketId . '/close', $token))['data']['status']);

        $foreignService = $this->service($this->createUser());
        self::assertSame(422, $this->api('POST', '/api/v1/tickets', $token, ['subject' => 'Other', 'message' => 'Someone else service.', 'service_id' => $foreignService])->status());

        $this->settings->set('system.tickets_enabled', false);
        self::assertSame('tickets_disabled', $this->errorCode($this->api('GET', '/api/v1/tickets', $token)));
    }

    public function testRateLimitPerToken(): void
    {
        $this->settings->set('api.rate_limit', 10);
        $token = $this->token($this->createUser(), [ApiScope::ACCOUNT_READ]);
        for ($i = 1; $i <= 10; $i++) {
            $response = $this->api('GET', '/api/v1/me', $token);
            self::assertSame(200, $response->status());
            self::assertSame((string) (10 - $i), $response->header('X-RateLimit-Remaining'));
        }
        $limited = $this->api('GET', '/api/v1/me', $token);
        self::assertSame(429, $limited->status());
        self::assertSame('rate_limited', $this->errorCode($limited));
        self::assertNotNull($limited->header('Retry-After'));
    }

    public function testRequestFormatErrorsAreJson(): void
    {
        $token = $this->token($this->createUser(), [ApiScope::TICKETS_WRITE]);

        self::assertSame('invalid_json', $this->errorCode($this->app->handle(Request::create('POST', '/api/v1/tickets', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'], '{"subject":'))));
        self::assertSame('invalid_json', $this->errorCode($this->app->handle(Request::create('POST', '/api/v1/tickets', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'], '[1,2]'))));
        self::assertSame(415, $this->app->handle(Request::create('POST', '/api/v1/tickets', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'text/plain'], 'hello'))->status());

        $notFound = $this->api('GET', '/api/v1/does-not-exist', $token);
        self::assertSame(404, $notFound->status());
        self::assertSame('not_found', $this->errorCode($notFound));
        self::assertSame([], $notFound->cookies());
        self::assertSame('method_not_allowed', $this->errorCode($this->api('POST', '/api/v1/me', $token)));
        self::assertSame('invalid_idempotency_key', $this->errorCode($this->api('POST', '/api/v1/tickets', $token, ['subject' => 'abc', 'message' => 'message text'], ['Idempotency-Key' => 'x'])));
    }

    public function testAdminScopesFollowStaffPermissions(): void
    {
        $customer = $this->createUser();
        try {
            $this->token($customer, [ApiScope::ADMIN_SERVICES_READ]);
            self::fail('A customer obtained an admin scope.');
        } catch (\InvalidArgumentException) {
        }

        $support = $this->createUser(Role::SUPPORT);
        $token = $this->token($support, [ApiScope::ADMIN_SERVICES_READ, ApiScope::ADMIN_SERVICES_WRITE, ApiScope::ADMIN_CUSTOMERS_READ, ApiScope::ADMIN_TICKETS_READ, ApiScope::ADMIN_TICKETS_WRITE]);
        try {
            $this->token($this->createUser(Role::FINANCE), [ApiScope::ADMIN_TICKETS_WRITE]);
            self::fail('Finance obtained a scope without the tickets.reply permission.');
        } catch (\InvalidArgumentException) {
        }

        $serviceId = $this->service($customer);
        $list = $this->body($this->api('GET', '/api/v1/admin/services?user_id=' . $customer->id, $token));
        self::assertSame([$serviceId], array_column($list['data'], 'id'));
        self::assertSame($customer->id, $this->body($this->api('GET', '/api/v1/admin/customers/' . $customer->id, $token))['data']['id']);
        self::assertSame(404, $this->api('GET', '/api/v1/admin/customers/' . $support->id, $token)->status(), 'Staff accounts are not customers.');

        self::assertSame('confirmation_required', $this->errorCode($this->api('POST', '/api/v1/admin/services/' . $serviceId . '/terminate', $token, ['confirm' => 'yes'])));
        $suspended = $this->api('POST', '/api/v1/admin/services/' . $serviceId . '/suspend', $token, ['reason' => 'Abuse report', 'notify' => false]);
        self::assertSame(202, $suspended->status());
        self::assertSame('suspended', $this->body($suspended)['data']['status']);
        self::assertSame('Abuse report', $this->body($suspended)['data']['suspension_reason']);
        self::assertFalse($this->mailQueued('service_suspended'), 'notify: false suppresses the e-mail.');
        $customerView = $this->body($this->api('GET', '/api/v1/services/' . $serviceId, $this->token($customer, [ApiScope::SERVICES_READ])))['data'];
        self::assertArrayNotHasKey('suspension_reason', $customerView, 'Internal suspension notes are not shown to customers.');
        $second = $this->service($customer, 'Second TS');
        $this->api('POST', '/api/v1/admin/services/' . $second . '/suspend', $token);
        self::assertTrue($this->mailQueued('service_suspended'), 'Without "notify" the customer is e-mailed, as in the admin interface.');
        self::assertNotNull($this->db->fetchOne("SELECT id FROM nx_audit_log WHERE action = 'service.suspend_via_api'"));
        self::assertSame(409, $this->api('POST', '/api/v1/admin/services/' . $serviceId . '/suspend', $token)->status());
        self::assertSame(2, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'service.suspend'"));

        $ticket = $this->app->container()->get(\Nebrix\Support\TicketRepository::class)->create($customer->id, 'Help', 'Need help with my server.', null, 'normal');
        $reply = $this->api('POST', '/api/v1/admin/tickets/' . $ticket . '/replies', $token, ['message' => 'We are on it.']);
        self::assertSame(201, $reply->status());
        self::assertSame($support->id, $this->body($reply)['data']['messages'][1]['author_id']);
        self::assertTrue($this->mailQueued('ticket_reply'));

        // A customer token cannot reach admin routes.
        self::assertSame(403, $this->api('GET', '/api/v1/admin/services', $this->token($customer, array_keys(ApiScope::customer())))->status());

        // Losing the permission disables the scope immediately.
        $this->db->run("DELETE FROM nx_role_permissions WHERE role_id = ? AND permission = 'services.view'", [$support->roleId]);
        $this->app->container()->get(\Nebrix\Authorization\Gate::class)->flush();
        $denied = $this->api('GET', '/api/v1/admin/services', $token);
        self::assertSame(403, $denied->status());
        self::assertSame('forbidden', $this->errorCode($denied));

        // Enforced staff 2FA applies to admin scopes.
        $this->settings->set('auth.staff_require_2fa', true);
        self::assertSame('two_factor_required', $this->errorCode($this->api('GET', '/api/v1/admin/customers', $token)));
    }

    public function testCustomerTokensCanBeDisabledAndMaintenanceBlocksCustomers(): void
    {
        $customerToken = $this->token($this->createUser(), [ApiScope::ACCOUNT_READ]);
        $staffToken = $this->token($this->createUser(Role::ADMIN), [ApiScope::ACCOUNT_READ]);

        $this->settings->set('system.maintenance_mode', true);
        self::assertSame(503, $this->api('GET', '/api/v1/me', $customerToken)->status());
        self::assertSame(200, $this->api('GET', '/api/v1/me', $staffToken)->status());
        $this->settings->set('system.maintenance_mode', false);

        $this->settings->set('api.customer_tokens', false);
        self::assertSame('api_access_disabled', $this->errorCode($this->api('GET', '/api/v1/me', $customerToken)));
        self::assertSame(200, $this->api('GET', '/api/v1/me', $staffToken)->status());
    }

    public function testOpenApiDocumentMatchesTheRouteTable(): void
    {
        $documented = [];
        foreach (OpenApi::endpoints() as [$method, $path, $scope]) {
            $documented[$method . ' /api/v1' . $path] = $scope;
        }

        $routed = [];
        foreach ($this->app->container()->get(Router::class)->routes() as $route) {
            if (!$route->stateless || in_array($route->path, ['/api/v1', '/api/v1/openapi.json'], true)) {
                continue;
            }
            $path = (string) preg_replace('/\{([a-zA-Z_]\w*)(?::((?:[^{}]+|\{[^{}]*\})+))?\}/', '{$1}', $route->path);
            $scopes = array_values(array_filter($route->middleware, static fn (string $m): bool => str_starts_with($m, 'api.scope:')));
            self::assertCount(1, $scopes, $route->path . ' must declare exactly one scope.');
            self::assertContains('api.auth', $route->middleware, $route->path);
            $routed[$route->methods[0] . ' ' . $path] = substr($scopes[0], strlen('api.scope:'));
        }
        ksort($documented);
        ksort($routed);
        self::assertSame($documented, $routed);

        $response = $this->api('GET', '/api/v1/openapi.json', null);
        self::assertSame(200, $response->status());
        $document = $this->body($response);
        self::assertSame('3.1.0', $document['openapi']);
        self::assertArrayHasKey('/api/v1/services/{id}/actions/{action}', $document['paths']);
    }

    public function testTokenManagementInTheAccountArea(): void
    {
        $customer = $this->createUser();
        $other = $this->createUser();
        $this->actingAs($customer);

        $wrong = $this->post('/account/api-tokens', ['name' => 'CI', 'scopes' => [ApiScope::SERVICES_READ], 'days' => '30', 'password' => 'wrong']);
        self::assertTrue($wrong->isRedirect());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_api_tokens'));

        $forbidden = $this->post('/account/api-tokens', ['name' => 'CI', 'scopes' => [ApiScope::ADMIN_CUSTOMERS_READ], 'days' => '30', 'password' => 'Valid-Password-123']);
        self::assertTrue($forbidden->isRedirect());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_api_tokens'));

        $created = $this->post('/account/api-tokens', ['name' => 'CI', 'scopes' => [ApiScope::SERVICES_READ, ApiScope::ACCOUNT_READ], 'days' => '30', 'allowed_ips' => "198.51.100.20\n2001:db8::/48", 'password' => 'Valid-Password-123']);
        self::assertSame(200, $created->status());
        self::assertSame(1, preg_match('/nbx_[a-f0-9]{48}/', $created->body(), $match));
        self::assertSame(200, $this->api('GET', '/api/v1/me', $match[0])->status());
        $row = $this->db->fetchOne('SELECT * FROM nx_api_tokens WHERE user_id = ?', [$customer->id]);
        self::assertNotNull($row);
        self::assertSame(['198.51.100.20', '2001:db8::/48'], json_decode((string) $row['allowed_ips'], true));

        // The token is not shown again.
        self::assertStringNotContainsString($match[0], $this->get('/account/api-tokens')->body());

        // Only own tokens can be revoked.
        $this->actingAs($other);
        self::assertSame(404, $this->post('/account/api-tokens/' . $row['id'] . '/revoke')->status());
        $this->actingAs($customer);
        $this->post('/account/api-tokens/' . $row['id'] . '/revoke');
        self::assertSame(401, $this->api('GET', '/api/v1/me', $match[0])->status());
    }

    public function testAdminTokenOverviewAndRevocation(): void
    {
        $customer = $this->createUser();
        $secret = $this->token($customer, [ApiScope::ACCOUNT_READ]);

        $this->actingAs($this->createUser(Role::SUPPORT));
        self::assertSame(403, $this->get('/admin/api-tokens')->status());

        $this->actingAs($this->createUser(Role::ADMIN));
        $page = $this->get('/admin/api-tokens');
        self::assertSame(200, $page->status());
        self::assertStringContainsString($customer->email, $page->body());
        self::assertStringNotContainsString($secret, $page->body());

        $this->post('/admin/api-tokens/user/' . $customer->id . '/revoke');
        self::assertSame(401, $this->api('GET', '/api/v1/me', $secret)->status());
        self::assertNotNull($this->db->fetchOne("SELECT id FROM nx_audit_log WHERE action = 'api_token.revoked_all'"));
    }

    public function testClosingAnAccountRevokesItsTokens(): void
    {
        $customer = $this->createUser();
        $secret = $this->token($customer, [ApiScope::ACCOUNT_READ]);
        $admin = $this->createUser(Role::ADMIN);

        $this->app->container()->get(\Nebrix\Customer\CustomerService::class)->closeAndAnonymize($customer, $admin->id, null);

        self::assertSame(0, $this->app->container()->get(ApiTokenService::class)->countActive($customer->id));
        self::assertSame(401, $this->api('GET', '/api/v1/me', $secret)->status());
        $row = $this->db->fetchOne('SELECT * FROM nx_api_tokens WHERE user_id = ?', [$customer->id]);
        self::assertNotNull($row);
        self::assertSame(['closed account', null, null], [$row['name'], $row['created_ip'], $row['last_used_ip']], 'Personal data of closed accounts is removed.');
    }

    public function testWriteScopesAlsoNeedTheViewPermission(): void
    {
        $support = $this->createUser(Role::SUPPORT);
        $this->db->run("DELETE FROM nx_role_permissions WHERE role_id = ? AND permission IN ('services.view', 'tickets.view')", [$support->roleId]);
        $this->app->container()->get(\Nebrix\Authorization\Gate::class)->flush();

        foreach ([ApiScope::ADMIN_SERVICES_WRITE, ApiScope::ADMIN_TICKETS_WRITE] as $scope) {
            try {
                $this->token($support, [$scope]);
                self::fail('Write scope without view permission: ' . $scope);
            } catch (\InvalidArgumentException) {
            }
        }
    }

    public function testIdempotentReplaysRespectScopeChecksAndAbandonedKeys(): void
    {
        $customer = $this->createUser();
        $support = $this->createUser(Role::SUPPORT);
        $token = $this->token($support, [ApiScope::ADMIN_TICKETS_WRITE]);
        $ticket = $this->app->container()->get(\Nebrix\Support\TicketRepository::class)->create($customer->id, 'Help', 'Need help with my server.', null, 'normal');

        self::assertSame(201, $this->api('POST', '/api/v1/admin/tickets/' . $ticket . '/replies', $token, ['message' => 'On it.'], ['Idempotency-Key' => 'reply-0001'])->status());
        $this->db->run("DELETE FROM nx_role_permissions WHERE role_id = ? AND permission = 'tickets.view'", [$support->roleId]);
        $this->app->container()->get(\Nebrix\Authorization\Gate::class)->flush();
        $replay = $this->api('POST', '/api/v1/admin/tickets/' . $ticket . '/replies', $token, ['message' => 'On it.'], ['Idempotency-Key' => 'reply-0001']);
        self::assertSame(403, $replay->status(), 'A stored response is not replayed after the permission was removed.');

        // A request that never finished (no response stored) frees its key after 5 minutes.
        $tickets = $this->token($customer, [ApiScope::TICKETS_WRITE]);
        $tokenId = (int) $this->db->fetchValue('SELECT id FROM nx_api_tokens WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$customer->id]);
        $this->db->insert('nx_api_idempotency', ['token_id' => $tokenId, 'key_hash' => hash('sha256', 'ticket-0001'), 'request_hash' => str_repeat('0', 64), 'created_at' => '2026-01-01 00:00:00']);
        $retried = $this->api('POST', '/api/v1/tickets', $tickets, ['subject' => 'Retry', 'message' => 'Created after a crash.'], ['Idempotency-Key' => 'ticket-0001']);
        self::assertSame(201, $retried->status());
    }

    public function testAccountRecoveryRevokesTokensAndCreationIsNotified(): void
    {
        $customer = $this->createUser();
        $first = $this->token($customer, [ApiScope::ACCOUNT_READ]);
        self::assertTrue($this->mailQueued('api_token_created'));

        // Password change (or reset) revokes all tokens.
        $this->actingAs($customer);
        $this->post('/account/password', ['current_password' => 'Valid-Password-123', 'password' => 'Another-Strong-Pass-77', 'password_confirmation' => 'Another-Strong-Pass-77']);
        self::assertSame(401, $this->api('GET', '/api/v1/me', $first)->status());

        // Staff "sign out everywhere" and 2FA reset revoke them as well.
        $customer = $this->app->container()->get(\Nebrix\Repository\UserRepository::class)->find($customer->id);
        self::assertNotNull($customer);
        $admin = $this->createUser(Role::ADMIN);
        $customers = $this->app->container()->get(\Nebrix\Customer\CustomerService::class);
        $second = $this->token($customer, [ApiScope::ACCOUNT_READ]);
        $customers->revokeSessions($customer, $admin->id, null);
        self::assertSame(401, $this->api('GET', '/api/v1/me', $second)->status());
        $third = $this->token($customer, [ApiScope::ACCOUNT_READ]);
        $customers->resetTwoFactor($customer, $admin->id, null);
        self::assertSame(401, $this->api('GET', '/api/v1/me', $third)->status());
    }

    public function testRateLimitIsSharedByAllTokensOfAUser(): void
    {
        $this->settings->set('api.rate_limit', 10);
        $customer = $this->createUser();
        $a = $this->token($customer, [ApiScope::ACCOUNT_READ]);
        $b = $this->token($customer, [ApiScope::ACCOUNT_READ]);
        for ($i = 0; $i < 5; $i++) {
            $this->api('GET', '/api/v1/me', $a);
            $this->api('GET', '/api/v1/me', $b);
        }
        self::assertSame(429, $this->api('GET', '/api/v1/me', $a)->status());
        self::assertSame(200, $this->api('GET', '/api/v1/me', $this->token($this->createUser(), [ApiScope::ACCOUNT_READ]))->status());
    }

    public function testDisabledCustomerLoginsAlsoBlockCustomerTokens(): void
    {
        $token = $this->token($this->createUser(), [ApiScope::ACCOUNT_READ]);
        $this->settings->set('auth.login_enabled', false);
        self::assertSame('api_access_disabled', $this->errorCode($this->api('GET', '/api/v1/me', $token)));
    }

    public function testMalformedInputIsAValidationErrorNotAServerError(): void
    {
        $token = $this->token($this->createUser(), [ApiScope::TICKETS_WRITE]);
        foreach ([['subject' => ['x'], 'message' => 'Long enough message.'], ['subject' => 'Help me', 'message' => 'Long enough message.', 'priority' => ['high']]] as $body) {
            $response = $this->api('POST', '/api/v1/tickets', $token, $body);
            self::assertSame(422, $response->status(), $response->body());
            self::assertSame('validation_failed', $this->errorCode($response));
        }
    }

    public function testOnlySuperAdministratorsRevokeSuperAdministratorTokens(): void
    {
        $owner = $this->createUser(Role::SUPER_ADMIN);
        $secret = $this->token($owner, [ApiScope::ACCOUNT_READ]);
        $tokenId = (int) $this->db->fetchValue('SELECT id FROM nx_api_tokens WHERE user_id = ?', [$owner->id]);

        $this->actingAs($this->createUser(Role::ADMIN));
        self::assertSame(403, $this->post('/admin/api-tokens/' . $tokenId . '/revoke')->status());
        self::assertSame(403, $this->post('/admin/api-tokens/user/' . $owner->id . '/revoke')->status());
        self::assertSame(200, $this->api('GET', '/api/v1/me', $secret)->status());

        $this->actingAs($this->createUser(Role::SUPER_ADMIN));
        $this->post('/admin/api-tokens/' . $tokenId . '/revoke');
        self::assertSame(401, $this->api('GET', '/api/v1/me', $secret)->status());
    }
}
