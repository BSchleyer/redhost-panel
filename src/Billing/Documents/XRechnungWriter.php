<?php

declare(strict_types=1);

namespace Nebrix\Billing\Documents;

use DOMDocument;
use DOMElement;
use Nebrix\Billing\InvoiceRepository;
use Nebrix\Billing\Money;
use Nebrix\Billing\Tax\TaxDecision;

/**
 * Electronic invoice in the XRechnung 3.0 profile of EN 16931, UN/CEFACT
 * Cross Industry Invoice (CII D16B) syntax.
 *
 * The document is built with DOM (all values escaped). Element order
 * follows the CII schema. Invoices use type code 380, cancellation and
 * correction documents 381 (credit note) with a reference to the original
 * invoice (BT-25). Amounts are positive in both cases.
 */
final class XRechnungWriter
{
    public const GUIDELINE = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0';
    public const PROCESS = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    private const NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';
    private const NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
    private const NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';
    private const NS_QDT = 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100';

    private DOMDocument $doc;

    /**
     * @param array<string, mixed> $invoice
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>|null $original referenced invoice of a correction document
     */
    public function write(array $invoice, array $items, Seller $seller, ?array $original = null): string
    {
        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;
        $root = $this->doc->createElementNS(self::NS_RSM, 'rsm:CrossIndustryInvoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ram', self::NS_RAM);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:udt', self::NS_UDT);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:qdt', self::NS_QDT);
        $this->doc->appendChild($root);

        $billing = json_decode((string) ($invoice['billing_details'] ?? '{}'), true);
        $billing = is_array($billing) ? $billing : [];
        $currency = (string) $invoice['currency'];
        $category = (string) ($invoice['tax_category'] ?: ((int) $invoice['tax'] > 0 ? TaxDecision::STANDARD : TaxDecision::OUTSIDE_SCOPE));
        $isCorrection = $invoice['type'] === InvoiceRepository::TYPE_CREDIT_NOTE;
        // Cancellation of an unpaid invoice: nothing is refunded.
        $isVoid = $isCorrection && $original !== null && $original['status'] === InvoiceRepository::STATUS_CANCELLED;

        // Context (BT-23, BT-24)
        $context = $this->ram($root, 'rsm:ExchangedDocumentContext', null, self::NS_RSM);
        $this->ram($this->ram($context, 'ram:BusinessProcessSpecifiedDocumentContextParameter'), 'ram:ID', self::PROCESS);
        $this->ram($this->ram($context, 'ram:GuidelineSpecifiedDocumentContextParameter'), 'ram:ID', self::GUIDELINE);

        // Document (BT-1, BT-3, BT-2, BT-22)
        $document = $this->ram($root, 'rsm:ExchangedDocument', null, self::NS_RSM);
        $this->ram($document, 'ram:ID', (string) $invoice['number']);
        $this->ram($document, 'ram:TypeCode', $isCorrection ? '381' : '380');
        $this->date($this->ram($document, 'ram:IssueDateTime'), (string) $invoice['created_at']);
        foreach (array_filter([(string) ($invoice['tax_note'] ?? ''), $seller->footer]) as $note) {
            $this->ram($this->ram($document, 'ram:IncludedNote'), 'ram:Content', $note);
        }

        $transaction = $this->ram($root, 'rsm:SupplyChainTradeTransaction', null, self::NS_RSM);

