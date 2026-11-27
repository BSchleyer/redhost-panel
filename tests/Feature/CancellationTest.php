<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use DateTimeImmutable;
use Nebrix\Api\ApiScope;
use Nebrix\Api\ApiTokenService;
use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Contract\CancellationService;
use Nebrix\Http\Request;
use Nebrix\Scheduler\Scheduler;
use Nebrix\Service\ServiceRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\FeatureTestCase;

/**
 * Cancellation button (§ 312k BGB) and withdrawal function.
 */
final class CancellationTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clock()->freeze(new DateTimeImmutable('2026-06-10 12:00:00'));
    }

    private function service(User $user, string $due = '2026-07-01 00:00:00'): int
    {
        return $this->app->container()->get(ServiceRepository::class)->create([
            'user_id' => $user->id, 'category' => 'dedicated', 'name' => 'Root server', 'status' => 'active',
            'billing_cycle' => 'monthly', 'recurring_amount' => 500, 'tax_rate_bp' => 1900, 'currency' => 'EUR', 'next_due_at' => $due, 'config' => [],
        ]);
    }

    public function testPagesArePublicLinkedAndAvailableDuringMaintenance(): void
    {
        $page = $this->get('/cancel-contract');
        self::assertSame(200, $page->status());
        self::assertStringContainsString('Cancel now', $page->body());
        self::assertStringContainsString('Confirm withdrawal', $this->get('/withdraw-contract')->body());

        $login = $this->get('/login')->body();
        self::assertStringContainsString('href="/cancel-contract"', $login);
        self::assertStringContainsString('href="/withdraw-contract"', $login);

        $this->app->container()->get(SettingsRepository::class)->set('system.maintenance_mode', true);
        self::assertSame(200, $this->get('/cancel-contract')->status());
    }

    public function testGuestDeclarationIsConfirmedImmediatelyAndReviewedByStaff(): void
    {
        $customer = $this->createUser();
        $serviceId = $this->service($customer);

        $missing = $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Max Muster', 'email' => 'max@example.test']);
        self::assertTrue($missing->isRedirect(), 'The contract must be identified.');
        $noReason = $this->post('/cancel-contract', ['kind' => 'extraordinary', 'name' => 'Max', 'email' => 'max@example.test', 'contract' => 'Root server']);
        self::assertTrue($noReason->isRedirect(), 'An extraordinary cancellation needs a reason.');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_cancellations'));

        $response = $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Max Muster', 'email' => 'max@example.test', 'customer_reference' => $customer->email, 'contract' => 'Root server #' . $serviceId, 'effective' => 'earliest']);
        self::assertSame(200, $response->status());
        $declaration = (array) $this->db->fetchOne('SELECT * FROM nx_cancellations');
        self::assertStringContainsString((string) $declaration['reference'], $response->body());
        self::assertStringContainsString('2026-06-10 12:00:00 UTC', $response->body(), 'The receipt time is shown so the customer can save it.');
        self::assertSame('received', $declaration['status']);
        self::assertNull($declaration['service_id'], 'A guest cannot cancel a service directly.');
        self::assertNull($this->db->fetchValue('SELECT cancel_at FROM nx_services WHERE id = ?', [$serviceId]));
        $mail = $this->lastMail('cancellation_received');
        self::assertSame('max@example.test', $mail['to']);
        self::assertStringContainsString((string) $declaration['reference'], $mail['html']);

        // Staff assign the contract; another customer's service is refused.
        $admin = $this->createUser(Role::ADMIN);
        $this->actingAs($admin);
        self::assertStringContainsString('Root server', $this->get('/admin/cancellations/' . $declaration['id'])->body());
        $foreign = $this->service($this->createUser());
        $this->post('/admin/cancellations/' . $declaration['id'], ['action' => 'schedule', 'service_id' => (string) $foreign]);
        self::assertSame('received', $this->db->fetchValue('SELECT status FROM nx_cancellations WHERE id = ?', [$declaration['id']]));
        $this->post('/admin/cancellations/' . $declaration['id'], ['action' => 'schedule', 'service_id' => (string) $serviceId]);
        self::assertSame('2026-07-01 00:00:00', $this->db->fetchValue('SELECT cancel_at FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertSame('scheduled', $this->db->fetchValue('SELECT status FROM nx_cancellations WHERE id = ?', [$declaration['id']]));
        self::assertTrue($this->mailQueued('cancellation_confirmed'));
    }

    public function testSignedInCustomerCancelsAtTheEndOfTheTermAndCanTakeItBack(): void
    {
        $customer = $this->createUser();
        $serviceId = $this->service($customer);
        $scheduler = $this->app->container()->get(Scheduler::class);
        $this->clock()->freeze(new DateTimeImmutable('2026-06-26 12:00:00'));
        $scheduler->createRenewalInvoices();
        self::assertSame('unpaid', $this->db->fetchValue("SELECT status FROM nx_invoices WHERE service_id = ? AND type = 'renewal'", [$serviceId]));

        $this->actingAs($customer);
        $form = $this->get('/cancel-contract?service=' . $serviceId)->body();
        self::assertStringContainsString('Root server (#' . $serviceId . ')', $form);
        $response = $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Test Customer', 'email' => $customer->email, 'service_id' => (string) $serviceId, 'effective' => 'earliest']);
        self::assertStringContainsString('2026-07-01', $response->body());
        self::assertSame('2026-07-01 00:00:00', $this->db->fetchValue('SELECT cancel_at FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertSame('cancelled', $this->db->fetchValue("SELECT status FROM nx_invoices WHERE service_id = ? AND type = 'renewal'", [$serviceId]), 'The renewal for the next term is void.');
        $scheduler->createRenewalInvoices();
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_invoices WHERE service_id = ? AND type = 'renewal'", [$serviceId]));
        self::assertStringContainsString('ends on', $this->get('/services/' . $serviceId)->body());

        // Taken back before the end date: renewed as usual.
        $this->post('/services/' . $serviceId . '/cancellation/withdraw');
        self::assertNull($this->db->fetchValue('SELECT cancel_at FROM nx_services WHERE id = ?', [$serviceId]));
        self::assertSame('withdrawn', $this->db->fetchValue('SELECT status FROM nx_cancellations ORDER BY id DESC LIMIT 1'));
        $scheduler->createRenewalInvoices();
        self::assertSame(2, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_invoices WHERE service_id = ? AND type = 'renewal'", [$serviceId]));

        // A later end date is kept; the service is terminated when it is reached.
        $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Test Customer', 'email' => $customer->email, 'service_id' => (string) $serviceId, 'effective' => 'date', 'requested_date' => '2026-09-15']);
        self::assertSame('2026-09-15 00:00:00', $this->db->fetchValue('SELECT cancel_at FROM nx_services WHERE id = ?', [$serviceId]));
        $cancellations = $this->app->container()->get(CancellationService::class);
        self::assertSame(0, $cancellations->executeDue());
        $this->clock()->freeze(new DateTimeImmutable('2026-09-15 00:30:00'));
        self::assertSame(1, $cancellations->executeDue());
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'service.terminate'"));
        self::assertSame('completed', $this->db->fetchValue('SELECT status FROM nx_cancellations ORDER BY id DESC LIMIT 1'));
    }

    public function testCustomersCannotCancelOtherCustomersServices(): void
    {
        $foreign = $this->service($this->createUser());
        $customer = $this->createUser();
        $this->actingAs($customer);

        $response = $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Mallory', 'email' => $customer->email, 'service_id' => (string) $foreign]);
        self::assertTrue($response->isRedirect());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_cancellations'));
        self::assertSame(404, $this->post('/services/' . $foreign . '/cancellation/withdraw')->status());
    }

    public function testWithdrawalAndExtraordinaryCancellationsAreReviewedByStaff(): void
    {
        $customer = $this->createUser();
        $serviceId = $this->service($customer);
        $this->actingAs($customer);

        $this->post('/withdraw-contract', ['name' => 'Test Customer', 'email' => $customer->email, 'service_id' => (string) $serviceId, 'reason' => 'Changed my mind.']);
        $this->post('/cancel-contract', ['kind' => 'extraordinary', 'name' => 'Test Customer', 'email' => $customer->email, 'service_id' => (string) $serviceId, 'reason' => 'Repeated outages.']);
        self::assertSame(['received', 'received'], array_column($this->db->fetchAll('SELECT status FROM nx_cancellations ORDER BY id'), 'status'));
        self::assertSame(['withdrawal', 'extraordinary'], array_column($this->db->fetchAll('SELECT kind FROM nx_cancellations ORDER BY id'), 'kind'));
        self::assertNull($this->db->fetchValue('SELECT cancel_at FROM nx_services WHERE id = ?', [$serviceId]));

        $this->actingAs($this->createUser(Role::ADMIN));
        $id = (int) $this->db->fetchValue("SELECT id FROM nx_cancellations WHERE kind = 'extraordinary'");
        $this->post('/admin/cancellations/' . $id, ['action' => 'reject', 'note' => 'No outages were recorded.']);
        self::assertSame('rejected', $this->db->fetchValue('SELECT status FROM nx_cancellations WHERE id = ?', [$id]));
        self::assertStringContainsString('No outages were recorded.', $this->lastMail('cancellation_rejected')['html']);

        // Support cannot process declarations (services.manage is required).
        $this->actingAs($this->createUser(Role::FINANCE));
        $withdrawal = (int) $this->db->fetchValue("SELECT id FROM nx_cancellations WHERE kind = 'withdrawal'");
        self::assertSame(403, $this->post('/admin/cancellations/' . $withdrawal, ['action' => 'complete'])->status());
    }

    public function testConfirmationIsInTheCustomersLanguage(): void
    {
        $customer = $this->createUser(Role::CUSTOMER, ['language' => 'de']);
        $serviceId = $this->service($customer);
        $this->actingAs($customer);
        $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Erika', 'email' => $customer->email, 'service_id' => (string) $serviceId]);

        $mail = $this->lastMail('cancellation_received');
        self::assertStringStartsWith('Eingangsbestätigung: Ordentliche Kündigung', $mail['subject']);
    }

    public function testApiCancellation(): void
    {
        $this->app->container()->get(SettingsRepository::class)->set('api.enabled', true);
        $customer = $this->createUser();
        $serviceId = $this->service($customer);
        $token = $this->app->container()->get(ApiTokenService::class)->create($customer, 'Test', [ApiScope::SERVICES_MANAGE], 30, [], null)['secret'];
        $call = fn (string $path, array $body = []) => $this->app->handle(Request::create('POST', $path, [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'], (string) json_encode($body)));

        $created = $call('/api/v1/services/' . $serviceId . '/cancel', ['reason' => 'Moving to another plan']);
        self::assertSame(201, $created->status(), $created->body());
        self::assertSame('2026-07-01T00:00:00Z', json_decode($created->body(), true)['data']['ends_at']);
        self::assertSame(409, $call('/api/v1/services/' . $serviceId . '/cancel')->status());
        self::assertSame(200, $call('/api/v1/services/' . $serviceId . '/cancel/withdraw')->status());
        self::assertNull($this->db->fetchValue('SELECT cancel_at FROM nx_services WHERE id = ?', [$serviceId]));
    }

    public function testRejectedOrReplacedDeclarationsNeverEndTheService(): void
    {
        $customer = $this->createUser();
        $serviceId = $this->service($customer);
        $cancellations = $this->app->container()->get(CancellationService::class);
        $services = $this->app->container()->get(ServiceRepository::class);

        // A guest declaration scheduled by staff: the customer cannot take it back, staff can reject it.
        $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Max', 'email' => 'max@example.test', 'contract' => 'Root server']);
        $declaration = (array) $this->db->fetchOne('SELECT * FROM nx_cancellations ORDER BY id DESC LIMIT 1');
        $admin = $this->createUser(Role::ADMIN);
        $this->actingAs($admin);
        $this->post('/admin/cancellations/' . $declaration['id'], ['action' => 'schedule', 'service_id' => (string) $serviceId, 'effective_date' => '2026-06-01']);
        self::assertSame('received', $this->db->fetchValue('SELECT status FROM nx_cancellations WHERE id = ?', [$declaration['id']]), 'An end date in the past is refused.');
        $this->post('/admin/cancellations/' . $declaration['id'], ['action' => 'schedule', 'service_id' => (string) $serviceId]);
        self::assertFalse($cancellations->canWithdraw($services->find($serviceId) ?? throw new \RuntimeException()));
        $this->actingAs($customer);
        $this->post('/services/' . $serviceId . '/cancellation/withdraw');
        self::assertSame('2026-07-01 00:00:00', $this->db->fetchValue('SELECT cancel_at FROM nx_services WHERE id = ?', [$serviceId]));

        $this->actingAs($admin);
        $this->post('/admin/cancellations/' . $declaration['id'], ['action' => 'complete', 'note' => 'done']);
        self::assertSame('scheduled', $this->db->fetchValue('SELECT status FROM nx_cancellations WHERE id = ?', [$declaration['id']]), 'Scheduled declarations complete automatically.');
        $this->post('/admin/cancellations/' . $declaration['id'], ['action' => 'reject', 'note' => 'Minimum term not reached.']);
        self::assertSame('rejected', $this->db->fetchValue('SELECT status FROM nx_cancellations WHERE id = ?', [$declaration['id']]));
        self::assertNull($this->db->fetchValue('SELECT cancel_at FROM nx_services WHERE id = ?', [$serviceId]), 'Rejecting removes the end date.');

        // Even a stale end date of a rejected declaration never terminates the service.
        $this->db->update('nx_services', ['cancel_at' => '2026-06-01 00:00:00', 'cancellation_id' => $declaration['id']], ['id' => $serviceId]);
        self::assertSame(0, $cancellations->executeDue());
        self::assertSame('active', $this->db->fetchValue('SELECT status FROM nx_services WHERE id = ?', [$serviceId]));
        $this->db->update('nx_services', ['cancel_at' => null, 'cancellation_id' => null], ['id' => $serviceId]);

        // The customer's own cancellation; an already cancelled service is not offered again.
        $this->actingAs($customer);
        $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Max', 'email' => $customer->email, 'service_id' => (string) $serviceId, 'effective' => 'earliest']);
        $own = (int) $this->db->fetchValue('SELECT cancellation_id FROM nx_services WHERE id = ?', [$serviceId]);
        self::assertTrue($cancellations->canWithdraw($services->find($serviceId) ?? throw new \RuntimeException()));
        $count = (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_cancellations');
        $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Max', 'email' => $customer->email, 'service_id' => (string) $serviceId, 'effective' => 'earliest']);
        self::assertSame($count, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_cancellations'));

        // A later declaration that staff schedule replaces the earlier one.
        $this->post('/cancel-contract', ['kind' => 'extraordinary', 'name' => 'Max', 'email' => $customer->email, 'contract' => 'Root server', 'reason' => 'Moving abroad']);
        $extraordinary = (int) $this->db->fetchValue('SELECT MAX(id) FROM nx_cancellations');
        $this->actingAs($admin);
        $this->post('/admin/cancellations/' . $extraordinary, ['action' => 'schedule', 'service_id' => (string) $serviceId, 'effective_date' => '2026-06-15']);
        self::assertSame('completed', $this->db->fetchValue('SELECT status FROM nx_cancellations WHERE id = ?', [$own]));
        self::assertSame($extraordinary, (int) $this->db->fetchValue('SELECT cancellation_id FROM nx_services WHERE id = ?', [$serviceId]));
    }

    public function testOpenClaimsOfCancelledServicesAreNotCollectedAutomatically(): void
    {
        $customer = $this->createUser();
        $this->db->update('nx_users', ['credit_balance' => 10000], ['id' => $customer->id]);
        $serviceId = $this->service($customer, '2026-06-01 00:00:00');
        $this->db->update('nx_services', ['status' => 'suspended'], ['id' => $serviceId]);
        $this->db->update('nx_users', ['auto_pay_credit' => 0], ['id' => $customer->id]);
        $invoiceId = $this->app->container()->get(\Nebrix\Billing\InvoiceService::class)->createRenewal($this->app->container()->get(ServiceRepository::class)->find($serviceId) ?? throw new \RuntimeException());
        self::assertNotNull($invoiceId);
        $this->db->update('nx_invoices', ['due_at' => '2026-06-01 00:00:00'], ['id' => $invoiceId]);

        $this->actingAs($customer);
        $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Max', 'email' => $customer->email, 'service_id' => (string) $serviceId, 'effective' => 'earliest']);
        self::assertSame('2026-06-10 12:00:00', $this->db->fetchValue('SELECT cancel_at FROM nx_services WHERE id = ?', [$serviceId]));
        $this->db->update('nx_users', ['auto_pay_credit' => 1], ['id' => $customer->id]);

        self::assertSame(0, $this->app->container()->get(\Nebrix\Billing\AutoPayment::class)->run());
        self::assertSame(0, $this->app->container()->get(\Nebrix\Billing\PaymentReminders::class)->send());
        self::assertSame('unpaid', $this->db->fetchValue('SELECT status FROM nx_invoices WHERE id = ?', [$invoiceId]));
        self::assertSame(10000, (int) $this->db->fetchValue('SELECT credit_balance FROM nx_users WHERE id = ?', [$customer->id]));
    }

    public function testGuestsCannotFloodOneAddressWithReceipts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            self::assertSame(200, $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Max', 'email' => 'Victim@example.test', 'contract' => 'Contract ' . $i])->status());
        }
        self::assertSame(429, $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Max', 'email' => 'victim@example.test', 'contract' => 'Spam'])->status());
        self::assertSame(200, $this->post('/cancel-contract', ['kind' => 'ordinary', 'name' => 'Max', 'email' => 'other@example.test', 'contract' => 'Root server'])->status());
        self::assertSame(4, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_cancellations'));
    }

    public function testGuestsSeeTheFormsAndReceiptInTheirBrowserLanguage(): void
    {
        $this->serverOverrides['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,de;q=0.9,en;q=0.8';
        self::assertStringContainsString('Vertrag widerrufen', $this->get('/withdraw-contract')->body());
        $this->post('/withdraw-contract', ['name' => 'Max', 'email' => 'gast@example.test', 'contract' => 'Root server']);
        self::assertStringContainsString('Eingangsbestätigung', $this->lastMail('cancellation_received')['subject']);

        $this->serverOverrides['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9';
        self::assertStringContainsString('Withdraw from contract', $this->get('/withdraw-contract')->body(), 'Languages that are not enabled fall back to the default.');
    }
}
