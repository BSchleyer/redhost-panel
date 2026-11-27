<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Audit\AuditLogger;
use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Mail\Mailer;
use Nebrix\Queue\JobQueue;
use Nebrix\Repository\UserRepository;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;

/**
 * Service state changes requested by staff, customers or the scheduler.
 * The local state changes immediately; remote work runs in queued jobs.
 */
class ServiceManager
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly UserRepository $users,
        private readonly JobQueue $jobs,
        private readonly ProvisionerRegistry $provisioners,
        private readonly AuditLogger $audit,
        private readonly Mailer $mailer,
        private readonly Clock $clock,
        private readonly Config $config,
    ) {
    }

    public function suspend(Service $service, string $reason, ?int $actorId, bool $notify = true): void
    {
        if ($service->status !== Service::STATUS_ACTIVE) {
            throw new ServiceStateException('Only active services can be suspended.');
        }

        $this->services->update($service->id, ['status' => Service::STATUS_SUSPENDED, 'suspended_at' => $this->clock->nowString(), 'suspension_reason' => mb_substr($reason, 0, 255)]);
        $this->jobs->dispatch(ProvisioningService::JOB_SUSPEND, ['service_id' => $service->id], ProvisioningService::JOB_SUSPEND . ':' . $service->id . ':' . $this->clock->now()->getTimestamp());
        $this->audit->log($actorId, 'service.suspended', 'service', $service->id, ['reason' => $reason]);

        $owner = $this->users->find($service->userId);
        if ($notify && $owner !== null) {
            $this->mailer->queue($owner->email, $owner->displayName(), 'service_suspended', [
                'name' => $owner->displayName(),
                'service_name' => $service->name,
                'reason' => $reason === 'overdue' ? 'The renewal invoice has not been paid.' : $reason,
                'service_url' => rtrim($this->config->string('app.url'), '/') . '/services/' . $service->id,
            ]);
        }
    }

    public function unsuspend(Service $service, ?int $actorId): void
    {
        if ($service->status !== Service::STATUS_SUSPENDED) {
            throw new ServiceStateException('Only suspended services can be reactivated.');
        }

        $this->jobs->dispatch(ProvisioningService::JOB_UNSUSPEND, ['service_id' => $service->id], ProvisioningService::JOB_UNSUSPEND . ':' . $service->id . ':' . $this->clock->now()->getTimestamp());
        $this->audit->log($actorId, 'service.unsuspend_requested', 'service', $service->id);
    }

    public function terminate(Service $service, ?int $actorId, string $reason): void
    {
        if (in_array($service->status, [Service::STATUS_TERMINATED, Service::STATUS_CANCELLED], true)) {
            throw new ServiceStateException('The service is already terminated.');
        }

        $this->jobs->dispatch(ProvisioningService::JOB_TERMINATE, ['service_id' => $service->id], ProvisioningService::JOB_TERMINATE . ':' . $service->id);
        $this->audit->log($actorId, 'service.termination_requested', 'service', $service->id, ['reason' => $reason]);
    }

    /** Re-queues provisioning after a failure (or for a pending manual service with an integration). */
    public function retryProvisioning(Service $service, ?int $actorId): void
    {
        if (!in_array($service->status, [Service::STATUS_FAILED, Service::STATUS_PENDING, Service::STATUS_PROVISIONING], true)) {
            throw new ServiceStateException('The service is not waiting for provisioning.');
        }
        if (!$this->provisioners->supportsService($service)) {
            throw new ServiceStateException('This service is provisioned manually.');
        }

        $this->services->update($service->id, ['status' => Service::STATUS_PENDING]);
        // Re-run the latest provisioning job if it failed; if it already
        // finished (e.g. a reinstall failed later) queue a new one.
        $latest = $this->jobs->latestByKeyPrefix(ProvisioningService::JOB_PROVISION . ':' . $service->id);
        if ($latest === null || $latest['status'] === JobQueue::STATUS_COMPLETED) {
            $this->jobs->dispatch(ProvisioningService::JOB_PROVISION, ['service_id' => $service->id], ProvisioningService::JOB_PROVISION . ':' . $service->id . ':retry:' . $this->clock->now()->getTimestamp());
        } else {
            $this->jobs->retry((int) $latest['id']);
        }
        $this->audit->log($actorId, 'service.provisioning_retried', 'service', $service->id);
    }

    /**
     * Re-creates an active service (new operating system / clean install).
     *
     * The provisioner resets its persisted remote state; the regular
     * provisioning job then destroys the old installation where necessary
     * and installs the service again (idempotent, retried on failure).
     *
     * @param array<string, mixed> $input validated action input (e.g. image)
     */
    public function reinstall(Service $service, array $input, ?int $actorId): void
    {
        if (!$this->provisioners->supportsService($service)) {
            throw new ServiceStateException('This service is managed manually.');
        }
        // Atomic: a second, concurrent request cannot start another reinstall.
        if (!$this->services->transitionStatus($service->id, Service::STATUS_ACTIVE, Service::STATUS_PROVISIONING)) {
            throw new ServiceStateException('Only active services can be reinstalled.');
        }

        try {
            $this->provisioners->for($service->category)->prepareReinstall($service, $input);
        } catch (\Throwable $exception) {
            $this->services->transitionStatus($service->id, Service::STATUS_PROVISIONING, Service::STATUS_ACTIVE);
            throw $exception;
        }
        $this->jobs->dispatch(
            ProvisioningService::JOB_PROVISION,
            ['service_id' => $service->id],
            ProvisioningService::JOB_PROVISION . ':' . $service->id . ':reinstall:' . $this->clock->now()->getTimestamp(),
        );
        $this->audit->log($actorId, 'service.reinstall_requested', 'service', $service->id, array_map(static fn (mixed $v): string => mb_substr((string) $v, 0, 100), $input));
    }

    /**
     * Activates a manually provisioned service (dedicated, webhosting, domain).
     *
     * @param array<string, string> $credentials access data entered by staff
     */
    public function activateManually(Service $service, array $credentials, ?string $externalId, int $actorId): void
    {
        if (!in_array($service->status, [Service::STATUS_PENDING, Service::STATUS_FAILED], true)) {
            throw new ServiceStateException('Only pending services can be activated.');
        }

        if ($credentials !== []) {
            $this->services->setCredentials($service->id, $credentials);
        }
        $this->services->update($service->id, [
            'status' => Service::STATUS_ACTIVE,
            'external_id' => $externalId,
            'provisioned_at' => $this->clock->nowString(),
            'last_error' => null,
        ]);
        $this->audit->log($actorId, 'service.activated_manually', 'service', $service->id);

        $owner = $this->users->find($service->userId);
        if ($owner !== null) {
            $this->mailer->queue($owner->email, $owner->displayName(), 'service_provisioned', [
                'name' => $owner->displayName(),
                'service_name' => $service->name,
                'service_url' => rtrim($this->config->string('app.url'), '/') . '/services/' . $service->id,
            ]);
        }
    }
}
