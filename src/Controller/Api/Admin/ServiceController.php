<?php

declare(strict_types=1);

namespace Nebrix\Controller\Api\Admin;

use Nebrix\Api\ApiException;
use Nebrix\Api\Resource;
use Nebrix\Controller\Api\ApiController;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Provisioning\ServiceManager;
use Nebrix\Provisioning\ServiceStateException;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;

/**
 * Admin service management: list and lifecycle operations through the same
 * ServiceManager as the admin interface (jobs, audit log, e-mails).
 */
final class ServiceController extends ApiController
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceManager $manager,
    ) {
    }

    public function index(Request $request): Response
    {
        [$page, $perPage] = $this->pagination($request);
        $filters = [
            'status' => $this->stringQuery($request, 'status', 20),
            'category' => $this->stringQuery($request, 'category', 30),
            'search' => $this->stringQuery($request, 'search'),
        ];
        foreach (['user_id' => 'user_id', 'integration_id' => 'integration'] as $query => $filter) {
            $value = $this->intQuery($request, $query);
            if ($value !== null) {
                $filters[$filter] = $value;
            }
        }
        $result = $this->services->paginateModels($filters, $page, $perPage);

        return $this->paginated(array_map(static fn (Service $service): array => self::withAdminFields($service), $result['items']), $result['total'], $page, $perPage);
    }

    public function show(Request $request, int $id): Response
    {
        return $this->data(self::withAdminFields($this->find($id)));
    }

    public function suspend(Request $request, int $id): Response
    {
        $data = $this->validateInput($request, ['reason' => 'nullable|string|max:255', 'notify' => 'nullable|bool']);
        // Like the admin interface, the customer is notified unless "notify": false is sent explicitly.
        $notify = !array_key_exists('notify', $this->input($request)) || $data['notify'] === true;

        return $this->lifecycle($request, $id, 'suspend', fn (Service $s, int $actor) => $this->manager->suspend($s, (string) ($data['reason'] ?: 'Suspended by staff'), $actor, $notify));
    }

    public function unsuspend(Request $request, int $id): Response
    {
        return $this->lifecycle($request, $id, 'unsuspend', fn (Service $s, int $actor) => $this->manager->unsuspend($s, $actor));
    }

    public function terminate(Request $request, int $id): Response
    {
        $data = $this->validateInput($request, ['reason' => 'nullable|string|max:255', 'confirm' => 'required|string|max:20']);
        if ($data['confirm'] !== 'TERMINATE') {
            throw new ApiException(422, 'confirmation_required', 'Send "confirm": "TERMINATE" to terminate the service. This deletes the remote resource.');
        }

        return $this->lifecycle($request, $id, 'terminate', fn (Service $s, int $actor) => $this->manager->terminate($s, $actor, (string) ($data['reason'] ?: 'Terminated by staff')));
    }

    public function retry(Request $request, int $id): Response
    {
        return $this->lifecycle($request, $id, 'retry', fn (Service $s, int $actor) => $this->manager->retryProvisioning($s, $actor));
    }

    /**
     * @param callable(Service, int): void $operation
     */
    private function lifecycle(Request $request, int $id, string $name, callable $operation): Response
    {
        $service = $this->find($id);
        try {
            $operation($service, $this->user($request)->id);
        } catch (ServiceStateException $exception) {
            throw ApiException::conflict($exception->getMessage());
        }
        // ServiceManager logs the operation itself; this entry ties it to the token.
        $this->apiAudit($request, 'service.' . $name . '_via_api', 'service', $id);

        return $this->data(self::withAdminFields($this->find($id)), 202);
    }

    /**
     * @return array<string, mixed>
     */
    private static function withAdminFields(Service $service): array
    {
        return Resource::service($service) + [
            'suspension_reason' => $service->status === Service::STATUS_SUSPENDED && is_string($service->attributes['suspension_reason'] ?? null) ? $service->attributes['suspension_reason'] : null,
            'integration_id' => $service->integrationId,
            'external_id' => $service->externalId,
            'last_error' => is_string($service->attributes['last_error'] ?? null) ? $service->attributes['last_error'] : null,
        ];
    }

    private function find(int $id): Service
    {
        return $this->services->find($id) ?? throw ApiException::notFound();
    }
}
