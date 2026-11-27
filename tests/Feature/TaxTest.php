<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use DateTimeImmutable;
use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Billing\InvoiceService;
use Nebrix\Billing\OrderService;
use Nebrix\Billing\Tax\TaxDecision;
use Nebrix\Billing\Tax\TaxService;
use Nebrix\Billing\Tax\VatIdValidator;
use Nebrix\Repository\ProductRepository;
use Nebrix\Repository\UserRepository;
use Nebrix\Service\ServiceRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\FeatureTestCase;
use Nebrix\Tests\Support\FakeVatIdValidator;

/**
 * EU tax rules: reverse charge, OSS, small business, third countries.
 */
final class TaxTest extends FeatureTestCase
{
    private SettingsRepository $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = $this->app->container()->get(SettingsRepository::class);
    }

    private function customer(string $country, ?string $vatId = null, bool $verify = true): User
    {
        $user = $this->createUser(Role::CUSTOMER, ['country' => $country, 'vat_id' => $vatId, 'company' => $vatId !== null ? 'Example Trading GmbH' : null]);
        if ($vatId !== null && $verify) {
            $this->app->container()->get(TaxService::class)->verifyUser($user);
        }

        return $this->app->container()->get(UserRepository::class)->find($user->id) ?? $user;
    }

    private function product(): int
    {
        return $this->app->container()->get(ProductRepository::class)->create([
            'category' => 'dedicated', 'name' => 'Root S', 'slug' => 'root-' . bin2hex(random_bytes(3)), 'price' => 1000, 'setup_fee' => 0, 'currency' => 'EUR',
            'billing_cycle' => 'monthly', 'tax_rate_bp' => 1900, 'is_visible' => 1, 'is_active' => 1, 'auto_provision' => 0, 'provisioning_config' => [],
        ]);
    }

    public function testDecisions(): void
    {
        $taxes = $this->app->container()->get(TaxService::class);
        $decide = fn (User $user): array => [($d = $taxes->decide($user))->category, $d->rate(1900)];

        self::assertSame(['S', 1900], $decide($this->customer('DE')));
        self::assertSame(['S', 1900], $decide($this->customer('DE', 'DE123456789')), 'Domestic business customers pay VAT.');
        self::assertSame(['S', 1900], $decide($this->customer('AT')), 'Without OSS the domestic rate applies.');
        self::assertSame(['AE', 0], $decide($this->customer('AT', 'ATU12345678')));
        self::assertSame(['S', 1900], $decide($this->customer('AT', 'ATU99999999')), 'An invalid VAT ID is no reverse charge.');
        self::assertSame(['S', 1900], $decide($this->customer('AT', 'FR12345678901')), 'The VAT ID must belong to the customer\'s country.');
        self::assertSame(['S', 1900], $decide($this->customer('AT', 'ATU12345678', false)), 'Unverified VAT IDs are no reverse charge.');

        $this->settings->set('tax.oss', true);
        self::assertSame(['S', 2000], $decide($this->customer('AT')));
        self::assertSame(['S', 2400], $decide($this->customer('EE')));
        $this->settings->set('tax.rate_overrides', "EE=2200\nfi=2550");
        self::assertSame(['S', 2200], $decide($this->customer('EE')));

        self::assertSame(['S', 1900], $decide($this->customer('CH')));
        $this->settings->set('tax.non_eu_exempt', true);
        self::assertSame(['O', 0], $decide($this->customer('CH')));

        $this->settings->set('tax.reverse_charge', false);
        self::assertSame(['S', 2000], $decide($this->customer('AT', 'ATU12345678')));

        $this->settings->set('tax.small_business', true);
        $decision = $taxes->decide($this->customer('DE'));
        self::assertSame([TaxDecision::EXEMPT, 0, 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.'], [$decision->category, $decision->rate(1900), $decision->note]);
    }

    public function testReverseChargeOrderInvoiceAndRenewal(): void
    {
        $customer = $this->customer('AT', 'ATU12345678');
        $product = $this->product();
        $orders = $this->app->container()->get(OrderService::class);

        $quote = $orders->quote((array) $this->app->container()->get(ProductRepository::class)->find($product), null, $customer);
        self::assertSame(1000, $quote['total'], 'Reverse charge customers see net prices.');

        $result = $orders->place($customer, $product, ['hostname' => 'srv1'], '127.0.0.1');
        $invoice = (array) $this->db->fetchOne('SELECT * FROM nx_invoices WHERE id = ?', [$result['invoice_id']]);
        self::assertSame([1000, 0, 1000, 'AE', 'AT'], [(int) $invoice['subtotal'], (int) $invoice['tax'], (int) $invoice['total'], $invoice['tax_category'], $invoice['tax_country']]);
        self::assertStringContainsString('Reverse Charge', (string) $invoice['tax_note']);
        self::assertTrue(json_decode((string) $invoice['billing_details'], true)['vat_id_verified']);

        $this->actingAs($customer);
        self::assertStringContainsString('Reverse Charge', $this->get('/invoices/' . $result['invoice_id'])->body());

        // The renewal follows the current status: the VAT ID is no longer valid.
        $this->clock()->freeze(new DateTimeImmutable('2026-06-25 12:00:00'));
        $serviceId = $this->app->container()->get(ServiceRepository::class)->create([
            'user_id' => $customer->id, 'product_id' => $product, 'category' => 'dedicated', 'name' => 'srv1', 'status' => 'active',
            'billing_cycle' => 'monthly', 'recurring_amount' => 1000, 'tax_rate_bp' => 0, 'currency' => 'EUR', 'next_due_at' => '2026-07-01 00:00:00', 'config' => [],
        ]);
        $this->db->update('nx_users', ['vat_id_valid' => 0], ['id' => $customer->id]);
        $invoices = $this->app->container()->get(InvoiceService::class);
        $renewalId = $invoices->createRenewal($this->app->container()->get(ServiceRepository::class)->find($serviceId) ?? throw new \RuntimeException());
        self::assertNotNull($renewalId);
        self::assertSame(190, (int) $this->db->fetchValue('SELECT tax FROM nx_invoices WHERE id = ?', [$renewalId]));

        // Imported services without a product keep their recorded (gross) treatment.
        $legacy = $this->app->container()->get(ServiceRepository::class)->create([
            'user_id' => $customer->id, 'category' => 'lxc', 'name' => 'legacy', 'status' => 'active',
            'billing_cycle' => 'monthly', 'recurring_amount' => 500, 'tax_rate_bp' => 0, 'currency' => 'EUR', 'next_due_at' => '2026-07-01 00:00:00', 'config' => [],
        ]);
        $legacyInvoice = $invoices->createRenewal($this->app->container()->get(ServiceRepository::class)->find($legacy) ?? throw new \RuntimeException());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT tax FROM nx_invoices WHERE id = ?', [$legacyInvoice]));
    }

    public function testVatIdIsVerifiedWhenTheProfileChanges(): void
    {
        /** @var FakeVatIdValidator $vies */
        $vies = $this->app->container()->get(VatIdValidator::class);
        $customer = $this->createUser(Role::CUSTOMER, ['country' => 'AT']);
        $this->actingAs($customer);
        $profile = ['first_name' => 'Anna', 'last_name' => 'Huber', 'language' => 'en', 'country' => 'AT', 'auto_pay_credit' => '1', 'address_line1' => 'Ring 1', 'postcode' => '1010', 'city' => 'Wien', 'company' => 'Example Trading'];

        // Business customers must give their full address (e-invoices).
        $this->post('/account/profile', ['first_name' => 'Anna', 'last_name' => 'Huber', 'language' => 'en', 'country' => 'AT', 'vat_id' => 'ATU12345678']);
        self::assertNull($this->db->fetchValue('SELECT vat_id FROM nx_users WHERE id = ?', [$customer->id]));

        $this->post('/account/profile', $profile + ['vat_id' => 'ATU 1234 5678']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT vat_id_valid FROM nx_users WHERE id = ?', [$customer->id]));
        self::assertSame('Example Trading GmbH', $this->db->fetchValue('SELECT vat_id_name FROM nx_users WHERE id = ?', [$customer->id]));
        self::assertStringContainsString('verified', $this->get('/account/profile')->body());

        $this->post('/account/profile', $profile + ['vat_id' => 'ATU00000000']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT vat_id_valid FROM nx_users WHERE id = ?', [$customer->id]));

        // VIES down: unknown, re-checked by the scheduler later.
        $vies->down = true;
        $this->post('/account/profile', $profile + ['vat_id' => 'ATU12345678']);
        self::assertNull($this->db->fetchValue('SELECT vat_id_valid FROM nx_users WHERE id = ?', [$customer->id]));
        $vies->down = false;
        self::assertSame(1, $this->app->container()->get(TaxService::class)->recheckStale());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT vat_id_valid FROM nx_users WHERE id = ?', [$customer->id]));
        self::assertSame(0, $this->app->container()->get(TaxService::class)->recheckStale(), 'Checked IDs are not queried again within 30 days.');
    }

    public function testVatIdParsing(): void
    {
        self::assertSame(['DE', '123456789'], VatIdValidator::parse('de 123.456.789'));
        self::assertSame(['EL', '123456789'], VatIdValidator::parse('EL123456789'));
        self::assertNull(VatIdValidator::parse('CHE-123.456.789'));
        self::assertNull(VatIdValidator::parse('XX123'));
    }

    public function testReverseChargeNeedsTheRegisteredCompanyName(): void
    {
        $taxes = $this->app->container()->get(TaxService::class);
        $users = $this->app->container()->get(UserRepository::class);

        // A private customer with a real company's VAT ID gets no reverse charge.
        $impostor = $this->createUser(Role::CUSTOMER, ['country' => 'AT', 'vat_id' => 'ATU12345678', 'company' => 'Huber Consulting']);
        $taxes->verifyUser($impostor);
        $impostor = $users->find($impostor->id) ?? throw new \RuntimeException();
        self::assertSame('name_mismatch', $taxes->vatIdStatus($impostor));
        self::assertSame(TaxDecision::STANDARD, $taxes->decide($impostor)->category);

        $noCompany = $this->createUser(Role::CUSTOMER, ['country' => 'AT', 'vat_id' => 'ATU12345678']);
        $taxes->verifyUser($noCompany);
        self::assertSame('company_missing', $taxes->vatIdStatus($users->find($noCompany->id) ?? throw new \RuntimeException()));

        // Staff see the status and the registered name.
        $this->actingAs($this->createUser(Role::ADMIN));
        $page = $this->get('/admin/customers/' . $impostor->id)->body();
        self::assertStringContainsString('registered to a different name', $page);
        self::assertStringContainsString('Example Trading GmbH', $page);

        self::assertTrue(TaxService::namesMatch('Example Trading', 'EXAMPLE TRADING GMBH'));
        self::assertTrue(TaxService::namesMatch('Müller & Söhne GmbH', 'MUELLER UND SOEHNE GESELLSCHAFT MIT BESCHRÄNKTER HAFTUNG'));
        self::assertFalse(TaxService::namesMatch('Huber Consulting', 'Example Trading GmbH'));
    }

    public function testVerifiedVatIdSurvivesAViesOutage(): void
    {
        /** @var FakeVatIdValidator $vies */
        $vies = $this->app->container()->get(VatIdValidator::class);
        $taxes = $this->app->container()->get(TaxService::class);
        $customer = $this->customer('AT', 'ATU12345678');
        self::assertSame('verified', $taxes->vatIdStatus($customer));

        // The scheduled re-check during an outage keeps the earlier result …
        $this->db->update('nx_users', ['vat_id_checked_at' => '2026-01-01 00:00:00'], ['id' => $customer->id]);
        $vies->down = true;
        self::assertSame(0, $taxes->recheckStale());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT vat_id_valid FROM nx_users WHERE id = ?', [$customer->id]));
        self::assertSame(TaxDecision::REVERSE_CHARGE, $taxes->decide($this->app->container()->get(UserRepository::class)->find($customer->id) ?? throw new \RuntimeException())->category);

        // … while a newly entered VAT ID stays unchecked until VIES answers.
        $this->db->update('nx_users', ['vat_id' => 'FR12345678901'], ['id' => $customer->id]);
        $taxes->verifyUser($this->app->container()->get(UserRepository::class)->find($customer->id) ?? throw new \RuntimeException(), true);
        self::assertNull($this->db->fetchValue('SELECT vat_id_valid FROM nx_users WHERE id = ?', [$customer->id]));
    }
}
