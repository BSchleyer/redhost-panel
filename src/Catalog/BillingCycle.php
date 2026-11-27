<?php

declare(strict_types=1);

namespace Nebrix\Catalog;

use DateTimeImmutable;

final class BillingCycle
{
    public const ONE_TIME = 'one_time';
    public const MONTHLY = 'monthly';
    public const QUARTERLY = 'quarterly';
    public const SEMIANNUALLY = 'semiannually';
    public const ANNUALLY = 'annually';

    private const MONTHS = [
        self::MONTHLY => 1,
        self::QUARTERLY => 3,
        self::SEMIANNUALLY => 6,
        self::ANNUALLY => 12,
    ];

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::SEMIANNUALLY => 'Every 6 months',
            self::ANNUALLY => 'Annually',
            self::ONE_TIME => 'One-time',
        ];
    }

    public static function isRecurring(string $cycle): bool
    {
        return isset(self::MONTHS[$cycle]);
    }

    /**
     * Next due date. Month arithmetic is clamped to the last day of the
     * month, so a service started on 31 January renews on 28/29 February.
     */
    public static function advance(DateTimeImmutable $from, string $cycle): DateTimeImmutable
    {
        if (!isset(self::MONTHS[$cycle])) {
            throw new \InvalidArgumentException(sprintf('Billing cycle "%s" is not recurring.', $cycle));
        }

        $months = self::MONTHS[$cycle];
        $day = (int) $from->format('j');
        $firstOfTarget = $from->modify('first day of this month')->modify(sprintf('+%d months', $months));
        $lastDay = (int) $firstOfTarget->format('t');

        return $firstOfTarget->setDate(
            (int) $firstOfTarget->format('Y'),
            (int) $firstOfTarget->format('n'),
            min($day, $lastDay),
        );
    }
}
