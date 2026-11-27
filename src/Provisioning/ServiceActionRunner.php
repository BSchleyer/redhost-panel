<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Audit\AuditLogger;
use Nebrix\Integration\IntegrationException;
use Nebrix\Security\ValidationException;
use Nebrix\Service\Service;

/**
 * Runs a provisioner action requested by a customer or staff member.
 * Only actions offered by the provisioner for this service are accepted
 * (whitelist); their input is validated against the action's fields.
 */
class ServiceActionRunner
{
    public function __construct(
        private readonly ProvisionerRegistry $provisioners,
        private readonly ServiceManager $manager,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @return array<string, ServiceAction>
     *
     * @throws IntegrationException
     */
    public function available(Service $service): array
    {
        if (!$service->isActive() || !$this->provisioners->supportsService($service)) {
            return [];
        }

        return $this->provisioners->for($service->category)->actions($service);
    }

    /**
     * @param array<string, mixed> $input raw request data
     * @return array{message: string, redirect: string|null}
     *
     * @throws ValidationException|IntegrationException|ServiceStateException
     */
    public function run(Service $service, string $key, array $input, ?int $actorId, ?string $ip): array
    {
        $actions = $this->available($service);
        $action = $actions[$key] ?? throw new ServiceStateException('This action is not available for the service.');
        $values = $action->validate($input);

        if ($action->mode === ServiceAction::REINSTALL) {
            $this->manager->reinstall($service, $values, $actorId);

            return ['message' => $action->queuedMessage ?? 'The reinstallation has been started. New access data will be available when it is finished.', 'redirect' => null];
        }

        $audit = ['action' => $key] + array_map(static fn (mixed $v): string => mb_substr((string) $v, 0, 100), $values);
        if ($action->mode === ServiceAction::REDIRECT) {
            $url = $this->provisioners->for($service->category)->perform($service, $key, $values + ['_client_ip' => (string) $ip]);
            if (preg_match('#^https?://[^\s/?\#]+#i', $url) !== 1) {
                throw new IntegrationException('The remote system did not return a valid login URL.');
            }
            $this->audit->log($actorId, 'service.action', 'service', $service->id, $audit, $ip);

            return ['message' => '', 'redirect' => $url];
        }

        $message = $this->provisioners->for($service->category)->perform($service, $key, $values);
        $this->audit->log($actorId, 'service.action', 'service', $service->id, $audit, $ip);

        return ['message' => $message, 'redirect' => null];
    }
}
