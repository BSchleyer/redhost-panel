<?php

declare(strict_types=1);

namespace Nebrix\Http;

/**
 * CIDR helpers for IPv4 and IPv6.
 */
final class IpRange
{
    /**
     * @param string $range single address or CIDR notation (e.g. 10.0.0.0/8, fd00::/8)
     */
    public static function contains(string $range, string $ip): bool
    {
        $ipBinary = @inet_pton($ip);
        if ($ipBinary === false) {
            return false;
        }

        if (!str_contains($range, '/')) {
            $rangeBinary = @inet_pton($range);

            return $rangeBinary !== false && $rangeBinary === $ipBinary;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $subnetBinary = @inet_pton($subnet);
        if ($subnetBinary === false || !ctype_digit($bits) || strlen($subnetBinary) !== strlen($ipBinary)) {
            return false;
        }

        $bits = (int) $bits;
        $maxBits = strlen($ipBinary) * 8;
        if ($bits > $maxBits) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if (substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
    }
}
