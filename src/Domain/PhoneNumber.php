<?php

declare(strict_types=1);

namespace Nebrix\Domain;

/**
 * Converts phone numbers to the EPP format required by registries
 * ("+CC.NUMBER", e.g. +49.3012345678).
 */
final class PhoneNumber
{
    /** Country calling codes for national numbers (leading 0). */
    private const CALLING_CODES = [
        'DE' => '49', 'AT' => '43', 'CH' => '41', 'NL' => '31', 'BE' => '32', 'LU' => '352', 'FR' => '33', 'IT' => '39',
        'ES' => '34', 'PT' => '351', 'PL' => '48', 'CZ' => '420', 'SK' => '421', 'HU' => '36', 'DK' => '45', 'SE' => '46',
        'NO' => '47', 'FI' => '358', 'GB' => '44', 'IE' => '353', 'US' => '1', 'CA' => '1', 'LI' => '423', 'GR' => '30',
        'RO' => '40', 'BG' => '359', 'HR' => '385', 'SI' => '386', 'EE' => '372', 'LV' => '371', 'LT' => '370',
    ];

    public static function toEpp(?string $phone, ?string $country): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        // "+49 30 1234567", "+49-30-1234567", "+49.301234567"
        if (preg_match('/^\+(\d{1,3})[\s.\-\/()]+([\d\s.\-\/()]{4,20})$/', $phone, $match) === 1) {
            return self::format($match[1], $match[2]);
        }

        $code = self::CALLING_CODES[strtoupper((string) $country)] ?? null;
        // "+4930123456" without separator: split using the customer's country code.
        if ($code !== null && preg_match('/^\+(\d{6,18})$/', $phone, $match) === 1 && str_starts_with($match[1], $code)) {
            return self::format($code, substr($match[1], strlen($code)));
        }
        // National format "030 1234567".
        if ($code !== null && preg_match('/^0[\d\s.\-\/()]{4,20}$/', $phone) === 1) {
            return self::format($code, substr((string) preg_replace('/\D/', '', $phone), 1));
        }

        return null;
    }

    private static function format(string $code, string $number): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $number);
        if (strlen($digits) < 4 || strlen($digits) > 14) {
            return null;
        }

        return '+' . $code . '.' . ltrim($digits, '0');
    }
}
