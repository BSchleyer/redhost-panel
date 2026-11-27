<?php

declare(strict_types=1);

namespace Nebrix\Repository;

use Nebrix\Auth\User;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

class UserRepository
{
    private const SELECT = 'SELECT u.*, r.`slug` AS role_slug, r.`name` AS role_name, r.`is_staff` AS role_is_staff
        FROM `nx_users` u INNER JOIN `nx_roles` r ON r.`id` = u.`role_id`';

    /** Columns that may be written through create()/update(). */
    private const WRITABLE = [
        'role_id', 'customer_group_id', 'email', 'username', 'password_hash', 'status', 'email_verified_at',
        'first_name', 'last_name', 'company', 'vat_id', 'address_line1', 'address_line2', 'postcode', 'city',
        'country', 'phone', 'language', 'two_factor_secret', 'two_factor_enabled_at', 'two_factor_recovery_codes',
        'two_factor_last_slice', 'password_changed_at', 'last_login_at', 'last_login_ip', 'legal_accepted_at', 'notes', 'legacy_id',
        'auto_pay_credit',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    public function find(int $id): ?User
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE u.`id` = ?', [$id]);

        return $row === null ? null : User::fromRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE u.`email` = ?', [mb_strtolower(trim($email))]);

        return $row === null ? null : User::fromRow($row);
    }

    /** Looks up by e-mail or legacy username (RED-Host allowed both for login). */
    public function findByLogin(string $login): ?User
    {
        $login = trim($login);
        if (str_contains($login, '@')) {
            return $this->findByEmail($login);
        }

        $row = $this->db->fetchOne(self::SELECT . ' WHERE u.`username` = ?', [$login]);

        return $row === null ? null : User::fromRow($row);
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `nx_users` WHERE `email` = ? AND `id` <> ?',
            [mb_strtolower(trim($email)), $exceptId ?? 0],
        ) > 0;
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `nx_users` WHERE `username` = ? AND `id` <> ?',
            [$username, $exceptId ?? 0],
        ) > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data = $this->filter($data);
        $now = $this->clock->nowString();
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        return $this->db->insert('nx_users', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $data = $this->filter($data);
        if ($data === []) {
            return;
        }
        $data['updated_at'] = $this->clock->nowString();
        $this->db->update('nx_users', $data, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::WRITABLE));
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        return $data;
    }

    /**
     * @param array{search?: string, status?: string, role?: string, staff?: bool, group?: int} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (($filters['search'] ?? '') !== '') {
            $where[] = '(u.`email` LIKE ? OR u.`username` LIKE ? OR u.`first_name` LIKE ? OR u.`last_name` LIKE ? OR u.`company` LIKE ? OR u.`id` = ?)';
            $like = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            array_push($params, $like, $like, $like, $like, $like, ctype_digit((string) $filters['search']) ? (int) $filters['search'] : 0);
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'u.`status` = ?';
            $params[] = $filters['status'];
        }
        if (($filters['role'] ?? '') !== '') {
            $where[] = 'r.`slug` = ?';
            $params[] = $filters['role'];
        }
        if (isset($filters['staff'])) {
            $where[] = 'r.`is_staff` = ?';
            $params[] = $filters['staff'] ? 1 : 0;
        }
        if (isset($filters['group'])) {
            $where[] = 'u.`customer_group_id` = ?';
            $params[] = $filters['group'];
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `nx_users` u INNER JOIN `nx_roles` r ON r.`id` = u.`role_id` WHERE ' . $whereSql,
            $params,
        );

        $offset = max(0, ($page - 1) * $perPage);
        $items = $this->db->fetchAll(
            self::SELECT . ' WHERE ' . $whereSql . ' ORDER BY u.`id` DESC LIMIT ? OFFSET ?',
            [...$params, $perPage, $offset],
        );

        return ['items' => $items, 'total' => $total];
    }

    public function countByStatus(string $status, bool $staff = false): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `nx_users` u INNER JOIN `nx_roles` r ON r.`id` = u.`role_id` WHERE u.`status` = ? AND r.`is_staff` = ?',
            [$status, $staff ? 1 : 0],
        );
    }

    public function countCustomers(): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `nx_users` u INNER JOIN `nx_roles` r ON r.`id` = u.`role_id` WHERE r.`is_staff` = 0',
        );
    }

    public function countUsersWithRole(string $slug, ?string $status = null): int
    {
        $sql = 'SELECT COUNT(*) FROM `nx_users` u INNER JOIN `nx_roles` r ON r.`id` = u.`role_id` WHERE r.`slug` = ?';
        $params = [$slug];
        if ($status !== null) {
            $sql .= ' AND u.`status` = ?';
            $params[] = $status;
        }

        return (int) $this->db->fetchValue($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentCustomers(int $limit = 5): array
    {
        return $this->db->fetchAll(
            self::SELECT . ' WHERE r.`is_staff` = 0 ORDER BY u.`created_at` DESC LIMIT ?',
            [$limit],
        );
    }
}
