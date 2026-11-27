<?php

declare(strict_types=1);

namespace Nebrix\Billing\Tax;

/**
 * EU member states and their standard VAT rates (basis points).
 *
 * Rates as known in November 2026 – they change occasionally. Verify them
 * before going live and keep them current through the setting
 * tax.rate_overrides (e.g. "EE=2400"), which takes precedence.
 */
final class EuVat
{
    /** @var array<string, int> */
    public const STANDARD_RATES = [
        'AT' => 2000, 'BE' => 2100, 'BG' => 2000, 'CY' => 1900, 'CZ' => 2100, 'DE' => 1900, 'DK' => 2500,
        'EE' => 2400, 'ES' => 2100, 'FI' => 2550, 'FR' => 2000, 'GR' => 2400, 'HR' => 2500, 'HU' => 2700,
        'IE' => 2300, 'IT' => 2200, 'LT' => 2100, 'LU' => 1700, 'LV' => 2100, 'MT' => 1800, 'NL' => 2100,
        'PL' => 2300, 'PT' => 2300, 'RO' => 2100, 'SE' => 2500, 'SI' => 2200, 'SK' => 2300,
    ];

    public static function isMember(string $country): bool
    {
        return isset(self::STANDARD_RATES[strtoupper($country)]);
    }

    /** VAT ID prefix of a member state (Greece uses "EL"). */
    public static function prefix(string $country): string
    {
        $country = strtoupper($country);

        return $country === 'GR' ? 'EL' : $country;
    }

    public static function countryForPrefix(string $prefix): ?string
    {
        $prefix = strtoupper($prefix);
        $country = $prefix === 'EL' ? 'GR' : $prefix;

        return self::isMember($country) ? $country : null;
    }
}
