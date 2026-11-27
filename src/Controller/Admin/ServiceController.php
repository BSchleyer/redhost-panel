<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Authorization\Permission;
use Nebrix\Catalog\ProductCategory;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Http\SecurityHeaders;
use Nebrix\Integration\IntegrationException;
use Nebrix\Provisioning\ProvisionerRegistry;
use Nebrix\Provisioning\ServiceActionRunner;
use Nebrix\Provisioning\ServiceManager;
use Nebrix\Provisioning\ServiceStateException;
use Nebrix\RemoteConsole\ConsoleSessions;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use RuntimeException;

final class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceManager $manager,
        private readonly ProvisionerRegistry $provisioners,
        private readonly Connection $db,
        private readonly ServiceActionRunner $runner,
        private readonly ConsoleSessions $consoles,
        private readonly Config $config,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'status' => in_array($request->string('status'), ['pending', 'provisioning', 'active', 'suspended', 'terminated', 'failed', 'cancelled'], true) ? $request->string('status') : '',
            'category' => ProductCategory::exists($request->string('category')) ? $request->string('category') : '',
            'search' => mb_substr($request->string('search'), 0, 100),
        ];
        $page = $this->page($request);

        return $this->render($request, 'admin/services/index.twig', [
            'result' => $this->services->paginate($filters, $page),
            'filters' => $filters,
            'categories' => ProductCategory::labels(),
            'page' => $page,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $service = $this->find($id);
        $live = null;
        $liveError = null;
        $remoteActions = [];
        if ($service->isActive() && $this->provisioners->supportsService($service)) {
            try {
                $live = $this->provisioners->for($service->category)->status($service);
                $remoteActions = $this->runner->available($service);
            } catch (IntegrationException $exception) {
                $liveError = $exception->getMessage();
            }
        }

        $config = $service->config;
        unset($config['_options']);

        return $this->render($request, 'admin/services/show.twig', [
            'service' => $service,
            'config' => $config,
            'options' => $service->config['_options'] ?? [],
            'customer' => $this->db->fetchOne('SELECT `id`, `email`, `first_name`, `last_name` FROM `nx_users` WHERE `id` = ?', [$service->userId]),
            'integration' => $service->integrationId !== null ? $this->db->fetchOne('SELECT `id`, `name`, `type` FROM `nx_integrations` WHERE `id` = ?', [$service->integrationId]) : null,
            'invoices' => $this->db->fetchAll('SELECT * FROM `nx_invoices` WHERE `service_id` = ? ORDER BY `id` DESC', [$id]),
            'jobs' => $this->db->fetchAll(
                "SELECT `id`, `type`, `status`, `attempts`, `max_attempts`, `last_error`, `updated_at` FROM `nx_jobs`
                 WHERE `unique_key` LIKE ? OR `unique_key` LIKE ? ORDER BY `id` DESC LIMIT 20",
                ['service.%:' . $id, 'service.%:' . $id . ':%'],
            ),
            'live' => $live,
            'live_error' => $liveError,
            'automated' => $this->provisioners->supportsService($service),
            'remote_actions' => $remoteActions,
            'console' => $this->consoles->supports($service),
        ]);
    }

    public function console(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::SERVICES_MANAGE);
        $service = $this->find($id);
        if (!$this->consoles->supports($service)) {
            throw HttpException::notFound();
        }

        $url = parse_url($this->config->string('console.ws_url'));
        $origin = sprintf('%s://%s%s', $url['scheme'] ?? 'wss', $url['host'] ?? 'localhost', isset($url['port']) ? ':' . $url['port'] : '');
        $response = $this->render($request, 'admin/services/console.twig', ['service' => $service]);
        $response->setHeader('Content-Security-Policy', SecurityHeaders::contentSecurityPolicy(['connect-src' => [$origin]]));

        return $response;
    }

    public function consoleSession(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::SERVICES_MANAGE);
        $service = $this->find($id);
        try {
            $session = $this->consoles->open($service, $this->user($request), $request->clientIp());
        } catch (IntegrationException|ServiceStateException $exception) {
            return Response::json(['error' => mb_substr($exception->getMessage(), 0, 200)], 502);
        }
        $this->audit($request, 'service.console_opened_by_staff', 'service', $id);

        return Response::json(['url' => $session['url'], 'password' => $session['password']]);
    }

    /** Provisioner actions (power, reinstall, …) executed by staff. */
    public function remote(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::SERVICES_MANAGE);
        $service = $this->find($id);
        $data = $this->validate($request, ['action' => 'required|alpha_dash|max:30']);

        try {
            $result = $this->runner->run($service, (string) $data['action'], $request->all(), $this->user($request)->id, $request->clientIp());
            if ($result['redirect'] !== null) {
                return $this->render($request, 'admin/services/sso.twig', ['service' => $service, 'url' => $result['redirect']]);
            }
            $this->flash($request, 'success', $result['message']);
        } catch (ServiceStateException|IntegrationException $exception) {
            $this->flash($request, 'error', $exception->getMessage());
        }

        return $this->redirect('/admin/services/' . $id);
    }

    public function credentials(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::SERVICES_MANAGE);
        $service = $this->find($id);
        $this->audit($request, 'service.credentials_viewed_by_staff', 'service', $id);

        return $this->render($request, 'admin/services/credentials.twig', ['service' => $service, 'credentials' => $this->services->credentials($service)]);
    }

    public function action(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::SERVICES_MANAGE);
        $service = $this->find($id);
        $data = $this->validate($request, [
            'action' => 'required|in:retry,suspend,unsuspend,terminate,activate,due_date',
            'reason' => 'nullable|string|max:255',
            'external_id' => 'nullable|string|max:191',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'access_info' => 'nullable|string|max:2000',
            'next_due_at' => 'nullable|date',
        ]);
        $actor = $this->user($request)->id;

        try {
            switch ($data['action']) {
                case 'retry':
                    $this->manager->retryProvisioning($service, $actor);
                    $message = 'Provisioning has been queued again.';
                    break;
                case 'suspend':
                    $this->manager->suspend($service, (string) ($data['reason'] ?: 'Suspended by staff'), $actor);
                    $message = 'The service is being suspended.';
                    break;
                case 'unsuspend':
                    $this->manager->unsuspend($service, $actor);
                    $message = 'The service is being reactivated.';
                    break;
                case 'terminate':
                    if ($request->string('confirm') !== 'TERMINATE') {
                        throw new RuntimeException('Type TERMINATE to confirm the termination.');
                    }
                    $this->manager->terminate($service, $actor, (string) ($data['reason'] ?: 'Terminated by staff'));
                    $message = 'The service is being terminated.';
                    break;
                case 'activate':
                    $credentials = array_filter([
                        'username' => $data['username'],
                        'password' => $data['password'],
                        'info' => $data['access_info'],
                    ], static fn ($v): bool => $v !== null && $v !== '');
                    $this->manager->activateManually($service, $credentials, $data['external_id'], $actor);
                    $message = 'The service has been activated.';
                    break;
                default: // due_date
                    if ($data['next_due_at'] === null) {
                        throw new RuntimeException('Enter a due date.');
                    }
                    $this->services->update($id, ['next_due_at' => $data['next_due_at'] . ' 00:00:00']);
                    $this->audit($request, 'service.due_date_changed', 'service', $id, ['next_due_at' => $data['next_due_at']]);
                    $message = 'Due date updated.';
            }
            $this->flash($request, 'success', $message);
        } catch (RuntimeException $exception) {
            $this->flash($request, 'error', $exception->getMessage());
        }

        return $this->redirect('/admin/services/' . $id);
    }

    private function find(int $id): Service
    {
        return $this->services->find($id) ?? throw HttpException::notFound();
    }
}
