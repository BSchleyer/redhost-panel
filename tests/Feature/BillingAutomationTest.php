<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use DateTimeImmutable;
use Nebrix\Auth\User;
use Nebrix\Billing\AutoPayment;
use Nebrix\Billing\CreditService;
use Nebrix\Billing\PaymentReminders;
use Nebrix\Scheduler\Scheduler;
use Nebrix\Service\ServiceRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\FeatureTestCase;

/**
 * Automatic renewal payment from account credit and payment reminders.
 */
final class BillingAutomationTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clock()->freeze(new DateTimeImmutable('2026-06-25 12:00:00'));
    }

    private function service(User $user, int $amount = 500): int
    {
        return $this->app->container()->get(ServiceRepository::class)->create([
            'user_id' => $user->id, 'category' => 'dedicated', 'name' => 'Root server', 'status' => 'active',
            'billing_cycle' => 'monthly', 'recurring_amount' => $amount, 'tax_rate_bp' => 1900, 'currency' => 'EUR',
            'next_due_at' => '2026-07-01 00:00:00', 'config' => [],
        ]);
    }

    private function renewalStatus(int $serviceId): string
    {
        return (string) $this->db->fetchValue("SELECT status FROM nx_invoices WHERE service_id = ? AND type = 'renewal'", [$serviceId]);
    }

    public function testRenewalIsPaidFromCreditAndExtendsTheTerm(): void
    {
        $customer = $this->createUser();
        $this->app->container()->get(CreditService::class)->adjust($customer->id, 2000, 'test');
        $serviceId = $this->service($customer);

        $this->app->container()->get(Scheduler::class)->createRenewalInvoices();
        self::assertSame('unpaid', $this->renewalStatus($serviceId), 'Not charged a week ahead of the due date.');

        $this->clock()->freeze(new DateTimeImmutable('2026-06-30 08:00:00'));
        self::assertSame(1, $this->app->container()->get(AutoPayment::class)->run());
        self::assertSame('paid', $this->renewalStatus($serviceId));
        self::assertSame(2000 - 595, $this->app->container()->get(CreditService::class)->balance($customer->id));
        self::assertSame('2026-08-01 00:00:00', $this->db->fetchValue('SELECT next_due_at FROM nx_services WHERE id = ?', [$serviceId]));

        // Running again does not charge twice.
        self::assertSame(0, $this->app->container()->get(AutoPayment::class)->run());
        self::assertSame(1405, $this->app->container()->get(CreditService::class)->balance($customer->id));
    }

    public function testOpenRenewalsArePaidOnceTheCreditSufficesUnlessDisabled(): void
    {
        $credit = $this->app->container()->get(CreditService::class);
        $autoPayment = $this->app->container()->get(AutoPayment::class);
        $scheduler = $this->app->container()->get(Scheduler::class);

        $poor = $this->createUser();
        $credit->adjust($poor->id, 100, 'test');
        $poorService = $this->service($poor);

        $optedOut = $this->createUser(attributes: ['auto_pay_credit' => 0]);
        $credit->adjust($optedOut->id, 5000, 'test');
        $optedOutService = $this->service($optedOut);

        $scheduler->createRenewalInvoices();
        self::assertSame('unpaid', $this->renewalStatus($poorService));
        self::assertSame('unpaid', $this->renewalStatus($optedOutService));

        // Credit topped up later: the run on the due date pays the renewal before the overdue check.
        $credit->adjust($poor->id, 1000, 'top-up');
        $this->clock()->freeze(new DateTimeImmutable('2026-07-01 00:30:00'));
        self::assertSame(1, $autoPayment->run());
        self::assertSame('paid', $this->renewalStatus($poorService));
        self::assertSame('unpaid', $this->renewalStatus($optedOutService), 'Customers can switch automatic payment off.');
        self::assertSame(5000, $credit->balance($optedOut->id));

        // Globally disabled.
        $other = $this->createUser();
        $credit->adjust($other->id, 5000, 'test');
        $this->app->container()->get(SettingsRepository::class)->set('billing.auto_pay_credit', false);
        $otherService = $this->service($other);
        $scheduler->createRenewalInvoices();
        self::assertSame('unpaid', $this->renewalStatus($otherService));
    }

    public function testProfileSwitchControlsAutomaticPayment(): void
    {
        $customer = $this->createUser();
        $this->actingAs($customer);
        $this->post('/account/profile', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'language' => 'en']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT auto_pay_credit FROM nx_users WHERE id = ?', [$customer->id]));
        $this->post('/account/profile', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'language' => 'en', 'auto_pay_credit' => '1']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT auto_pay_credit FROM nx_users WHERE id = ?', [$customer->id]));
    }

    public function testRemindersAreSentOncePerLevel(): void
    {
        $customer = $this->createUser();
        $serviceId = $this->service($customer);
        $this->app->container()->get(Scheduler::class)->createRenewalInvoices();
        $reminders = $this->app->container()->get(PaymentReminders::class);
        $count = fn (): int => (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_jobs WHERE type = 'mail.send' AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.template')) = 'invoice_reminder'");

        self::assertSame(0, $reminders->send(), 'Not yet: the first reminder is 3 days before the due date.');

        $this->clock()->freeze(new DateTimeImmutable('2026-06-28 12:00:00'));
        self::assertSame(1, $reminders->send());
        self::assertSame(0, $reminders->send());

        $this->clock()->freeze(new DateTimeImmutable('2026-07-02 12:00:00'));
        self::assertSame(1, $reminders->send());
        self::assertSame(0, $reminders->send());
        self::assertSame(2, $count());
        self::assertStringContainsString('Payment reminder', (string) $this->lastMail('invoice_reminder')['subject']);
        self::assertSame(2, (int) $this->db->fetchValue("SELECT reminders_sent FROM nx_invoices WHERE service_id = ? AND type = 'renewal'", [$serviceId]));

        // An invoice that is already overdue gets a single reminder, not one per missed level.
        $late = $this->createUser();
        $lateService = $this->service($late);
        $this->db->update('nx_services', ['next_due_at' => '2026-06-20 00:00:00'], ['id' => $lateService]);
        $this->app->container()->get(Scheduler::class)->createRenewalInvoices();
        self::assertSame(1, $reminders->send());
        self::assertSame(3, $count());

        // Paid invoices and disabled reminders send nothing.
        $this->app->container()->get(SettingsRepository::class)->set('billing.reminder_offsets', '');
        $this->clock()->freeze(new DateTimeImmutable('2026-07-20 12:00:00'));
        self::assertSame(0, $reminders->send());
    }
}
