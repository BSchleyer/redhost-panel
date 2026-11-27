<?php

declare(strict_types=1);

namespace Nebrix\Api;

/**
 * Read model of an API token (never contains the secret itself).
 */
final class ApiToken
{
    /**
     * @param list<string> $scopes
     * @param list<string> $allowedIps
     */
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $name,
        public readonly string $prefix,
        public readonly array $scopes,
        public readonly array $allowedIps,
        public readonly string $expiresAt,
        public readonly ?string $lastUsedAt,
        public readonly ?string $lastUsedIp,
        public readonly ?string $revokedAt,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $list = static function (mixed $json): array {
            $decoded = is_string($json) ? json_decode($json, true) : null;

            return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
        };

        return new self(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['name'],
            (string) $row['token_prefix'],
            $list($row['scopes'] ?? null),
            $list($row['allowed_ips'] ?? null),
            (string) $row['expires_at'],
            isset($row['last_used_at']) ? (string) $row['last_used_at'] : null,
            isset($row['last_used_ip']) ? (string) $row['last_used_ip'] : null,
            isset($row['revoked_at']) ? (string) $row['revoked_at'] : null,
            (string) $row['created_at'],
        );
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /** Display form, e.g. "nbx_1a2b3c4d…". */
    public function maskedToken(): string
    {
        return ApiTokenService::PREFIX . $this->prefix . '…';
    }
}
