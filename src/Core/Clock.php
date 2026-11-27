<?php

declare(strict_types=1);

namespace Nebrix\Core;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Source of the current time. All persisted timestamps are UTC.
 *
 * Tests replace the clock with a frozen instance to make billing and expiry
 * logic deterministic.
 */
class Clock
{
    private ?DateTimeImmutable $frozen = null;

    public function now(): DateTimeImmutable
    {
        return $this->frozen ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** Current UTC time formatted for DATETIME columns. */
    public function nowString(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }

    public function freeze(DateTimeImmutable $at): void
    {
        $this->frozen = $at->setTimezone(new DateTimeZone('UTC'));
    }

    public function unfreeze(): void
    {
        $this->frozen = null;
    }
}
