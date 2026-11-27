<?php

declare(strict_types=1);

namespace Nebrix\Billing;

use Nebrix\Payment\GatewayRegistry;
use Nebrix\Repository\UserRepository;
use Nebrix\Settings\SettingsRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Pays open renewal invoices automatically from the customer's account
 * credit (setting billing.auto_pay_credit and the customer's preference).
 *
 * Invoices are paid at the earliest one day before their due date (not when
 * the renewal invoice is created), so a customer who cancels shortly before
 * the end of the term is not charged for the next period.
 *
 * Uses the regular credit gateway, so the invoice is locked, the balance is
 * checked inside the transaction and the payment is recorded exactly like a
 * manual credit payment (no double spending, payment confirmation e-mail).
 */
class AutoPayment
{
    public function __construct(
        private readonly \Nebrix\Database\Connection $db,
        private readonly SettingsRepository $settings,
        private readonly GatewayRegistry $gateways,
        private readonly UserRepository $users,
        private readonly LoggerInterface $logger,
        private readonly \Nebrix\Core\Clock $clock,
    ) {
    }

    /**
     * @param int|null $invoiceId only this invoice (e.g. right after it was created)
     * @return int number of invoices paid
     */
    public function run(?int $invoiceId = null): int
    {
        if (!$this->settings->bool('billing.auto_pay_credit')) {
            return 0;
        }
        $gateway = $this->gateways->get('credit');
        if ($gateway === null || !$gateway->isEnabled()) {
            return 0;
        }

        // Never for services that are cancelled or have ended: an open claim
        // for such a service is settled by the customer or the staff explicitly.
        $sql = "SELECT i.* FROM `nx_invoices` i JOIN `nx_users` u ON u.`id` = i.`user_id`
                LEFT JOIN `nx_services` s ON s.`id` = i.`service_id`
                WHERE i.`type` = 'renewal' AND i.`status` = 'unpaid' AND i.`total` > 0
                  AND u.`auto_pay_credit` = 1 AND u.`status` = 'active' AND u.`credit_balance` >= i.`total` AND i.`due_at` <= ?
                  AND (i.`service_id` IS NULL OR (s.`status` IN ('active', 'suspended') AND s.`cancel_at` IS NULL))";
        $params = [$this->clock->now()->modify('+1 day')->format('Y-m-d H:i:s')];
        if ($invoiceId !== null) {
            $sql .= ' AND i.`id` = ?';
            $params[] = $invoiceId;
        }

        $paid = 0;
        foreach ($this->db->fetchAll($sql . ' ORDER BY i.`due_at`, i.`id` LIMIT 500', $params) as $invoice) {
            $user = $this->users->find((int) $invoice['user_id']);
            if ($user === null || !$gateway->isAvailableFor($invoice, $user)) {
                continue;
            }
            try {
                $outcome = $gateway->start($invoice, $user, '', '');
            } catch (Throwable $exception) {
                $this->logger->error('Automatic credit payment failed', ['invoice' => $invoice['id'], 'message' => $exception->getMessage()]);
                continue;
            }
            if ($outcome instanceof \Nebrix\Payment\PaymentOutcome && $outcome->isSuccessful()) {
                $paid++;
            }
        }

        return $paid;
    }
}
