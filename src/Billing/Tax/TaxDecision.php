<?php

declare(strict_types=1);

namespace Nebrix\Billing\Tax;

/**
 * How an invoice for a customer is taxed.
 *
 * Categories follow EN 16931 (UNCL 5305): S = standard rate, AE = reverse
 * charge, E = exempt (small business), O = outside the scope of VAT.
 */
final class TaxDecision
{
    public const STANDARD = 'S';
    public const REVERSE_CHARGE = 'AE';
    public const EXEMPT = 'E';
    public const OUTSIDE_SCOPE = 'O';

    /**
     * @param int|null $rateBp fixed rate; null = the product's (domestic) rate
     */
    public function __construct(
        public readonly string $category,
        public readonly ?int $rateBp,
        public readonly ?string $note,
        public readonly ?string $country,
    ) {
    }

    public function rate(int $domesticRateBp): int
    {
        return $this->rateBp ?? $domesticRateBp;
    }
}
