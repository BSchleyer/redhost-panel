<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Billing\OrderRepository;
use Nebrix\Catalog\BillingCycle;
use Nebrix\Catalog\ProductCategory;
use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Mail\Mailer;
use Nebrix\Queue\JobQueue;
use Nebrix\Queue\PermanentJobFailure;
use Nebrix\Repository\ProductRepository;
use Nebrix\Repository\UserRepository;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use PDOException;

/**
 * Executes provisioning work inside background jobs.
 */
class ProvisioningService
{
    public const JOB_PROVISION = 'service.provision';
    public const JOB_SUSPEND = 'service.suspend';
    public const JOB_UNSUSPEND = 'service.unsuspend';
    public const JOB_TERMINATE = 'service.terminate';

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ProductRepository $products,
        private readonly ServiceRepository $services,
        private readonly UserRepository $users,
        private readonly ProvisionerRegistry $provisioners,
        private readonly JobQueue $jobs,
        private readonly Mailer $mailer,
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly Config $config,
    ) {
    }

    /**
     * Creates one service per paid order item (idempotent through the
     * unique order_item_id) and queues automatic provisioning.
     */
    public function fulfilOrder(int $orderId): void
    {
        $order = $this->orders->find($orderId);
        if ($order === null || $order['status'] !== OrderRepository::STATUS_PAID) {
            throw new PermanentJobFailure('Order is not paid.');
        }

        foreach ($this->orders->items($orderId) as $item) {
            $service = $this->services->findByOrderItem((int) $item['id']) ?? $this->createService($order, $item);
            if ($service === null) {
                continue;
            }

            $product = $service->productId !== null ? $this->products->find($service->productId) : null;
            $automatic = $product !== null && (bool) $product['auto_provision'] && $service->integrationId !== null && $this->provisioners->supports($service->category);

            if ($automatic && in_array($service->status, [Service::STATUS_PENDING, Service::STATUS_PROVISIONING], true)) {
                $this->jobs->dispatch(self::JOB_PROVISION, ['service_id' => $service->id], self::JOB_PROVISION . ':' . $service->id);
            }
        }
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $item
     */
    private function createService(array $order, array $item): ?Service
    {
        $product = $item['product_id'] !== null ? $this->products->find((int) $item['product_id']) : null;
        if ($product === null) {
            return null;
        }

        $options = is_array($item['options']) ? $item['options'] : [];
        $cycle = (string) $item['billing_cycle'];
        $now = $this->clock->now();

        try {
            $id = $this->services->create([
                'user_id' => (int) $order['user_id'],
                'product_id' => (int) $product['id'],
                'order_item_id' => (int) $item['id'],
                'integration_id' => $product['integration_id'] !== null ? (int) $product['integration_id'] : null,
                'category' => (string) $product['category'],
                'name' => mb_substr((string) ($options['hostname'] ?? $options['server_name'] ?? $options['domain'] ?? $product['name']), 0, 150),
                'status' => Service::STATUS_PENDING,
                'billing_cycle' => $cycle,
                'recurring_amount' => BillingCycle::isRecurring($cycle) ? (int) $item['unit_price'] - (int) $item['discount'] : 0,
                'tax_rate_bp' => (int) $item['tax_rate_bp'],
                'currency' => (string) $order['currency'],
                'next_due_at' => BillingCycle::isRecurring($cycle) ? BillingCycle::advance($now, $cycle)->format('Y-m-d H:i:s') : null,
                'config' => $product['provisioning_config'] + ['_options' => $options],
            ]);
        } catch (PDOException $exception) {
            // A concurrent run created it first (unique order_item_id).
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->services->findByOrderItem((int) $item['id']);
            }

            throw $exception;
        }

        return $this->services->find($id);
    }

    public function provision(int $serviceId): void
    {
        $service = $this->services->find($serviceId) ?? throw new PermanentJobFailure('Service not found.');
        if (in_array($service->status, [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED, Service::STATUS_TERMINATED, Service::STATUS_CANCELLED], true)) {
            return; // already done – idempotent
        }

        $owner = $this->users->find($service->userId) ?? throw new PermanentJobFailure('Customer not found.');
        $this->services->update($serviceId, ['status' => Service::STATUS_PROVISIONING]);

        $result = $this->provisioners->for($service->category)->provision($this->services->find($serviceId) ?? $service, $owner);

        $this->db->transaction(function () use ($serviceId, $result): void {
            if ($result->remote !== []) {
                $this->services->mergeRemote($serviceId, $result->remote);
            }
            if ($result->credentials !== []) {
                $this->services->setCredentials($serviceId, $result->credentials);
            }
            $this->services->update($serviceId, [
                'status' => Service::STATUS_ACTIVE,
                'external_id' => $result->externalId,
                'provisioned_at' => $this->clock->nowString(),
                'last_error' => null,
            ]);
        });

        $this->mailer->queue($owner->email, $owner->displayName(), 'service_provisioned', [
            'name' => $owner->displayName(),
            'service_name' => $service->name,
            'service_url' => rtrim($this->config->string('app.url'), '/') . '/services/' . $serviceId,
        ]);
    }

    public function provisioningFailed(int $serviceId, string $error): void
    {
        $this->services->update($serviceId, ['status' => Service::STATUS_FAILED, 'last_error' => mb_substr($error, 0, 2000)]);
    }

    public function suspend(int $serviceId): void
    {
        $service = $this->services->find($serviceId) ?? throw new PermanentJobFailure('Service not found.');
        if ($service->status === Service::STATUS_SUSPENDED && $this->provisioners->supportsService($service) && $service->externalId !== null) {
            $this->provisioners->for($service->category)->suspend($service);
        }
    }

    public function unsuspend(int $serviceId): void
    {
        $service = $this->services->find($serviceId) ?? throw new PermanentJobFailure('Service not found.');
        if ($service->status !== Service::STATUS_SUSPENDED) {
            return;
        }
        if ($this->provisioners->supportsService($service) && $service->externalId !== null) {
            $this->provisioners->for($service->category)->unsuspend($service);
        }
        $this->services->update($serviceId, ['status' => Service::STATUS_ACTIVE, 'suspended_at' => null, 'suspension_reason' => null]);
    }

    public function terminate(int $serviceId): void
    {
        $service = $this->services->find($serviceId) ?? throw new PermanentJobFailure('Service not found.');
        if ($service->status === Service::STATUS_TERMINATED) {
            return;
        }
        if ($this->provisioners->supportsService($service) && ($service->externalId !== null || $service->remote !== [])) {
            $this->provisioners->for($service->category)->terminate($service);
        }

        $this->services->update($serviceId, ['status' => Service::STATUS_TERMINATED, 'terminated_at' => $this->clock->nowString()]);
        if ($service->productId !== null) {
            $this->products->releaseStock($service->productId);
        }
    }

    public function isAutomated(string $category): bool
    {
        return $this->provisioners->supports($category) && ProductCategory::get($category)['integration'] !== null;
    }
}
