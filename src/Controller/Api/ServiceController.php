<?php

declare(strict_types=1);

namespace Nebrix\Controller\Api;

use Nebrix\Api\ApiException;
use Nebrix\Api\Resource;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Integration\IntegrationException;
use Nebrix\Provisioning\ProvisionerRegistry;
use Nebrix\Provisioning\ServiceAction;
use Nebrix\Provisioning\ServiceActionRunner;
use Nebrix\Provisioning\ServiceStateException;
use Nebrix\Repository\CustomerGroupRepository;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Psr\Log\LoggerInterface;

/**
 * The customer's services: details, live status, actions and access data.
 * Same checks as the web interface (ownership, customer group may manage
 * services, provisioner action whitelist, shared rate limits).
 */
final class ServiceController extends ApiController
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ProvisionerRegistry $provisioners,
        private readonly ServiceActionRunner $runner,
        private readonly CustomerGroupRepository $groups,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function index(Request $request): Response
    {
        $status = $this->stringQuery($request, 'status', 20);
        $services = array_values(array_filter(
            $this->services->forUser($this->user($request)->id),
            static fn (Service $service): bool => $status === '' || $service->status === $status,
        ));

        return $this->pageOf($request, $services, Resource::service(...));
    }

    public function show(Request $request, int $id): Response
    {
        return $this->data(Resource::service($this->find($request, $id)));
    }

    public function status(Request $request, int $id): Response
    {
        $service = $this->find($request, $id);
        if (!$service->isActive() || !$this->provisioners->supportsService($service)) {
            return $this->data(['state' => $service->status, 'live' => false, 'connection' => []]);
        }

        try {
            $status = $this->provisioners->for($service->category)->status($service);
        } catch (IntegrationException $exception) {
            $this->logger->notice('Live status unavailable (API)', ['service' => $id, 'message' => $exception->getMessage()]);

            throw new ApiException(502, 'upstream_error', 'Live information is temporarily unavailable.');
        }

        return $this->data(['live' => true] + $status);
    }

    public function actions(Request $request, int $id): Response
    {
        $service = $this->find($request, $id);
        if (!$this->mayManage($request)) {
            return Response::json(['data' => []]);
        }

        try {
            $available = $this->runner->available($service);
        } catch (IntegrationException $exception) {
            $this->logger->warning('Service actions unavailable (API)', ['service' => $id, 'message' => $exception->getMessage()]);

            throw new ApiException(502, 'upstream_error', 'The actions are temporarily unavailable.');
        }

        $actions = [];
        foreach ($available as $key => $action) {
            $fields = [];
            foreach ($action->fields as $name => $field) {
                $fields[] = [
                    'name' => $name,
                    'label' => $field['label'],
                    'type' => $field['type'] ?? 'text',
                    'required' => str_contains($field['rules'], 'required'),
                    'options' => $field['options'] ?? null,
                    'help' => $field['help'] ?? null,
                ];
            }
            $actions[] = [
                'key' => $key,
                'label' => $action->label,
                'mode' => $action->mode,
                'dangerous' => $action->danger,
                'requires_confirmation' => $action->confirm !== null,
                'confirmation_text' => $action->confirm,
                'fields' => $fields,
            ];
        }

        return Response::json(['data' => $actions]);
    }

    public function runAction(Request $request, int $id, string $action): Response
    {
        $service = $this->find($request, $id);
        if (!$service->isActive() || !$this->mayManage($request) || !$this->provisioners->supportsService($service)) {
            throw ApiException::forbidden('Actions are not available for this service.');
        }

        try {
            $definition = $this->runner->available($service)[$action] ?? throw ApiException::notFound('The action is not available for this service.');
        } catch (IntegrationException $exception) {
            $this->logger->warning('Service actions unavailable (API)', ['service' => $id, 'message' => $exception->getMessage()]);

            throw new ApiException(502, 'upstream_error', 'The action could not be executed. Please try again later.');
        }

        $input = $this->input($request);
        // Actions the web interface confirms in a dialog need an explicit confirmation.
        if (($definition->confirm !== null || $definition->danger) && ($input['confirm'] ?? null) !== true) {
            throw new ApiException(422, 'confirmation_required', $definition->confirm ?? 'Confirm the action with "confirm": true.');
        }

        $this->throttle('service-action:' . $id, 10, 300);
        if ($definition->mode === ServiceAction::REINSTALL) {
            $this->throttle('service-reinstall:' . $id, 3, 3600);
        }

        unset($input['confirm']);
        try {
            $result = $this->runner->run($service, $action, $input, $this->user($request)->id, $request->clientIp());
        } catch (ServiceStateException $exception) {
            throw ApiException::conflict($exception->getMessage());
        } catch (IntegrationException $exception) {
            $this->logger->warning('Customer service action failed (API)', ['service' => $id, 'action' => $action, 'message' => $exception->getMessage()]);

            throw new ApiException(502, 'upstream_error', 'The action could not be executed. Please try again later or contact support.');
        }

        return $this->data([
            'message' => $result['message'],
            'redirect_url' => $result['redirect'],
            'service' => Resource::service($this->services->find($id) ?? $service),
        ], $definition->mode === ServiceAction::REINSTALL ? 202 : 200);
    }

    public function credentials(Request $request, int $id): Response
    {
        $service = $this->find($request, $id);
        if (!in_array($service->status, [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED], true)) {
            throw ApiException::notFound('No access data is available for this service.');
        }
        $this->apiAudit($request, 'service.credentials_viewed', 'service', $id);

        $credentials = [];
        foreach ($this->services->credentials($service) as $key => $value) {
            $credentials[(string) $key] = is_scalar($value) || $value === null ? $value : null;
        }

        return $this->data($credentials);
    }

    private function mayManage(Request $request): bool
    {
        $user = $this->user($request);
        if ($user->customerGroupId === null) {
            return true;
        }
        $group = $this->groups->find($user->customerGroupId);

        return $group === null || (bool) $group['can_manage_services'];
    }

    private function find(Request $request, int $id): Service
    {
        return $this->services->findForUser($id, $this->user($request)->id) ?? throw ApiException::notFound();
    }
}
