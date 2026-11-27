<?php

declare(strict_types=1);

namespace Nebrix\Auth;

/**
 * Read model of a row from nx_users joined with its role.
 */
final class User
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_CLOSED];

    /**
     * @param array<string, mixed> $attributes full database row
     */
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly ?string $username,
        public readonly string $passwordHash,
        public readonly int $roleId,
        public readonly string $roleSlug,
        public readonly string $roleName,
        public readonly bool $roleIsStaff,
        public readonly string $status,
        public readonly ?string $emailVerifiedAt,
        public readonly ?string $twoFactorEnabledAt,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?int $customerGroupId,
        public readonly int $creditBalance,
        public readonly array $attributes,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['email'],
            isset($row['username']) ? (string) $row['username'] : null,
            (string) $row['password_hash'],
            (int) $row['role_id'],
            (string) ($row['role_slug'] ?? ''),
            (string) ($row['role_name'] ?? ''),
            (bool) ($row['role_is_staff'] ?? false),
            (string) $row['status'],
            isset($row['email_verified_at']) ? (string) $row['email_verified_at'] : null,
            isset($row['two_factor_enabled_at']) ? (string) $row['two_factor_enabled_at'] : null,
            isset($row['first_name']) ? (string) $row['first_name'] : null,
            isset($row['last_name']) ? (string) $row['last_name'] : null,
            isset($row['customer_group_id']) ? (int) $row['customer_group_id'] : null,
            (int) ($row['credit_balance'] ?? 0),
            $row,
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isStaff(): bool
    {
        return $this->roleIsStaff;
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleSlug === Role::SUPER_ADMIN;
    }

    public function hasTwoFactor(): bool
    {
        return $this->twoFactorEnabledAt !== null;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function displayName(): string
    {
        $name = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
        if ($name !== '') {
            return $name;
        }

        return $this->username ?? $this->email;
    }

    public function attribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }
}
