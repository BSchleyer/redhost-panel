<?php

declare(strict_types=1);

namespace Nebrix\Service;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Security\Encrypter;

class ServiceRepository
{
    private const WRITABLE = [
        'user_id', 'product_id', 'order_item_id', 'integration_id', 'category', 'name', 'status', 'billing_cycle',
        'recurring_amount', 'tax_rate_bp', 'currency', 'next_due_at', 'external_id', 'config', 'suspension_reason',
        'last_error', 'provisioned_at', 'suspended_at', 'terminated_at', 'cancelled_at', 'cancel_at', 'cancellation_id',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly Encrypter $encrypter,
        private readonly Clock $clock,
    ) {
    }

    public function find(int $id): ?Service
    {
        $row = $this->db->fetchOne('SELECT * FROM `nx_services` WHERE `id` = ?', [$id]);

        return $row === null ? null : Service::fromRow($row);
    }

    /** Ownership-scoped lookup used by every customer-facing request (IDOR protection). */
    public function findForUser(int $id, int $userId): ?Service
    {
        $row = $this->db->fetchOne('SELECT * FROM `nx_services` WHERE `id` = ? AND `user_id` = ?', [$id, $userId]);

        return $row === null ? null : Service::fromRow($row);
    }

    public function findByOrderItem(int $orderItemId): ?Service
    {
        $row = $this->db->fetchOne('SELECT * FROM `nx_services` WHERE `order_item_id` = ?', [$orderItemId]);

        return $row === null ? null : Service::fromRow($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data = $this->filter($data);
        $now = $this->clock->nowString();

        return $this->db->insert('nx_services', $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $data = $this->filter($data);
        $data['updated_at'] = $this->clock->nowString();
        $this->db->update('nx_services', $data, ['id' => $id]);
    }

    /**
     * Changes the status only if it still has the expected value (atomic
     * guard against concurrent requests). Returns false if it had changed.
     */
    public function transitionStatus(int $id, string $from, string $to): bool
    {
        return $this->db->run(
            'UPDATE `nx_services` SET `status` = ?, `last_error` = NULL, `updated_at` = ? WHERE `id` = ? AND `status` = ?',
            [$to, $this->clock->nowString(), $id, $from],
        )->rowCount() === 1;
    }

    /**
     * Merges keys into the remote state. Called before and after every
     * remote side effect so a retried job can continue where it stopped.
     *
     * @param array<string, mixed> $changes null values remove keys
     */
    public function mergeRemote(int $id, array $changes): void
    {
        $this->db->transaction(function () use ($id, $changes): void {
            $current = $this->db->fetchValue('SELECT `remote` FROM `nx_services` WHERE `id` = ? FOR UPDATE', [$id]);
            $remote = is_string($current) ? (json_decode($current, true) ?: []) : [];
            foreach ($changes as $key => $value) {
                if ($value === null) {
                    unset($remote[$key]);
                } else {
                    $remote[$key] = $value;
                }
            }
            $this->db->update('nx_services', [
                'remote' => json_encode($remote, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => $this->clock->nowString(),
            ], ['id' => $id]);
        });
    }

    /**
     * Integer values of one remote-state key (e.g. a reserved voice port) held
     * by the other live services of an integration.
     *
     * @return list<int>
     */
    public function reservedRemoteValues(int $integrationId, string $key, int $exceptServiceId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT `remote` FROM `nx_services` WHERE `integration_id` = ? AND `id` <> ? AND `status` NOT IN ('terminated', 'cancelled') AND `remote` IS NOT NULL",
            [$integrationId, $exceptServiceId],
        );
        $values = [];
        foreach ($rows as $row) {
            $remote = json_decode((string) $row['remote'], true);
            if (is_array($remote) && isset($remote[$key]) && is_numeric($remote[$key])) {
                $values[] = (int) $remote[$key];
            }
        }

        return $values;
    }

    /**
     * Stores access data encrypted (never in plaintext, AUDIT H-08).
     *
     * @param array<string, scalar|null> $credentials
     */
    public function setCredentials(int $id, #[\SensitiveParameter] array $credentials): void
    {
        $this->db->update('nx_services', [
            'credentials' => $credentials === [] ? null : $this->encrypter->encryptArray($credentials, 'service:' . $id),
            'updated_at' => $this->clock->nowString(),
        ], ['id' => $id]);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function credentials(Service $service): array
    {
        $encrypted = $service->attributes['credentials'] ?? null;
        if (!is_string($encrypted) || $encrypted === '') {
            return [];
        }

        /** @var array<string, scalar|null> */
        return $this->encrypter->decryptArray($encrypted, 'service:' . $service->id);
    }

    /**
     * @return list<Service>
     */
    public function forUser(int $userId): array
    {
        return array_map(
            Service::fromRow(...),
            $this->db->fetchAll("SELECT * FROM `nx_services` WHERE `user_id` = ? AND `status` <> 'cancelled' ORDER BY FIELD(`status`, 'active', 'provisioning', 'pending', 'suspended', 'failed', 'terminated'), `id` DESC", [$userId]),
        );
    }

    /**
     * @param array{status?: string, category?: string, search?: string, integration?: int, user_id?: int} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        [$whereSql, $params, $from] = $this->filterSql($filters);

        return [
            'items' => $this->db->fetchAll(
                'SELECT s.`id`, s.`user_id`, s.`name`, s.`category`, s.`status`, s.`next_due_at`, s.`recurring_amount`, s.`currency`, s.`billing_cycle`, s.`created_at`, s.`last_error`,
                        u.`email`, p.`name` AS product_name, i.`name` AS integration_name ' . $from . ' WHERE ' . $whereSql . ' ORDER BY s.`id` DESC LIMIT ? OFFSET ?',
                [...$params, $perPage, max(0, ($page - 1) * $perPage)],
            ),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) ' . $from . ' WHERE ' . $whereSql, $params),
        ];
    }

    /**
     * Same filters as paginate(), returning service models (REST API).
     *
     * @param array{status?: string, category?: string, search?: string, integration?: int, user_id?: int} $filters
     * @return array{items: list<Service>, total: int}
     */
    public function paginateModels(array $filters, int $page, int $perPage = 25): array
    {
        [$whereSql, $params, $from] = $this->filterSql($filters);

        return [
            'items' => array_map(Service::fromRow(...), $this->db->fetchAll(
                'SELECT s.* ' . $from . ' WHERE ' . $whereSql . ' ORDER BY s.`id` DESC LIMIT ? OFFSET ?',
                [...$params, $perPage, max(0, ($page - 1) * $perPage)],
            )),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) ' . $from . ' WHERE ' . $whereSql, $params),
        ];
    }

    /**
     * @param array{status?: string, category?: string, search?: string, integration?: int, user_id?: int} $filters
     * @return array{0: string, 1: list<mixed>, 2: string}
     */
    private function filterSql(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $where[] = 's.`status` = ?';
            $params[] = $filters['status'];
        }
        if (($filters['category'] ?? '') !== '') {
            $where[] = 's.`category` = ?';
            $params[] = $filters['category'];
        }
        if (isset($filters['integration'])) {
            $where[] = 's.`integration_id` = ?';
            $params[] = $filters['integration'];
        }
        if (isset($filters['user_id'])) {
            $where[] = 's.`user_id` = ?';
            $params[] = $filters['user_id'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(s.`name` LIKE ? OR u.`email` LIKE ? OR s.`id` = ?)';
            $like = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            array_push($params, $like, $like, ctype_digit((string) $filters['search']) ? (int) $filters['search'] : 0);
        }
        $from = 'FROM `nx_services` s JOIN `nx_users` u ON u.`id` = s.`user_id` LEFT JOIN `nx_products` p ON p.`id` = s.`product_id` LEFT JOIN `nx_integrations` i ON i.`id` = s.`integration_id`';

        return [implode(' AND ', $where), $params, $from];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::WRITABLE));
        if (isset($data['config']) && is_array($data['config'])) {
            $data['config'] = json_encode($data['config'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $data;
    }
}
