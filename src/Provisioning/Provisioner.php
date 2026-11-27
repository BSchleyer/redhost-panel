<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Auth\User;
use Nebrix\Integration\IntegrationException;
use Nebrix\Service\Service;

/**
 * Creates and manages services on an external system.
 *
 * Every method must be idempotent: jobs may run more than once (worker
 * crash, manual retry). Implementations persist remote identifiers through
 * ServiceRepository::mergeRemote() *before* a remote side effect and check
 * for existing remote objects before creating new ones.
 */
interface Provisioner
{
    /**
     * @throws RetryLaterException when a remote task is still running
     * @throws IntegrationException
     */
    public function provision(Service $service, User $owner): ProvisionResult;

    /** @throws RetryLaterException|IntegrationException */
    public function suspend(Service $service): void;

    /** @throws RetryLaterException|IntegrationException */
    public function unsuspend(Service $service): void;

    /** @throws RetryLaterException|IntegrationException */
    public function terminate(Service $service): void;

    /**
     * Live information for the service pages (state, usage, connection data).
     *
     * @return array<string, mixed>
     *
     * @throws IntegrationException
     */
    public function status(Service $service): array;

    /**
     * Actions a customer or staff member may trigger (e.g. start, stop).
     *
     * @return array<string, string> action => label
     */
    public function actions(Service $service): array;

    /**
     * @return string user-facing confirmation
     *
     * @throws IntegrationException
     */
    public function perform(Service $service, string $action): string;
}
