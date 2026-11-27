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

    /** Pause between two VIES requests of the scheduled re-check. */
    private int $pauseMicroseconds = 200_000;

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
     * Returns true (valid), false (invalid) or null (no VAT ID, or VIES not
     * reachable – checked again later). While VIES is unavailable an earlier
     * result for the same VAT ID is kept; only a definite answer changes it.
     *
     * @param bool $vatIdChanged the VAT ID was just entered or changed (no earlier result applies)
     */
    public function verifyUser(User $user, bool $vatIdChanged = true): ?bool
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
        if ($valid === null && !$vatIdChanged) {
            return null; // keep the earlier result; the scheduler tries again
        }
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
    public function recheckStale(int $limit = 20): int
    {
        $rows = $this->db->fetchAll(
            "SELECT `id` FROM `nx_users` WHERE `vat_id` IS NOT NULL AND `vat_id` <> '' AND `status` = 'active'
               AND (`vat_id_checked_at` IS NULL OR `vat_id_checked_at` < ?) ORDER BY `vat_id_checked_at` IS NOT NULL, `vat_id_checked_at` LIMIT ?",
            [$this->clock->now()->modify('-' . self::RECHECK_AFTER_DAYS . ' days')->format('Y-m-d H:i:s'), $limit],
        );
        $checked = 0;
        foreach ($rows as $index => $row) {
            if ($index > 0 && $this->pauseMicroseconds > 0) {
                usleep($this->pauseMicroseconds); // VIES throttles bursts of requests
            }
            $user = $this->users->find((int) $row['id']);
            if ($user !== null && $this->verifyUser($user, false) !== null) {
                $checked++;
            }
        }

        return $checked;
    }

    /**
     * Status of the customer's VAT ID for display and the reverse charge
     * decision: none, unchecked, invalid, company_missing (a company name is
     * needed), name_mismatch (VIES reports another holder) or verified.
     */
    public function vatIdStatus(User $user): string
    {
        if (trim((string) ($user->attributes['vat_id'] ?? '')) === '') {
            return 'none';
        }
        $valid = $user->attributes['vat_id_valid'] ?? null;
        if ($valid === null) {
            return 'unchecked';
        }
        if ((int) $valid !== 1) {
            return 'invalid';
        }
        $company = trim((string) ($user->attributes['company'] ?? ''));
        if ($company === '') {
            return 'company_missing';
        }
        $registered = trim((string) ($user->attributes['vat_id_name'] ?? ''));

        // Some member states (e.g. DE, ES) do not disclose the holder's name.
        return $registered === '' || self::namesMatch($company, $registered) ? 'verified' : 'name_mismatch';
    }

    /**
     * Compares a company name with the name registered in VIES, ignoring
     * case, punctuation, umlaut spelling and common legal forms.
     */
    public static function namesMatch(string $company, string $registered): bool
    {
        $normalize = static function (string $name): string {
            $name = mb_strtolower($name);
            $name = strtr($name, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', '&' => ' und ']);
            $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : false;
            $name = is_string($ascii) && $ascii !== '' ? strtolower($ascii) : $name;
            $tokens = preg_split('/[^a-z0-9]+/', $name) ?: [];
            $legal = ['gmbh', 'mbh', 'gesmbh', 'ag', 'kg', 'ohg', 'gbr', 'ug', 'ek', 'e', 'k', 'v', 'co', 'und', 'and', 'haftungsbeschraenkt', 'haftungsbeschrankt',
                'gesellschaft', 'mit', 'beschraenkter', 'beschrankter', 'haftung', 'ltd', 'limited', 'plc', 'llc', 'inc', 'sarl', 'sas', 'sa', 'srl', 'spa', 'bv', 'nv',
                'oy', 'ab', 'as', 'aps', 'sp', 'z', 'o', 'zoo', 'kft', 'sro', 'doo', 'ou', 'sl', 'lda', 'the'];

            return implode(' ', array_filter($tokens, static fn (string $token): bool => $token !== '' && !in_array($token, $legal, true)));
        };
        $a = $normalize($company);
        $b = $normalize($registered);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b || str_contains(' ' . $b . ' ', ' ' . $a . ' ') || str_contains(' ' . $a . ' ', ' ' . $b . ' ')) {
            return true;
        }
        similar_text($a, $b, $percent);

        return $percent >= 85.0;
    }

    private function hasVerifiedVatId(User $user, string $country): bool
    {
        $parsed = VatIdValidator::parse((string) ($user->attributes['vat_id'] ?? ''));

        return $parsed !== null
            && EuVat::countryForPrefix($parsed[0]) === $country
            && $this->vatIdStatus($user) === 'verified';
    }
}
