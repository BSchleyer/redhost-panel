<?php

declare(strict_types=1);

namespace Nebrix\Billing;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use RuntimeException;

/**
 * Customer credit balance (the prepaid balance of the legacy panel).
 *
 * Every change runs in a transaction with a row lock on the user and is
 * recorded in the append-only nx_credit_ledger, which fixes the legacy
 * read-modify-write race (AUDIT C-08).
 */
class CreditService
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param int $amount positive to add credit, negative to deduct
     * @return int new balance
     *
     * @throws InsufficientCreditException when a deduction exceeds the balance
     */
    /**
     * @param bool $allowNegative only for reversals of money that has already
     *        left (refunded or charged-back top-ups): the balance may become a debt
     */
    public function adjust(int $userId, int $amount, string $reason, ?string $referenceType = null, ?string $referenceId = null, ?int $actorId = null, bool $allowNegative = false): int
    {
        if ($amount === 0) {
            throw new RuntimeException('Credit adjustments must not be zero.');
        }

        return $this->db->transaction(function () use ($userId, $amount, $reason, $referenceType, $referenceId, $actorId, $allowNegative): int {
            $balance = $this->db->fetchValue('SELECT `credit_balance` FROM `nx_users` WHERE `id` = ? FOR UPDATE', [$userId]);
            if ($balance === null) {
                throw new RuntimeException('Customer not found.');
            }

            $newBalance = (int) $balance + $amount;
            if ($newBalance < 0 && !$allowNegative) {
                throw new InsufficientCreditException('The credit balance is not sufficient.');
            }

            $this->db->update('nx_users', ['credit_balance' => $newBalance, 'updated_at' => $this->clock->nowString()], ['id' => $userId]);
            $this->db->insert('nx_credit_ledger', [
                'user_id' => $userId,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reason' => mb_substr($reason, 0, 255),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'actor_id' => $actorId,
                'created_at' => $this->clock->nowString(),
            ]);

            return $newBalance;
        });
    }

    public function balance(int $userId): int
    {
        return (int) $this->db->fetchValue('SELECT `credit_balance` FROM `nx_users` WHERE `id` = ?', [$userId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $userId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT l.*, a.`email` AS actor_email FROM `nx_credit_ledger` l LEFT JOIN `nx_users` a ON a.`id` = l.`actor_id`
             WHERE l.`user_id` = ? ORDER BY l.`id` DESC LIMIT ?',
            [$userId, $limit],
        );
    }
}
