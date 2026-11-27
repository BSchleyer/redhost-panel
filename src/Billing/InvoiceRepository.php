<?php

declare(strict_types=1);

namespace Nebrix\Billing;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

class InvoiceRepository
{
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    public const TYPE_ORDER = 'order';
    public const TYPE_RENEWAL = 'renewal';
    public const TYPE_CREDIT = 'credit';
    /** Cancellation or correction document referring to an invoice (positive amounts). */
    public const TYPE_CREDIT_NOTE = 'credit_note';
    /** Status of correction documents (they are not paid, they are issued). */
    public const STATUS_ISSUED = 'issued';

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM `nx_invoices` WHERE `id` = ?', [$id]);
    }

    /**
     * Ownership-scoped lookup for customer requests (IDOR protection).
     *
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM `nx_invoices` WHERE `id` = ? AND `user_id` = ?', [$id, $userId]);
    }

    /**
     * Locks the invoice row for the rest of the transaction.
     *
     * @return array<string, mixed>|null
     */
    public function lock(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM `nx_invoices` WHERE `id` = ? FOR UPDATE', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $invoiceId): array
    {
        return $this->db->fetchAll('SELECT * FROM `nx_invoice_items` WHERE `invoice_id` = ? ORDER BY `id`', [$invoiceId]);
    }

    /**
     * @param array<string, mixed> $invoice
     * @param list<array<string, mixed>> $items
     */
    public function create(array $invoice, array $items): int
    {
        $now = $this->clock->nowString();
        $id = $this->db->insert('nx_invoices', $invoice + ['created_at' => $now, 'updated_at' => $now]);
        foreach ($items as $item) {
            $this->db->insert('nx_invoice_items', $item + ['invoice_id' => $id]);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $data['updated_at'] = $this->clock->nowString();
        $this->db->update('nx_invoices', $data, ['id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function openRenewalFor(int $serviceId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `nx_invoices` WHERE `service_id` = ? AND `type` = 'renewal' AND `status` = 'unpaid' ORDER BY `id` DESC LIMIT 1",
            [$serviceId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId, int $limit = 100): array
    {
        return $this->db->fetchAll('SELECT * FROM `nx_invoices` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT ?', [$userId, $limit]);
    }

    /**
     * @param array{status?: string, search?: string, type?: string, user_id?: int} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'i.`status` = ?';
            $params[] = $filters['status'];
        }
        if (($filters['type'] ?? '') !== '') {
            $where[] = 'i.`type` = ?';
            $params[] = $filters['type'];
        }
        if (isset($filters['user_id'])) {
            $where[] = 'i.`user_id` = ?';
            $params[] = $filters['user_id'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(i.`number` LIKE ? OR u.`email` LIKE ?)';
            $like = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $whereSql = implode(' AND ', $where);

        return [
            'items' => $this->db->fetchAll(
                'SELECT i.*, u.`email` FROM `nx_invoices` i JOIN `nx_users` u ON u.`id` = i.`user_id` WHERE ' . $whereSql . ' ORDER BY i.`id` DESC LIMIT ? OFFSET ?',
                [...$params, $perPage, max(0, ($page - 1) * $perPage)],
            ),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_invoices` i JOIN `nx_users` u ON u.`id` = i.`user_id` WHERE ' . $whereSql, $params),
        ];
    }
}
