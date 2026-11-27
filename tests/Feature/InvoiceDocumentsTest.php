<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Nebrix\Api\ApiScope;
use Nebrix\Api\ApiTokenService;
use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Billing\CreditService;
use Nebrix\Billing\Documents\InvoiceDocuments;
use Nebrix\Billing\Documents\XRechnungWriter;
use Nebrix\Billing\InvoiceService;
use Nebrix\Billing\OrderService;
use Nebrix\Billing\Tax\TaxService;
use Nebrix\Http\Request;
use Nebrix\Payment\GatewayRegistry;
use Nebrix\Payment\PaymentService;
use Nebrix\Repository\ProductRepository;
use Nebrix\Repository\UserRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\FeatureTestCase;

/**
 * Invoice PDF, XRechnung and correction documents.
 *
 * Set XRECHNUNG_DUMP_DIR to write the generated XML files for validation
 * with the official KoSIT/EN 16931 rules.
 */
final class InvoiceDocumentsTest extends FeatureTestCase
{
    private SettingsRepository $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = $this->app->container()->get(SettingsRepository::class);
        foreach ([
            'general.company_name' => 'Nebrix Hosting GmbH', 'general.company_email' => 'billing@nebrix.example', 'general.company_phone' => '+49 30 1234567',
            'invoice.seller_street' => 'Hauptstraße 1', 'invoice.seller_postcode' => '10115', 'invoice.seller_city' => 'Berlin',
            'invoice.seller_vat_id' => 'DE123456789', 'invoice.seller_tax_number' => '27/123/45678', 'invoice.contact_name' => 'Buchhaltung',
            'invoice.bank_iban' => 'DE02120300000000202051', 'invoice.bank_bic' => 'BYLADEM1001', 'invoice.footer' => 'Amtsgericht Berlin HRB 12345',
        ] as $key => $value) {
            $this->settings->set($key, $value);
        }
    }

    private function customer(string $country, ?string $vatId = null, string $language = 'de'): User
    {
        $user = $this->createUser(Role::CUSTOMER, [
            'country' => $country, 'vat_id' => $vatId, 'company' => $vatId !== null ? 'Example Trading GmbH' : null, 'language' => $language,
            'address_line1' => 'Musterweg 2', 'postcode' => '1010', 'city' => 'Wien',
        ]);
        if ($vatId !== null) {
            $this->app->container()->get(TaxService::class)->verifyUser($user);
        }

        return $this->app->container()->get(UserRepository::class)->find($user->id) ?? $user;
    }

    /**
     * @return array{order_id: int, invoice_id: int, paid: bool}
     */
    private function order(User $user): array
    {
        $product = $this->app->container()->get(ProductRepository::class)->create([
            'category' => 'dedicated', 'name' => 'Root S', 'slug' => 'root-' . bin2hex(random_bytes(3)), 'price' => 1000, 'setup_fee' => 0, 'currency' => 'EUR',
            'billing_cycle' => 'monthly', 'tax_rate_bp' => 1900, 'is_visible' => 1, 'is_active' => 1, 'auto_provision' => 0, 'provisioning_config' => [],
        ]);

        return $this->app->container()->get(OrderService::class)->place($user, $product, ['hostname' => 'srv1'], '127.0.0.1');
    }

    private function xpath(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($xml));
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        return $xpath;
    }

    private function value(DOMXPath $xpath, string $query): string
    {
        return trim((string) $xpath->evaluate('string(' . $query . ')'));
    }

    private function dump(string $name, string $content, string $extension = 'xml'): void
    {
        $dir = getenv('XRECHNUNG_DUMP_DIR');
        if (is_string($dir) && $dir !== '') {
            @mkdir($dir, 0777, true);
            file_put_contents($dir . '/' . $name . '.' . $extension, $content);
        }
    }

    public function testDomesticInvoiceIsArchivedAsPdfAndXRechnung(): void
    {
        $customer = $this->customer('DE');
        $invoiceId = $this->order($customer)['invoice_id'];
        $documents = $this->app->container()->get(InvoiceDocuments::class);

        $pdf = $documents->pdf($invoiceId);
        self::assertStringStartsWith('%PDF-', $pdf);
        $this->dump('standard', $pdf, 'pdf');
        self::assertSame($pdf, $documents->pdf($invoiceId), 'The archived document is returned unchanged.');
        $invoice = (array) $this->db->fetchOne('SELECT * FROM nx_invoices WHERE id = ?', [$invoiceId]);
        self::assertSame(hash('sha256', $pdf), $invoice['pdf_sha256']);
        self::assertNotNull($invoice['archived_at']);

        $xml = $documents->xml($invoiceId);
        $this->dump('standard', $xml);
        $x = $this->xpath($xml);
        self::assertSame(XRechnungWriter::GUIDELINE, $this->value($x, '//ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'));
        self::assertSame('380', $this->value($x, '//rsm:ExchangedDocument/ram:TypeCode'));
        self::assertSame((string) $invoice['number'], $this->value($x, '//rsm:ExchangedDocument/ram:ID'));
        self::assertSame('S', $this->value($x, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertSame('1.90', $this->value($x, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:CalculatedAmount'));
        self::assertSame('11.90', $this->value($x, '//ram:GrandTotalAmount'));
        self::assertSame('11.90', $this->value($x, '//ram:DuePayableAmount'));
        self::assertSame('DE02120300000000202051', $this->value($x, '//ram:IBANID'));
        self::assertSame('DE123456789', $this->value($x, "//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID='VA']"));

        // A modified archive file is detected and not delivered.
        $file = sys_get_temp_dir() . '/nebrix-test-invoices/' . substr((string) $invoice['created_at'], 0, 4) . '/' . $invoice['number'] . '-' . $invoiceId . '.pdf';
        @chmod($file, 0640);
        file_put_contents($file, '%PDF-forged');
        $this->expectException(\RuntimeException::class);
        $documents->pdf($invoiceId);
    }

    public function testReverseChargeSmallBusinessAndThirdCountry(): void
    {
        $documents = $this->app->container()->get(InvoiceDocuments::class);

        $business = $this->customer('AT', 'ATU12345678');
        $xml = $documents->xml($this->order($business)['invoice_id']);
        $this->dump('reverse-charge', $xml);
        $x = $this->xpath($xml);
        self::assertSame('AE', $this->value($x, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertSame('VATEX-EU-AE', $this->value($x, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReasonCode'));
        self::assertSame('ATU12345678', $this->value($x, "//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID='VA']"));
        self::assertSame('10.00', $this->value($x, '//ram:GrandTotalAmount'));

        $this->settings->set('tax.non_eu_exempt', true);
        $swiss = $this->customer('CH');
        $xml = $documents->xml($this->order($swiss)['invoice_id']);
        $this->dump('outside-scope', $xml);
        $x = $this->xpath($xml);
        self::assertSame('O', $this->value($x, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertSame('', $this->value($x, '//ram:IncludedSupplyChainTradeLineItem//ram:ApplicableTradeTax/ram:RateApplicablePercent'), 'Lines of category O carry no rate (BR-O-05).');
        self::assertSame('0.00', $this->value($x, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent'), 'The breakdown states a rate (BR-DE-14).');
        self::assertSame('27/123/45678', $this->value($x, '//ram:SellerTradeParty/ram:ID'), 'Seller identifier instead of the VAT ID (BR-CO-26).');
        self::assertSame('', $this->value($x, "//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID='VA']"), 'No VAT IDs with category O.');

        $this->settings->set('tax.small_business', true);
        $xml = $documents->xml($this->order($this->customer('DE'))['invoice_id']);
        $this->dump('small-business', $xml);
        $x = $this->xpath($xml);
        self::assertSame('E', $this->value($x, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertStringContainsString('§ 19 UStG', $this->value($x, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReason'));
    }

    public function testCancellationAndRefundsCreateCorrectionDocuments(): void
    {
        $customer = $this->customer('DE');
        $invoices = $this->app->container()->get(InvoiceService::class);
        $documents = $this->app->container()->get(InvoiceDocuments::class);

        // Cancellation of an unpaid invoice: a cancellation invoice mirrors it.
        $cancelled = $this->order($customer)['invoice_id'];
        $invoices->cancel($cancelled);
        $storno = (array) $this->db->fetchOne('SELECT * FROM nx_invoices WHERE reference_invoice_id = ?', [$cancelled]);
        self::assertSame(['credit_note', 'issued', 1190, 190], [$storno['type'], $storno['status'], (int) $storno['total'], (int) $storno['tax']]);
        self::assertStringStartsWith('RK-', (string) $storno['number']);
        self::assertTrue($this->mailQueued('invoice_correction'));
        $xml = $documents->xml((int) $storno['id']);
        $this->dump('cancellation', $xml);
        $x = $this->xpath($xml);
        self::assertSame('381', $this->value($x, '//rsm:ExchangedDocument/ram:TypeCode'));
        self::assertSame((string) $this->db->fetchValue('SELECT number FROM nx_invoices WHERE id = ?', [$cancelled]), $this->value($x, '//ram:InvoiceReferencedDocument/ram:IssuerAssignedID'));
        self::assertStringContainsString('nothing is payable', $this->value($x, '//ram:SpecifiedTradePaymentTerms/ram:Description'), 'A voided unpaid invoice promises no refund.');
        self::assertStringStartsWith('%PDF-', $documents->pdf((int) $storno['id']));
        $this->dump('cancellation', $documents->pdf((int) $storno['id']), 'pdf');

        // Partial refund of a paid invoice: one correction line, net + tax = refund.
        $this->app->container()->get(CreditService::class)->adjust($customer->id, 5000, 'test');
        $paid = $this->order($customer)['invoice_id'];
        $invoice = (array) $this->db->fetchOne('SELECT * FROM nx_invoices WHERE id = ?', [$paid]);
        $gateway = $this->app->container()->get(GatewayRegistry::class)->get('credit');
        self::assertNotNull($gateway);
        $gateway->start($invoice, $customer, '', '');
        $reference = (string) $this->db->fetchValue("SELECT reference FROM nx_transactions WHERE invoice_id = ? AND type = 'payment'", [$paid]);
        $this->app->container()->get(PaymentService::class)->recordRefund('credit', 'refund-test-1', $reference, 500, 'EUR');
        $correction = (array) $this->db->fetchOne('SELECT * FROM nx_invoices WHERE reference_invoice_id = ?', [$paid]);
        self::assertSame(500, (int) $correction['total']);
        self::assertStringStartsWith('Teilerstattung zu Rechnung', (string) $this->db->fetchValue('SELECT description FROM nx_invoice_items WHERE invoice_id = ?', [$correction['id']]), 'Stored lines are in the customer\'s language.');
        self::assertStringContainsString('(Monatlich)', (string) $this->db->fetchValue('SELECT description FROM nx_invoice_items WHERE invoice_id = ?', [$paid]));
        self::assertStringContainsString('refunded or credited', $this->value($this->xpath($documents->xml((int) $correction['id'])), '//ram:SpecifiedTradePaymentTerms/ram:Description'));
        self::assertSame(500, (int) $correction['subtotal'] + (int) $correction['tax']);
        self::assertSame(420, (int) $correction['subtotal']);
        $xml = $documents->xml((int) $correction['id']);
        $this->dump('partial-refund', $xml);
        self::assertSame('5.00', $this->value($this->xpath($xml), '//ram:GrandTotalAmount'));

        self::assertSame([420, 80], InvoiceService::splitGross(500, 1900));
        self::assertSame([1000, 0], InvoiceService::splitGross(1000, 0));
    }

    public function testDownloadsAreScopedToTheOwner(): void
    {
        $customer = $this->customer('AT', 'ATU12345678', 'en');
        $invoiceId = $this->order($customer)['invoice_id'];
        self::assertNull($this->db->fetchValue('SELECT archived_at FROM nx_invoices WHERE id = ?', [$invoiceId]), 'Documents are not rendered inside the order transaction.');

        $this->actingAs($this->createUser());
        self::assertSame(404, $this->get('/invoices/' . $invoiceId . '/pdf')->status());

        $this->actingAs($customer);
        $pdf = $this->get('/invoices/' . $invoiceId . '/pdf');
        self::assertSame(200, $pdf->status());
        self::assertSame('application/pdf', $pdf->header('Content-Type'));
        self::assertStringContainsString('attachment; filename="NX-', (string) $pdf->header('Content-Disposition'));
        self::assertStringContainsString('E-invoice (XRechnung)', $this->get('/invoices/' . $invoiceId)->body());

        $this->settings->set('api.enabled', true);
        $token = $this->app->container()->get(ApiTokenService::class)->create($customer, 'Docs', [ApiScope::INVOICES_READ], 30, [], null)['secret'];
        $xml = $this->app->handle(Request::create('GET', '/api/v1/invoices/' . $invoiceId . '/xml', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]));
        self::assertSame(200, $xml->status());
        self::assertSame('application/xml', $xml->header('Content-Type'));
        self::assertStringContainsString('CrossIndustryInvoice', $xml->body());

        // Business customers get the PDF and the XRechnung file attached.
        $payload = (string) $this->db->fetchValue("SELECT payload FROM nx_jobs WHERE type = 'mail.send' AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.template')) = 'order_confirmation' ORDER BY id DESC LIMIT 1");
        $message = $this->app->container()->get(\Nebrix\Security\Encrypter::class)->decryptArray((string) json_decode($payload, true)['message'], 'mail-job');
        self::assertSame([], $message['attachments']);
        self::assertSame($invoiceId, $message['invoice_documents'], 'The worker attaches the documents when it sends the mail.');
        self::assertSame(['application/pdf', 'application/xml'], array_column($this->app->container()->get(InvoiceDocuments::class)->attachments($invoiceId), 'mime'));
        $this->app->container()->get(\Nebrix\Mail\SendMailJob::class)->handle(json_decode($payload, true));
    }

    public function testDashboardWarnsWhileInvoiceDetailsAreIncomplete(): void
    {
        $this->actingAs($this->createUser(Role::ADMIN));
        self::assertStringNotContainsString('Invoice details are incomplete', $this->get('/admin')->body());

        $this->settings->set('invoice.contact_name', '');
        self::assertStringContainsString('Invoice details are incomplete', $this->get('/admin')->body());
    }
}
