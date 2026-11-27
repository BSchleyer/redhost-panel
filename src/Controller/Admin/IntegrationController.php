<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Authorization\Permission;
use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationLogger;
use Nebrix\Integration\IntegrationManager;
use Nebrix\Integration\IntegrationRepository;
use Nebrix\Integration\IntegrationType;
use Nebrix\Security\UnsafeUrlException;
use Nebrix\Security\UrlGuard;
use Nebrix\Security\ValidationException;
use Nebrix\Security\Validator;

/**
 * Central management of Proxmox, Pterodactyl, Nextcloud and TeamSpeak
 * instances. Credentials are write-only: after saving they are never sent
 * back to the browser.
 */
final class IntegrationController extends Controller
{
    public function __construct(
        private readonly IntegrationRepository $integrations,
        private readonly IntegrationManager $manager,
        private readonly IntegrationLogger $logs,
        private readonly UrlGuard $guard,
        private readonly Connection $db,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->render($request, 'admin/integrations/index.twig', [
            'integrations' => $this->integrations->listing(),
            'types' => IntegrationType::all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize($request, Permission::INTEGRATIONS_MANAGE);
        $type = $request->string('type');
        if (!IntegrationType::exists($type)) {
            return $this->render($request, 'admin/integrations/choose_type.twig', ['types' => IntegrationType::all()]);
        }

        return $this->form($request, null, $type);
    }

    public function store(Request $request): Response
    {
        $this->authorize($request, Permission::INTEGRATIONS_MANAGE);
        $type = $request->string('type');
        if (!IntegrationType::exists($type)) {
            throw HttpException::badRequest();
        }

        [$name, $enabled, $config, $secrets] = $this->validated($request, $type, null);
        $id = $this->integrations->create($type, $name, $enabled, $config, array_filter($secrets, static fn ($v): bool => $v !== null));
        $this->audit($request, 'integration.created', 'integration', $id, ['type' => $type, 'name' => $name]);

        $result = $this->manager->testConnection($this->manager->get($id));
        $this->flash($request, $result->isHealthy() ? 'success' : 'warning', sprintf('Integration saved. Connection test: %s – %s', $result->status, $result->message));

        return $this->redirect('/admin/integrations/' . $id);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::INTEGRATIONS_MANAGE);
        $integration = $this->find($id);

        return $this->form($request, $id, $integration->type);
    }

    public function update(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::INTEGRATIONS_MANAGE);
        $integration = $this->find($id);

        [$name, $enabled, $config, $secrets] = $this->validated($request, $integration->type, $id);
        $this->integrations->update($id, $name, $enabled, $config, $secrets);
        $this->audit($request, 'integration.updated', 'integration', $id, [
            'enabled' => $enabled,
            'secrets_changed' => array_keys($secrets),
        ]);
        $this->flash($request, 'success', 'Integration saved.');

        return $this->redirect('/admin/integrations/' . $id);
    }

    public function test(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::INTEGRATIONS_VIEW);
        $result = $this->manager->testConnection($this->find($id));
        $this->audit($request, 'integration.tested', 'integration', $id, ['status' => $result->status]);
        $this->flash($request, $result->isHealthy() ? 'success' : ($result->status === 'degraded' ? 'warning' : 'error'), sprintf('%s: %s', ucfirst($result->status), $result->message));

