<?php

declare(strict_types=1);

namespace Nebrix\Billing\Documents;

use Nebrix\Settings\SettingsRepository;

/**
 * Seller data for invoice documents, from the settings
 * (General → company, Invoice details, Taxes).
 */
final class Seller
{
    public function __construct(
        public readonly string $name,
        public readonly string $street,
        public readonly string $postcode,
        public readonly string $city,
        public readonly string $country,
        public readonly string $addressText,
        public readonly string $vatId,
        public readonly string $taxNumber,
        public readonly string $contactName,
        public readonly string $phone,
        public readonly string $email,
        public readonly string $website,
        public readonly string $iban,
        public readonly string $bic,
        public readonly string $accountHolder,
        public readonly string $footer,
    ) {
    }

    public static function fromSettings(SettingsRepository $settings): self
    {
        $vatId = $settings->string('invoice.seller_vat_id');
        $legacyTaxId = $settings->string('general.company_tax_id');
        if ($vatId === '' && preg_match('/^[A-Z]{2}[A-Z0-9]{2,12}$/', strtoupper(str_replace(' ', '', $legacyTaxId))) === 1) {
            $vatId = strtoupper(str_replace(' ', '', $legacyTaxId));
        }
        $taxNumber = $settings->string('invoice.seller_tax_number');
        if ($taxNumber === '' && $vatId === '' && $legacyTaxId !== '') {
            $taxNumber = $legacyTaxId;
        }

        return new self(
            $settings->string('general.company_name'),
            $settings->string('invoice.seller_street'),
            $settings->string('invoice.seller_postcode'),
            $settings->string('invoice.seller_city'),
            strtoupper($settings->string('tax.home_country') ?: 'DE'),
            $settings->string('general.company_address'),
            $vatId,
            $taxNumber,
            $settings->string('invoice.contact_name'),
            $settings->string('general.company_phone'),
            $settings->string('general.company_email') ?: $settings->string('general.support_email'),
            $settings->string('general.website_url'),
            strtoupper(str_replace(' ', '', $settings->string('invoice.bank_iban'))),
            strtoupper($settings->string('invoice.bank_bic')),
            $settings->string('invoice.bank_account_holder') ?: $settings->string('general.company_name'),
            $settings->string('invoice.footer'),
        );
    }

    /**
     * Address lines for the PDF: structured fields, else the free-text address.
     *
     * @return list<string>
     */
    public function addressLines(): array
    {
        if ($this->street !== '' || $this->city !== '') {
            return array_values(array_filter([$this->street, trim($this->postcode . ' ' . $this->city)]));
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/', $this->addressText) ?: [])));
    }

    /**
     * Data an EN 16931 / XRechnung invoice requires but the settings lack.
     *
     * @return list<string> setting labels
     */
    public function missingForEInvoice(): array
    {
        $missing = [];
        foreach ([
            'Company name' => $this->name,
            'Street and number' => $this->street,
            'Postcode' => $this->postcode,
            'City' => $this->city,
            'VAT ID or tax number' => $this->vatId . $this->taxNumber,
            'Contact person for invoices' => $this->contactName,
            'Contact phone' => $this->phone,
            'Contact e-mail' => $this->email,
        ] as $label => $value) {
            if (trim($value) === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }
}
