<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Authorization\Permission;
use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Network\IpPoolService;
use Nebrix\Security\ValidationException;
use Nebrix\Security\Validator;

/**
 * IPv4 pools for statically addressed Proxmox guests.
 */
final class IpPoolController extends Controller
{
    public function __construct(
        private readonly IpPoolService $pools,
        private readonly Connection $db,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->render($request, 'admin/ip_pools/index.twig', ['pools' => $this->pools->pools()]);
    }

    public function create(Request $request): Response
    {
        $this->authorize($request, Permission::INTEGRATIONS_MANAGE);

        return $this->render($request, 'admin/ip_pools/form.twig', ['integrations' => $this->integrationOptions()]);
    }

    public function store(Request $request): Response
    {
        $this->authorize($request, Permission::INTEGRATIONS_MANAGE);
        $data = $this->validate($request, [
            'name' => 'required|string|max:100',
            'network' => 'required|string|max:50',
            'gateway' => 'required|ip',
            'dns' => 'nullable|string|max:255',
            'first' => 'nullable|ip',
            'last' => 'nullable|ip',
            'integration_id' => 'nullable|int|min:1',
            'note' => 'nullable|string|max:255',
        ]);
        if ($data['integration_id'] !== null && !array_key_exists((int) $data['integration_id'], $this->integrationOptions())) {
            throw ValidationException::withMessages(['integration_id' => 'Choose a Proxmox integration.']);
        }

        try {
            $id = $this->pools->createPool(
                (string) $data['name'],
                (string) $data['network'],
                (string) $data['gateway'],
                $data['dns'] !== null ? (string) $data['dns'] : null,
                $data['integration_id'] !== null ? (int) $data['integration_id'] : null,
                $data['first'] !== null ? (string) $data['first'] : null,
                $data['last'] !== null ? (string) $data['last'] : null,
                $data['note'] !== null ? (string) $data['note'] : null,
            );
        } catch (ValidationException $exception) {
            throw new ValidationException($exception->errors(), Validator::safeInput($request->all()));
        }
        $this->audit($request, 'ip_pool.created', 'ip_pool', $id, ['network' => $data['network']]);
        $this->flash($request, 'success', 'IP pool created.');

        return $this->redirect('/admin/ip-pools/' . $id);
    }

    public function show(Request $request, int $id): Response
    {
        $pool = $this->pools->find($id) ?? throw HttpException::notFound();

        return $this->render($request, 'admin/ip_pools/show.twig', ['pool' => $pool, 'addresses' => $this->pools->addresses($id)]);
    }

    public function updateAddress(Request $request, int $id, int $address): Response
    {
        $this->authorize($request, Permission::INTEGRATIONS_MANAGE);
        $this->pools->find($id) ?? throw HttpException::notFound();
        $data = $this->validate($request, ['status' => 'required|in:free,reserved', 'note' => 'nullable|string|max:255']);
        $this->pools->setReserved($id, $address, $data['status'] === 'reserved', $data['note'] !== null ? (string) $data['note'] : null);
        $this->audit($request, 'ip_pool.address_changed', 'ip_address', $address, ['status' => $data['status']]);
        $this->flash($request, 'success', 'Address updated.');

        return $this->redirect('/admin/ip-pools/' . $id);
    }

    public function delete(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::INTEGRATIONS_MANAGE);
        $this->pools->find($id) ?? throw HttpException::notFound();
        if ((int) $this->db->fetchValue("SELECT COUNT(*) FROM `nx_products` WHERE JSON_EXTRACT(`provisioning_config`, '$.ip_pool_id') = ?", [$id]) > 0) {
            $this->flash($request, 'error', 'The pool is still used by a product.');

            return $this->redirect('/admin/ip-pools/' . $id);
        }
        $this->pools->deletePool($id);
        $this->audit($request, 'ip_pool.deleted', 'ip_pool', $id);
        $this->flash($request, 'success', 'IP pool deleted.');

        return $this->redirect('/admin/ip-pools');
    }

    /**
     * @return array<int, string>
     */
    private function integrationOptions(): array
    {
        $options = [];
        foreach ($this->db->fetchAll("SELECT `id`, `name` FROM `nx_integrations` WHERE `type` = 'proxmox' ORDER BY `name`") as $row) {
            $options[(int) $row['id']] = (string) $row['name'];
        }

        return $options;
    }
}
