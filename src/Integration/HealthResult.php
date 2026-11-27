<?php

declare(strict_types=1);

namespace Nebrix\Integration;

final class HealthResult
{
    public const HEALTHY = 'healthy';
    public const DEGRADED = 'degraded';
    public const DOWN = 'down';

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly array $details = [],
    ) {
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function healthy(string $message, array $details = []): self
    {
        return new self(self::HEALTHY, $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function degraded(string $message, array $details = []): self
    {
        return new self(self::DEGRADED, $message, $details);
    }

    public static function down(string $message): self
    {
        return new self(self::DOWN, $message);
    }

    public function isHealthy(): bool
    {
        return $this->status === self::HEALTHY;
    }
}
