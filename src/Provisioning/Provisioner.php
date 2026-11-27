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
     * Actions a customer or staff member may trigger (e.g. start, reinstall).
     *
     * @return array<string, ServiceAction> action key => definition
     */
    public function actions(Service $service): array;

    /**
     * Executes a synchronous action. Input has been validated against the
     * action's field definitions.
     *
     * @param array<string, mixed> $input
     * @return string user-facing confirmation
     *
     * @throws IntegrationException
     */
    public function perform(Service $service, string $action, array $input = []): string;

    /**
     * Prepares a reinstall: validates the input and resets the persisted
     * remote state so that the next provisioning run re-creates the service
     * (destroying the old installation where necessary). Called only for
     * actions with mode ServiceAction::REINSTALL; must not contact the remote
     * system (the provisioning job does).
     *
     * @param array<string, mixed> $input
     *
     * @throws IntegrationException when reinstalling is not supported
     */
    public function prepareReinstall(Service $service, array $input): void;
}
