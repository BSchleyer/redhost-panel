<?php

declare(strict_types=1);

namespace Nebrix\Service;

/**
 * Read model of a customer service (nx_services).
 */
final class Service
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $remote
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ?int $productId,
        public readonly ?int $orderItemId,
        public readonly ?int $integrationId,
        public readonly string $category,
        public readonly string $name,
        public readonly string $status,
        public readonly string $billingCycle,
        public readonly int $recurringAmount,
        public readonly int $taxRateBp,
        public readonly string $currency,
        public readonly ?string $nextDueAt,
        public readonly ?string $externalId,
        public readonly array $config,
        public readonly array $remote,
        public readonly bool $hasCredentials,
        public readonly array $attributes,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $decode = static function (mixed $value): array {
            $decoded = is_string($value) ? json_decode($value, true) : null;

            return is_array($decoded) ? $decoded : [];
        };

        return new self(
            (int) $row['id'],
            (int) $row['user_id'],
            isset($row['product_id']) ? (int) $row['product_id'] : null,
            isset($row['order_item_id']) ? (int) $row['order_item_id'] : null,
            isset($row['integration_id']) ? (int) $row['integration_id'] : null,
            (string) $row['category'],
            (string) $row['name'],
            (string) $row['status'],
            (string) $row['billing_cycle'],
            (int) $row['recurring_amount'],
            (int) $row['tax_rate_bp'],
            (string) $row['currency'],
            isset($row['next_due_at']) ? (string) $row['next_due_at'] : null,
            isset($row['external_id']) ? (string) $row['external_id'] : null,
            $decode($row['config'] ?? null),
            $decode($row['remote'] ?? null),
            ($row['credentials'] ?? null) !== null && $row['credentials'] !== '',
            $row,
        );
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function remote(string $key, mixed $default = null): mixed
    {
        return $this->remote[$key] ?? $default;
    }

    /** Customer-chosen order options (hostname, server name, domain). */
    public function option(string $key, mixed $default = null): mixed
    {
        $options = $this->config['_options'] ?? [];

        return is_array($options) ? ($options[$key] ?? $default) : $default;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