        return $this->redirect('/admin/integrations/' . $id);
    }

    public function delete(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::INTEGRATIONS_MANAGE);
        $integration = $this->find($id);
        $active = $this->integrations->activeServiceCount($id);
        if ($active > 0) {
            $this->flash($request, 'error', sprintf('The integration is used by %d active service(s) and cannot be deleted. Disable it instead.', $active));

            return $this->redirect('/admin/integrations/' . $id);
        }

        $this->integrations->delete($id);
        $this->audit($request, 'integration.deleted', 'integration', $id, ['type' => $integration->type, 'name' => $integration->name]);
        $this->flash($request, 'success', 'Integration deleted.');

        return $this->redirect('/admin/integrations');
    }

    public function show(Request $request, int $id): Response
    {
        $integration = $this->find($id);
        $level = in_array($request->string('level'), ['debug', 'info', 'warning', 'error'], true) ? $request->string('level') : '';
        $page = $this->page($request);
        $row = null;
        foreach ($this->integrations->listing($integration->type) as $candidate) {
            if ((int) $candidate['id'] === $id) {
                $row = $candidate;
            }
        }

        return $this->render($request, 'admin/integrations/show.twig', [
            'integration' => $row,
            'type' => IntegrationType::get($integration->type),
            'config' => $integration->config,
            'logs' => $this->logs->paginate($id, $level, $page),
            'level' => $level,
            'page' => $page,
        ]);
    }

    /**
     * Read-only view of remote resources that are useful when configuring
     * products (nodes, nests, eggs, locations, virtual servers).
     */
    public function resources(Request $request, int $id): Response
    {
        $integration = $this->find($id);
        $resources = [];
        $error = null;

        try {
            switch ($integration->type) {
                case IntegrationType::PROXMOX:
                    $resources['nodes'] = $this->manager->proxmox($integration, true)->nodes();
                    break;
                case IntegrationType::PTERODACTYL:
                    $client = $this->manager->pterodactyl($integration, true);
                    $resources['nodes'] = $client->nodes();
                    $resources['locations'] = $client->locations();
                    $nests = [];
                    foreach ($client->nests() as $nest) {
                        $nest['eggs'] = $client->eggs((int) $nest['id']);
                        $nests[] = $nest;
                    }
                    $resources['nests'] = $nests;
                    break;
                case IntegrationType::TEAMSPEAK:
                    $client = $this->manager->teamspeak($integration, true);
                    $resources['servers'] = $client->listServers();
                    $client->disconnect();
                    break;
                case IntegrationType::HETZNER_ROBOT:
                    $prefix = (string) $integration->config('pool_prefix', 'nebrix-free');
                    $assigned = [];
                    foreach ($this->db->fetchAll("SELECT `id`, `remote` FROM `nx_services` WHERE `integration_id` = ? AND `status` NOT IN ('terminated', 'cancelled')", [$integration->id]) as $row) {
                        $remote = json_decode((string) $row['remote'], true);
                        if (is_array($remote) && isset($remote['server_number'])) {
                            $assigned[(int) $remote['server_number']] = (int) $row['id'];
                        }
                    }
                    $resources['dedicated'] = [];
                    foreach ($this->manager->hetznerRobot($integration, true)->servers() as $server) {
                        $name = (string) ($server['server_name'] ?? '');
                        $number = (int) ($server['server_number'] ?? 0);
                        $resources['dedicated'][] = $server + [
                            'pool_state' => isset($assigned[$number]) ? 'assigned' : (str_starts_with($name, 'nebrix-wipe-') ? 'wipe' : (str_starts_with($name, $prefix) ? 'free' : 'unmanaged')),
                            'service_id' => $assigned[$number] ?? null,
                        ];
                    }
                    break;
                default:
                    $resources['health'] = $this->manager->testConnection($integration);
            }
        } catch (IntegrationException $exception) {
            $error = $exception->getMessage();
        }

        return $this->render($request, 'admin/integrations/resources.twig', [
            'integration' => ['id' => $integration->id, 'name' => $integration->name, 'type' => $integration->type],
            'resources' => $resources,
            'error' => $error,
        ]);
    }

    private function form(Request $request, ?int $id, string $type): Response
    {
        $integration = $id !== null ? $this->find($id) : null;
        $secretsSet = [];
        foreach (array_keys(IntegrationType::get($type)['secrets']) as $key) {
            $secretsSet[$key] = $integration?->hasSecret($key) ?? false;
        }

        $config = $integration !== null ? $integration->config : [];
        foreach (IntegrationType::get($type)['config'] as $key => $field) {
            $config[$key] ??= $field['default'] ?? null;
        }

        return $this->render($request, 'admin/integrations/form.twig', [
            'id' => $id,
            'type' => $type,
            'definition' => IntegrationType::get($type),
            'name' => $integration?->name,
            'enabled' => $integration === null ? true : $integration->enabled,
            'config' => $config,
            'secrets_set' => $secretsSet,
        ]);
    }

    /**
     * @return array{0: string, 1: bool, 2: array<string, mixed>, 3: array<string, string|null>}
     */
    private function validated(Request $request, string $type, ?int $id): array
    {
        $definition = IntegrationType::get($type);
        $base = $this->validate($request, ['name' => 'required|string|max:100', 'is_enabled' => 'bool']);
        if ($this->integrations->nameExists($type, (string) $base['name'], $id ?? 0)) {
            throw ValidationException::withMessages(['name' => 'An integration of this type with this name already exists.']);
        }

        $configInput = [];
        $configRules = [];
        foreach ($definition['config'] as $key => $field) {
            $configInput[$key] = $request->input('config_' . $key);
            $configRules[$key] = $field['rules'];
        }
        try {
            $config = Validator::validate($configInput, $configRules, array_map(static fn (array $f): string => $f['label'], $definition['config']));
        } catch (ValidationException $exception) {
            throw $this->prefixed($exception, 'config_', $request);
        }

        // SSRF protection already at save time (and again before every request).
        try {
            if (isset($config['base_url'])) {
                $this->guard->validateUrl((string) $config['base_url']);
                $config['base_url'] = rtrim((string) $config['base_url'], '/');
            }
            if (isset($config['host'])) {
                $this->guard->validateHost((string) $config['host']);
            }
        } catch (UnsafeUrlException $exception) {
            throw ValidationException::withMessages([isset($config['base_url']) ? 'config_base_url' : 'config_host' => $exception->getMessage()]);
        }
        if ($type === IntegrationType::HETZNER_ROBOT && !\Nebrix\Provisioning\HetznerRobotProvisioner::isSafePoolPrefix((string) $config['pool_prefix'])) {
            throw ValidationException::withMessages(['config_pool_prefix' => 'The prefix must not match "nebrix-service-" or "nebrix-wipe-" (names of assigned and returned servers).']);
        }
        if ($type === IntegrationType::TEAMSPEAK && (int) $config['port_min'] > (int) $config['port_max']) {
            throw ValidationException::withMessages(['config_port_max' => 'The last port must be greater than the first port.']);
        }

        $secrets = [];
        $errors = [];
        foreach ($definition['secrets'] as $key => $field) {
            $value = $request->string('secret_' . $key);
            if (filter_var($request->input('secret_' . $key . '__clear'), FILTER_VALIDATE_BOOL) && ($field['required'] ?? false) !== true) {
                $secrets[$key] = null;
                continue;
            }
            if ($value === '') {
                if (($field['required'] ?? false) === true && $id === null) {
                    $errors['secret_' . $key] = [sprintf('The %s is required.', $field['label'])];
                }
                continue;
            }
            try {
                Validator::validate(['v' => $value], ['v' => $field['rules']], ['v' => $field['label']]);
            } catch (ValidationException $exception) {
                $errors['secret_' . $key] = [$exception->first()];
                continue;
            }
            $secrets[$key] = $value;
        }
        if ($errors !== []) {
            throw new ValidationException($errors, Validator::safeInput($request->all()));
        }

        return [(string) $base['name'], (bool) $base['is_enabled'], $config, $secrets];
    }

    private function prefixed(ValidationException $exception, string $prefix, Request $request): ValidationException
    {
        $errors = [];
        foreach ($exception->errors() as $key => $messages) {
            $errors[$prefix . $key] = $messages;
        }

        return new ValidationException($errors, Validator::safeInput($request->all()));
    }

    private function find(int $id): \Nebrix\Integration\Integration
    {
        return $this->integrations->find($id) ?? throw HttpException::notFound();
    }
}
