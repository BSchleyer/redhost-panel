<?php

declare(strict_types=1);

namespace Nebrix\Controller\Customer;

use Nebrix\Billing\InvoiceRepository;
use Nebrix\Billing\OrderRepository;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Integration\IntegrationException;
use Nebrix\Provisioning\ProvisionerRegistry;
use Nebrix\Repository\CustomerGroupRepository;
use Nebrix\Security\RateLimiter;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Psr\Log\LoggerInterface;

/**
 * Customer service management. Every lookup is scoped to the signed-in
 * customer; remote actions are limited to what the provisioner offers and
 * to the permissions of the customer's group.
 */
final class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ProvisionerRegistry $provisioners,
        private readonly CustomerGroupRepository $groups,
        private readonly InvoiceRepository $invoices,
        private readonly OrderRepository $orders,
        private readonly RateLimiter $limiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->render($request, 'customer/services/index.twig', ['services' => $this->services->forUser($this->user($request)->id)]);
    }

    public function show(Request $request, int $id): Response
    {
        $service = $this->find($request, $id);
        $live = null;
        $liveError = null;
        $actions = [];

        if ($service->isActive() && $this->provisioners->supports($service->category)) {
            $provisioner = $this->provisioners->for($service->category);
            try {
                $live = $provisioner->status($service);
                $actions = $this->mayManage($request) ? $provisioner->actions($service) : [];
            } catch (IntegrationException $exception) {
                // Customers get a generic message; details are in the integration log.
                $this->logger->notice('Live status unavailable', ['service' => $id, 'message' => $exception->getMessage()]);
                $liveError = 'Live information is temporarily unavailable.';
            }
        }

        return $this->render($request, 'customer/services/show.twig', [
            'service' => $service,
            'live' => $live,
            'live_error' => $liveError,
            'actions' => $actions,
            'renewal' => $this->invoices->openRenewalFor($id),
            'has_credentials' => $service->hasCredentials && in_array($service->status, [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED], true),
        ]);
    }

    /**
     * Access data is only rendered on explicit request and never cached.
     */
    public function credentials(Request $request, int $id): Response
    {
        $service = $this->find($request, $id);
        if (!in_array($service->status, [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED], true)) {
            throw HttpException::notFound();
        }
        $this->audit($request, 'service.credentials_viewed', 'service', $id);

        return $this->render($request, 'customer/services/credentials.twig', [
            'service' => $service,
            'credentials' => $this->services->credentials($service),
        ]);
    }

    public function action(Request $request, int $id): Response
    {
        $service = $this->find($request, $id);
        $data = $this->validate($request, ['action' => 'required|alpha_dash|max:30']);
        if (!$service->isActive() || !$this->mayManage($request) || !$this->provisioners->supports($service->category)) {
            throw HttpException::forbidden();
        }

        $provisioner = $this->provisioners->for($service->category);
        if (!array_key_exists((string) $data['action'], $provisioner->actions($service))) {
            throw HttpException::forbidden();
        }

        $key = 'service-action:' . $id;
        if (!$this->limiter->attempt($key, 10, 300)) {
            throw HttpException::tooManyRequests();
        }

        try {
            $message = $provisioner->perform($service, (string) $data['action']);
            $this->audit($request, 'service.action', 'service', $id, ['action' => $data['action']]);
            $this->flash($request, 'success', $message);
        } catch (IntegrationException $exception) {
            $this->logger->warning('Customer service action failed', ['service' => $id, 'action' => $data['action'], 'message' => $exception->getMessage()]);
            $this->flash($request, 'error', 'The action could not be executed. Please try again later or contact support.');
        }

        return $this->redirect('/services/' . $id);
    }

    public function orders(Request $request): Response
    {
        $user = $this->user($request);
        $orders = [];
        foreach ($this->orders->forUser($user->id) as $order) {
            $order['items'] = $this->orders->items((int) $order['id']);
            $orders[] = $order;
        }

        return $this->render($request, 'customer/orders.twig', ['orders' => $orders]);
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
        return $this->services->findForUser($id, $this->user($request)->id) ?? throw HttpException::notFound();
    }
}