        // Lines (BG-25)
        $groups = [];
        $lineTotal = 0;
        $periodStart = null;
        $periodEnd = null;
        foreach (array_values($items) as $index => $item) {
            $rate = (int) $item['tax_rate_bp'];
            $net = (int) $item['total'];
            $quantity = max(1, (int) $item['quantity']);
            $line = $this->ram($transaction, 'ram:IncludedSupplyChainTradeLineItem');
            $this->ram($this->ram($line, 'ram:AssociatedDocumentLineDocument'), 'ram:LineID', (string) ($index + 1));
            $this->ram($this->ram($line, 'ram:SpecifiedTradeProduct'), 'ram:Name', mb_substr((string) $item['description'], 0, 250));
            $this->ram($this->ram($this->ram($line, 'ram:SpecifiedLineTradeAgreement'), 'ram:NetPriceProductTradePrice'), 'ram:ChargeAmount', $this->amount((int) $item['unit_amount'], $currency));
            $this->ram($this->ram($line, 'ram:SpecifiedLineTradeDelivery'), 'ram:BilledQuantity', (string) $quantity)->setAttribute('unitCode', 'C62');
            $settlement = $this->ram($line, 'ram:SpecifiedLineTradeSettlement');
            $this->tax($settlement, $category, $rate, null, null);
            if (($item['period_start'] ?? null) !== null && ($item['period_end'] ?? null) !== null) {
                $this->period($settlement, (string) $item['period_start'], (string) $item['period_end']);
                $periodStart = $periodStart === null || $item['period_start'] < $periodStart ? (string) $item['period_start'] : $periodStart;
                $periodEnd = $periodEnd === null || $item['period_end'] > $periodEnd ? (string) $item['period_end'] : $periodEnd;
            }
            $this->ram($this->ram($settlement, 'ram:SpecifiedTradeSettlementLineMonetarySummation'), 'ram:LineTotalAmount', $this->amount($net, $currency));

            $key = $category . ':' . $rate;
            $groups[$key] ??= ['rate' => $rate, 'basis' => 0];
            $groups[$key]['basis'] += $net;
            $lineTotal += $net;
        }

        // Agreement: buyer reference (BT-10), seller (BG-4), buyer (BG-7)
        $agreement = $this->ram($transaction, 'ram:ApplicableHeaderTradeAgreement');
        $this->ram($agreement, 'ram:BuyerReference', 'C' . $invoice['user_id']);
        $sellerParty = $this->ram($agreement, 'ram:SellerTradeParty');
        // Category O must not carry VAT identifiers; the seller is then identified by BT-29 (BR-CO-26).
        if ($category === TaxDecision::OUTSIDE_SCOPE && ($seller->taxNumber !== '' || $seller->vatId !== '')) {
            $this->ram($sellerParty, 'ram:ID', $seller->taxNumber !== '' ? $seller->taxNumber : $seller->vatId);
        }
        $this->ram($sellerParty, 'ram:Name', $seller->name);
        $contact = $this->ram($sellerParty, 'ram:DefinedTradeContact');
        $this->ram($contact, 'ram:PersonName', $seller->contactName !== '' ? $seller->contactName : $seller->name);
        $this->ram($this->ram($contact, 'ram:TelephoneUniversalCommunication'), 'ram:CompleteNumber', $seller->phone);
        $this->ram($this->ram($contact, 'ram:EmailURIUniversalCommunication'), 'ram:URIID', $seller->email);
        $this->address($sellerParty, $seller->postcode, $seller->street, '', $seller->city, $seller->country);
        $this->ram($this->ram($sellerParty, 'ram:URIUniversalCommunication'), 'ram:URIID', $seller->email)->setAttribute('schemeID', 'EM');
        // Not subject to VAT (O): no VAT identifiers (BR-O-02), the tax number identifies the seller.
        if ($seller->vatId !== '' && $category !== TaxDecision::OUTSIDE_SCOPE) {
            $this->ram($this->ram($sellerParty, 'ram:SpecifiedTaxRegistration'), 'ram:ID', $seller->vatId)->setAttribute('schemeID', 'VA');
        }
        if ($seller->taxNumber !== '') {
            $this->ram($this->ram($sellerParty, 'ram:SpecifiedTaxRegistration'), 'ram:ID', $seller->taxNumber)->setAttribute('schemeID', 'FC');
        }

        $buyer = $this->ram($agreement, 'ram:BuyerTradeParty');
        $buyerName = trim((string) ($billing['company'] ?? '')) !== '' ? (string) $billing['company'] : (string) ($billing['name'] ?? 'Customer');
        $this->ram($buyer, 'ram:Name', $buyerName);
        $buyerCountry = strtoupper((string) ($billing['country'] ?? '')) ?: $seller->country;
        $this->address($buyer, (string) ($billing['postcode'] ?? ''), (string) ($billing['address_line1'] ?? ''), (string) ($billing['address_line2'] ?? ''), (string) ($billing['city'] ?? ''), $buyerCountry);
        $buyerEmail = (string) ($billing['email'] ?? '');
        if ($buyerEmail !== '') {
            $this->ram($this->ram($buyer, 'ram:URIUniversalCommunication'), 'ram:URIID', $buyerEmail)->setAttribute('schemeID', 'EM');
        }
        if ($category === TaxDecision::REVERSE_CHARGE && trim((string) ($billing['vat_id'] ?? '')) !== '') {
            $this->ram($this->ram($buyer, 'ram:SpecifiedTaxRegistration'), 'ram:ID', strtoupper(str_replace(' ', '', (string) $billing['vat_id'])))->setAttribute('schemeID', 'VA');
        }

