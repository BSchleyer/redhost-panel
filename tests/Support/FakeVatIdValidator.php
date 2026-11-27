<?php

declare(strict_types=1);

namespace Nebrix\Tests\Support;

use Nebrix\Billing\Tax\VatIdValidator;

/**
 * VIES stand-in for tests (no network): IDs in $valid are valid, $down
 * simulates an unavailable service, everything else is invalid.
 */
final class FakeVatIdValidator extends VatIdValidator
{
    /** @var list<string> */
    public array $valid = ['ATU12345678', 'FR12345678901', 'DE123456789'];

    public bool $down = false;

    /** @var list<string> */
    public array $checked = [];

    public function __construct()
    {
    }

    public function check(string $vatId): array
    {
        $parsed = self::parse($vatId);
        $normalized = $parsed !== null ? $parsed[0] . $parsed[1] : $vatId;
        $this->checked[] = $normalized;
        if ($this->down) {
            return ['status' => self::UNAVAILABLE, 'name' => null];
        }

        return in_array($normalized, $this->valid, true)
            ? ['status' => self::VALID, 'name' => 'Example Trading GmbH']
            : ['status' => self::INVALID, 'name' => null];
    }
}
