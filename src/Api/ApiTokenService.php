<?php

declare(strict_types=1);

namespace Nebrix\Api;

use InvalidArgumentException;
use Nebrix\Audit\AuditLogger;
use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Http\IpRange;
use Nebrix\Mail\Mailer;
use Nebrix\Repository\UserRepository;

/**
 * Personal access tokens for the REST API.
 *
 * Format: "nbx_" + 48 hex characters (192 random bits). Only the SHA-256
 * hash is stored; the token is shown once at creation. Every token has
 * scopes, an expiry date (max. one year) and an optional IP allowlist.
 */
class ApiTokenService
{
    public const PREFIX = 'nbx_';
    public const MAX_ACTIVE_PER_USER = 20;
    /** Allowed lifetimes in days. */
    public const LIFETIMES = [7, 30, 90, 180, 365];
    private const TOUCH_INTERVAL = 60;

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly Gate $gate,
        private readonly AuditLogger $audit,
        private readonly UserRepository $users,
        private readonly Mailer $mailer,
        private readonly Config $config,
    ) {
    }

    /**
     * @param list<string> $scopes
     * @param list<string> $allowedIps addresses or CIDR ranges
     * @return array{token: ApiToken, secret: string}
     *
     * @throws InvalidArgumentException with a user-facing message
     */
    public function create(User $user, string $name, array $scopes, int $days, array $allowedIps, ?string $ip): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Enter a name of up to 100 characters.');
        }
        $scopes = array_values(array_unique($scopes));
        if ($scopes === []) {
            throw new InvalidArgumentException('Choose at least one permission.');
        }
        $available = ApiScope::availableFor($user, $this->gate);
        foreach ($scopes as $scope) {
            if (!isset($available[$scope])) {
                throw new InvalidArgumentException('One of the chosen permissions is not available for your account.');
            }
        }
        if (!in_array($days, self::LIFETIMES, true)) {
            throw new InvalidArgumentException('Choose one of the offered lifetimes.');
        }
        $allowedIps = array_values(array_unique(array_filter(array_map('trim', $allowedIps), static fn (string $v): bool => $v !== '')));
        if (count($allowedIps) > 20) {
            throw new InvalidArgumentException('Enter at most 20 IP addresses or ranges.');
        }
        foreach ($allowedIps as $range) {
            if (!self::isValidRange($range)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a valid IP address or CIDR range.', mb_substr($range, 0, 50)));
            }
        }

        $created = $this->db->transaction(function () use ($user, $name, $scopes, $days, $allowedIps, $ip): array {
            // Lock the user row so that concurrent requests cannot exceed the limit.
            $this->db->fetchValue('SELECT `id` FROM `nx_users` WHERE `id` = ? FOR UPDATE', [$user->id]);
            if ($this->countActive($user->id) >= self::MAX_ACTIVE_PER_USER) {
                throw new InvalidArgumentException(sprintf('You can have at most %d active API tokens. Revoke one first.', self::MAX_ACTIVE_PER_USER));
            }

            $random = bin2hex(random_bytes(24));
            $secret = self::PREFIX . $random;
            $now = $this->clock->now();
            $id = $this->db->insert('nx_api_tokens', [
                'user_id' => $user->id,
                'name' => $name,
                'token_prefix' => substr($random, 0, 8),
                'token_hash' => hash('sha256', $secret),
                'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR),
                'allowed_ips' => $allowedIps === [] ? null : json_encode($allowedIps, JSON_THROW_ON_ERROR),
                'expires_at' => $now->modify('+' . $days . ' days')->format('Y-m-d H:i:s'),
                'created_ip' => $ip,
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            $this->audit->log($user->id, 'api_token.created', 'api_token', $id, ['name' => $name, 'scopes' => $scopes, 'days' => $days, 'ip_restricted' => $allowedIps !== []], $ip);

            return ['token' => $this->find($id) ?? throw new \RuntimeException('Token not stored.'), 'secret' => $secret];
        });

        // The owner learns about every new token (a hijacked session could otherwise mint one unnoticed).
        $this->mailer->queue($user->email, $user->displayName(), 'api_token_created', [
            'name' => $user->displayName(),
            'token_name' => $name,
            'scopes' => implode(', ', $scopes),
            'expires' => $created['token']->expiresAt . ' UTC',
            'ip' => (string) $ip,
            'tokens_url' => rtrim($this->config->string('app.url'), '/') . '/account/api-tokens',
        ]);

        return $created;
    }

    /**
     * Resolves a presented token. Returns null for unknown, malformed,
     * revoked or expired tokens, a client address outside the allowlist and
     * owners that are no longer active.
     *
     * @return array{0: ApiToken, 1: User}|null
     */
    public function authenticate(string $secret, string $clientIp): ?array
    {
        if (preg_match('/^' . self::PREFIX . '[a-f0-9]{48}$/', $secret) !== 1) {
            return null;
        }
        $now = $this->clock->nowString();
        $row = $this->db->fetchOne(
            'SELECT * FROM `nx_api_tokens` WHERE `token_hash` = ? AND `revoked_at` IS NULL AND `expires_at` > ?',
            [hash('sha256', $secret), $now],
        );
        if ($row === null) {
            return null;
        }
        $token = ApiToken::fromRow($row);

        if ($token->allowedIps !== []) {
            $allowed = false;
            foreach ($token->allowedIps as $range) {
                $allowed = $allowed || IpRange::contains($range, $clientIp);
            }
            if (!$allowed) {
                return null;
            }
        }

        $user = $this->users->find($token->userId);
        if ($user === null || !$user->isActive()) {
            return null;
        }

        if ($token->lastUsedAt === null || strtotime($token->lastUsedAt . ' UTC') < $this->clock->now()->getTimestamp() - self::TOUCH_INTERVAL || $token->lastUsedIp !== $clientIp) {
            $this->db->update('nx_api_tokens', ['last_used_at' => $now, 'last_used_ip' => mb_substr($clientIp, 0, 45)], ['id' => $token->id]);
        }

        return [$token, $user];
    }

    public function find(int $id): ?ApiToken
    {
        $row = $this->db->fetchOne('SELECT * FROM `nx_api_tokens` WHERE `id` = ?', [$id]);

        return $row === null ? null : ApiToken::fromRow($row);
    }

    /**
     * @return list<ApiToken>
     */
    public function forUser(int $userId): array
    {
        return array_map(ApiToken::fromRow(...), $this->db->fetchAll(
            'SELECT * FROM `nx_api_tokens` WHERE `user_id` = ? ORDER BY `revoked_at` IS NOT NULL, `id` DESC LIMIT 100',
            [$userId],
        ));
    }

    public function countActive(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `nx_api_tokens` WHERE `user_id` = ? AND `revoked_at` IS NULL AND `expires_at` > ?',
            [$userId, $this->clock->nowString()],
        );
    }

    public function revoke(ApiToken $token, ?int $actorId, ?string $ip): bool
    {
        $revoked = $this->db->run(
            'UPDATE `nx_api_tokens` SET `revoked_at` = ? WHERE `id` = ? AND `revoked_at` IS NULL',
            [$this->clock->nowString(), $token->id],
        )->rowCount() === 1;
        if ($revoked) {
            $this->audit->log($actorId, 'api_token.revoked', 'api_token', $token->id, ['owner' => $token->userId, 'name' => $token->name], $ip);
        }

        return $revoked;
    }

    public function revokeAllForUser(int $userId, ?int $actorId, ?string $ip): int
    {
        $count = $this->db->run(
            'UPDATE `nx_api_tokens` SET `revoked_at` = ? WHERE `user_id` = ? AND `revoked_at` IS NULL',
            [$this->clock->nowString(), $userId],
        )->rowCount();
        if ($count > 0) {
            $this->audit->log($actorId, 'api_token.revoked_all', 'user', $userId, ['count' => $count], $ip);
        }

        return $count;
    }

    /**
     * @param array{search?: string, state?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = ['1 = 1'];
        $params = [];
        $now = $this->clock->nowString();
        if (($filters['state'] ?? '') === 'active') {
            $where[] = 't.`revoked_at` IS NULL AND t.`expires_at` > ?';
            $params[] = $now;
        } elseif (($filters['state'] ?? '') === 'inactive') {
            $where[] = '(t.`revoked_at` IS NOT NULL OR t.`expires_at` <= ?)';
            $params[] = $now;
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(u.`email` LIKE ? OR t.`name` LIKE ? OR t.`token_prefix` = ?)';
            $like = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            array_push($params, $like, $like, (string) $filters['search']);
        }
        $whereSql = implode(' AND ', $where);
        $from = 'FROM `nx_api_tokens` t JOIN `nx_users` u ON u.`id` = t.`user_id`';

        return [
            'items' => $this->db->fetchAll(
                'SELECT t.*, u.`email` ' . $from . ' WHERE ' . $whereSql . ' ORDER BY t.`id` DESC LIMIT ? OFFSET ?',
                [...$params, $perPage, max(0, ($page - 1) * $perPage)],
            ),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) ' . $from . ' WHERE ' . $whereSql, $params),
        ];
    }

    /** Removes idempotency records older than 24 hours (scheduler cleanup). */
    public function pruneIdempotency(): int
    {
        return $this->db->run(
            'DELETE FROM `nx_api_idempotency` WHERE `created_at` < ?',
            [$this->clock->now()->modify('-1 day')->format('Y-m-d H:i:s')],
        )->rowCount();
    }

    public static function isValidRange(string $range): bool
    {
        [$address, $bits] = array_pad(explode('/', $range, 2), 2, null);
        $binary = @inet_pton((string) $address);
        if ($binary === false || filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($bits === null) {
            return true;
        }

        return ctype_digit($bits) && (int) $bits >= (strlen($binary) === 4 ? 8 : 16) && (int) $bits <= strlen($binary) * 8;
    }
}