        // Delivery: date of supply (BT-72) when no billing period is given.
        $delivery = $this->ram($transaction, 'ram:ApplicableHeaderTradeDelivery');
        if ($periodStart === null) {
            $this->date($this->ram($this->ram($delivery, 'ram:ActualDeliverySupplyChainEvent'), 'ram:OccurrenceDateTime'), (string) $invoice['created_at']);
        }

        // Settlement
        $settlement = $this->ram($transaction, 'ram:ApplicableHeaderTradeSettlement');
        $this->ram($settlement, 'ram:InvoiceCurrencyCode', $currency);
        $means = $this->ram($settlement, 'ram:SpecifiedTradeSettlementPaymentMeans');
        if ($seller->iban !== '' && !$isCorrection) {
            $this->ram($means, 'ram:TypeCode', '58');
            $account = $this->ram($means, 'ram:PayeePartyCreditorFinancialAccount');
            $this->ram($account, 'ram:IBANID', $seller->iban);
            $this->ram($account, 'ram:AccountName', $seller->accountHolder);
            if ($seller->bic !== '') {
                $this->ram($this->ram($means, 'ram:PayeeSpecifiedCreditorFinancialInstitution'), 'ram:BICID', $seller->bic);
            }
        } else {
            $this->ram($means, 'ram:TypeCode', 'ZZZ');
            $this->ram($means, 'ram:Information', $isVoid ? 'No payment due' : ($isCorrection ? 'Refund or offset' : 'Payment via the customer area'));
        }

        $taxTotal = 0;
        foreach ($groups as $group) {
            $tax = $category === TaxDecision::STANDARD ? Money::tax($group['basis'], $group['rate']) : 0;
            $taxTotal += $tax;
            $this->tax($settlement, $category, $group['rate'], $group['basis'], $tax, $currency, (string) ($invoice['tax_note'] ?? ''));
        }
        if ($periodStart !== null && $periodEnd !== null) {
            $this->period($settlement, $periodStart, $periodEnd);
        }

        $grand = $lineTotal + $taxTotal;
        $paid = !$isCorrection && in_array($invoice['status'], [InvoiceRepository::STATUS_PAID, InvoiceRepository::STATUS_REFUNDED], true) ? $grand : 0;
        $terms = $this->ram($settlement, 'ram:SpecifiedTradePaymentTerms');
        $this->ram($terms, 'ram:Description', $isCorrection
            ? ($isVoid ? 'The invoice has been cancelled before it was paid; nothing is payable.' : 'The amount is refunded or credited to the customer account.')
            : ($paid > 0 ? 'Paid.' : 'Payable by the due date via the customer area' . ($seller->iban !== '' ? ' or by bank transfer.' : '.')));
        if (!$isCorrection && $paid === 0) {
            $this->date($this->ram($terms, 'ram:DueDateDateTime'), (string) $invoice['due_at']);
        }

        $sum = $this->ram($settlement, 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $this->ram($sum, 'ram:LineTotalAmount', $this->amount($lineTotal, $currency));
        $this->ram($sum, 'ram:TaxBasisTotalAmount', $this->amount($lineTotal, $currency));
        $this->ram($sum, 'ram:TaxTotalAmount', $this->amount($taxTotal, $currency))->setAttribute('currencyID', $currency);
        $this->ram($sum, 'ram:GrandTotalAmount', $this->amount($grand, $currency));
        if ($paid > 0) {
            $this->ram($sum, 'ram:TotalPrepaidAmount', $this->amount($paid, $currency));
        }
        $this->ram($sum, 'ram:DuePayableAmount', $this->amount($grand - $paid, $currency));

        if ($original !== null) {
            $reference = $this->ram($settlement, 'ram:InvoiceReferencedDocument');
            $this->ram($reference, 'ram:IssuerAssignedID', (string) $original['number']);
            $formatted = $this->ram($reference, 'ram:FormattedIssueDateTime');
            $this->ram($formatted, 'qdt:DateTimeString', $this->ymd((string) $original['created_at']), self::NS_QDT)->setAttribute('format', '102');
        }

        return (string) $this->doc->saveXML();
    }

