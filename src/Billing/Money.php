<?php

declare(strict_types=1);

namespace Nebrix\Billing;

use InvalidArgumentException;

/**
 * Money helpers. Amounts are always integers in minor units (cents) to avoid
 * floating point rounding errors (legacy stored decimals as strings/floats).
 */
final class Money
{
    /** Currencies without minor units. */
    private const ZERO_DECIMAL = ['JPY', 'KRW', 'VND', 'CLP', 'ISK', 'HUF_STRIPE'];

    public static function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    /** Parses a validated decimal string such as "12.50" into minor units. */
    public static function parse(string $amount, string $currency = 'EUR'): int
    {
        $amount = str_replace(',', '.', trim($amount));
        if (preg_match('/^(-)?(\d{1,12})(?:\.(\d{1,2}))?$/', $amount, $match) !== 1) {
            throw new InvalidArgumentException('Invalid amount.');
        }

        $decimals = self::decimals($currency);
        $fraction = str_pad($match[3] ?? '', $decimals, '0');
        if ($decimals === 0 && ($match[3] ?? '') !== '' && (int) $match[3] !== 0) {
            throw new InvalidArgumentException('This currency has no minor units.');
        }

        $minor = (int) $match[2] * (10 ** $decimals) + ($decimals > 0 ? (int) substr($fraction, 0, $decimals) : 0);

        return $match[1] === '-' ? -$minor : $minor;
    }

    /** Converts minor units to a plain decimal string ("12.50") for APIs and forms. */
    public static function toDecimal(int $minor, string $currency = 'EUR'): string
    {
        $decimals = self::decimals($currency);
        if ($decimals === 0) {
            return (string) $minor;
        }

        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return sprintf('%s%d.%02d', $sign, intdiv($minor, 100), $minor % 100);
    }

    public static function format(int $minor, string $currency, string $locale = 'en'): string
    {
        $decimal = self::toDecimal($minor, $currency);
        $decimals = self::decimals($currency);
        $number = number_format((float) $decimal, $decimals, $locale === 'de' ? ',' : '.', $locale === 'de' ? '.' : ',');

        $symbol = match (strtoupper($currency)) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF',
            default => strtoupper($currency),
        };

        return $locale === 'de' || $symbol === 'CHF' ? $number . ' ' . $symbol : $symbol . $number;
    }

    /**
     * Tax in minor units for a net amount and a rate in basis points
     * (1900 = 19.00 %), rounded half up.
     */
    public static function tax(int $net, int $rateBasisPoints): int
    {
        if ($rateBasisPoints <= 0) {
            return 0;
        }

        return intdiv($net * $rateBasisPoints + 5000, 10000);
    }

    /** Applies a percentage discount in basis points, rounding the discount half up. */
    public static function discount(int $amount, int $discountBasisPoints): int
    {
        if ($discountBasisPoints <= 0) {
            return 0;
        }

        return intdiv($amount * min($discountBasisPoints, 10000) + 5000, 10000);
    }
}
