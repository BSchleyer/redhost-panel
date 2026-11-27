<?php

declare(strict_types=1);

namespace Nebrix\Billing\Tax;

use Nebrix\Auth\User;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Settings\SettingsRepository;

/**
 * Decides how a customer is taxed (settings group "tax"):
 *
 * 1. Small business (§ 19 UStG): no VAT for anyone, with the legal note.
 * 2. Customers in the home country or without a country: the product rate.
 * 3. EU business customers with a VAT ID verified through VIES: reverse
 *    charge (0 %, note) when enabled.
 * 4. Other EU customers: the standard rate of their country when OSS is
 *    enabled, otherwise the product rate.
 * 5. Customers outside the EU: 0 % "not taxable in the home country" when
 *    enabled, otherwise the product rate.
 *
 * The rules implement common cases; whether they fit a business must be
 * confirmed by its tax advisor.
 */
class TaxService
{
    private const RECHECK_AFTER_DAYS = 30;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly VatIdValidator $validator,
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly \Nebrix\Repository\UserRepository $users,
    ) {
    }

    public function decide(User $user): TaxDecision
    {
        $country = strtoupper(trim((string) ($user->attributes['country'] ?? '')));
        $country = preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;

        if ($this->settings->bool('tax.small_business')) {
            return new TaxDecision(TaxDecision::EXEMPT, 0, $this->settings->string('tax.small_business_note') ?: null, $country);
        }
        $home = strtoupper($this->settings->string('tax.home_country') ?: 'DE');
        if ($country === null || $country === $home) {
            return new TaxDecision(TaxDecision::STANDARD, null, null, $country);
        }

        if (EuVat::isMember($country)) {
            if ($this->settings->bool('tax.reverse_charge') && $this->hasVerifiedVatId($user, $country)) {
                return new TaxDecision(TaxDecision::REVERSE_CHARGE, 0, $this->settings->string('tax.reverse_charge_note') ?: null, $country);
            }
            if ($this->settings->bool('tax.oss')) {
                return new TaxDecision(TaxDecision::STANDARD, $this->standardRate($country), null, $country);
            }

            return new TaxDecision(TaxDecision::STANDARD, null, null, $country);
        }

        if ($this->settings->bool('tax.non_eu_exempt')) {
            return new TaxDecision(TaxDecision::OUTSIDE_SCOPE, 0, $this->settings->string('tax.non_eu_note') ?: null, $country);
        }

        return new TaxDecision(TaxDecision::STANDARD, null, null, $country);
    }

    /** Standard rate of an EU member state (setting override first). */
    public function standardRate(string $country): int
    {
        $country = strtoupper($country);
        foreach (preg_split('/[\s,;]+/', $this->settings->string('tax.rate_overrides')) ?: [] as $entry) {
            if (preg_match('/^([A-Z]{2})=(\d{1,4})$/', strtoupper(trim($entry)), $match) === 1 && $match[1] === $country) {
                return (int) $match[2];
            }
        }

        return EuVat::STANDARD_RATES[$country] ?? 0;
    }

    /**
     * Verifies the customer's VAT ID with VIES and stores the result.
     * Returns the stored state: true (valid), false (invalid) or null
     * (no VAT ID, or VIES not reachable – checked again later).
     */
    public function verifyUser(User $user): ?bool
    {
        $vatId = trim((string) ($user->attributes['vat_id'] ?? ''));
        if ($vatId === '') {
            $this->db->update('nx_users', ['vat_id_valid' => null, 'vat_id_checked_at' => null, 'vat_id_name' => null], ['id' => $user->id]);

            return null;
        }
        $result = $this->validator->check($vatId);
        $valid = match ($result['status']) {
            VatIdValidator::VALID => true,
            VatIdValidator::INVALID => false,
            default => null,
        };
        $this->db->update('nx_users', [
            'vat_id_valid' => $valid === null ? null : ($valid ? 1 : 0),
            'vat_id_checked_at' => $valid === null ? null : $this->clock->nowString(),
            'vat_id_name' => $result['name'],
        ], ['id' => $user->id]);

        return $valid;
    }

    /**
     * Re-checks VAT IDs that were never checked successfully or not within
     * 30 days (scheduler).
     */
    public function recheckStale(int $limit = 100): int
    {
        $rows = $this->db->fetchAll(
            "SELECT `id` FROM `nx_users` WHERE `vat_id` IS NOT NULL AND `vat_id` <> '' AND `status` = 'active'
               AND (`vat_id_checked_at` IS NULL OR `vat_id_checked_at` < ?) ORDER BY `vat_id_checked_at` IS NOT NULL, `vat_id_checked_at` LIMIT ?",
            [$this->clock->now()->modify('-' . self::RECHECK_AFTER_DAYS . ' days')->format('Y-m-d H:i:s'), $limit],
        );
        $checked = 0;
        foreach ($rows as $row) {
            $user = $this->users->find((int) $row['id']);
            if ($user !== null && $this->verifyUser($user) !== null) {
                $checked++;
            }
        }

        return $checked;
    }

    private function hasVerifiedVatId(User $user, string $country): bool
    {
        $parsed = VatIdValidator::parse((string) ($user->attributes['vat_id'] ?? ''));

        return $parsed !== null
            && EuVat::countryForPrefix($parsed[0]) === $country
            && (int) ($user->attributes['vat_id_valid'] ?? 0) === 1;
    }
}