    private function tax(DOMElement $parent, string $category, int $rate, ?int $basis, ?int $tax, string $currency = 'EUR', string $note = ''): void
    {
        $node = $this->ram($parent, 'ram:ApplicableTradeTax');
        if ($tax !== null) {
            $this->ram($node, 'ram:CalculatedAmount', $this->amount($tax, $currency));
        }
        $this->ram($node, 'ram:TypeCode', 'VAT');
        $exempt = $category !== TaxDecision::STANDARD;
        if ($basis !== null && $exempt) {
            $this->ram($node, 'ram:ExemptionReason', $note !== '' ? $note : match ($category) {
                TaxDecision::REVERSE_CHARGE => 'Reverse charge',
                TaxDecision::OUTSIDE_SCOPE => 'Not subject to VAT',
                default => 'Exempt from VAT',
            });
        }
        if ($basis !== null) {
            $this->ram($node, 'ram:BasisAmount', $this->amount($basis, $currency));
        }
        $this->ram($node, 'ram:CategoryCode', $category);
        if ($basis !== null && in_array($category, [TaxDecision::REVERSE_CHARGE, TaxDecision::OUTSIDE_SCOPE], true)) {
            $this->ram($node, 'ram:ExemptionReasonCode', $category === TaxDecision::REVERSE_CHARGE ? 'VATEX-EU-AE' : 'VATEX-EU-O');
        }
        // Lines of category O carry no rate (BR-O-05); the VAT breakdown always states
        // one (BR-DE-14), 0 for the exempt categories.
        if ($basis !== null || $category !== TaxDecision::OUTSIDE_SCOPE) {
            $this->ram($node, 'ram:RateApplicablePercent', number_format(($exempt ? 0 : $rate) / 100, 2, '.', ''));
        }
    }

    private function address(DOMElement $party, string $postcode, string $line1, string $line2, string $city, string $country): void
    {
        $address = $this->ram($party, 'ram:PostalTradeAddress');
        if ($postcode !== '') {
            $this->ram($address, 'ram:PostcodeCode', $postcode);
        }
        if ($line1 !== '') {
            $this->ram($address, 'ram:LineOne', $line1);
        }
        if ($line2 !== '') {
            $this->ram($address, 'ram:LineTwo', $line2);
        }
        if ($city !== '') {
            $this->ram($address, 'ram:CityName', $city);
        }
        $this->ram($address, 'ram:CountryID', $country);
    }

    private function period(DOMElement $parent, string $start, string $end): void
    {
        $period = $this->ram($parent, 'ram:BillingSpecifiedPeriod');
        $this->date($this->ram($period, 'ram:StartDateTime'), $start);
        // The service period ends the day before the next term starts.
        $this->date($this->ram($period, 'ram:EndDateTime'), date('Y-m-d', (strtotime($end . ' UTC') ?: 0) - 86400));
    }

    private function date(DOMElement $parent, string $value): void
    {
        $this->ram($parent, 'udt:DateTimeString', $this->ymd($value), self::NS_UDT)->setAttribute('format', '102');
    }

    private function ymd(string $value): string
    {
        return gmdate('Ymd', strtotime($value . (str_contains($value, 'T') ? '' : ' UTC')) ?: 0);
    }

    private function amount(int $minor, string $currency): string
    {
        return number_format($minor / (10 ** Money::decimals($currency)), 2, '.', '');
    }

    private function ram(DOMElement $parent, string $name, ?string $value = null, string $namespace = self::NS_RAM): DOMElement
    {
        $element = $this->doc->createElementNS($namespace, $name);
        if ($value !== null) {
            $element->appendChild($this->doc->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }
}
