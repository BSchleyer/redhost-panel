<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Auth\Role;
use Nebrix\Authorization\Gate;
use Nebrix\Authorization\Permission;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Repository\RoleRepository;
use Nebrix\Security\ValidationException;

final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly Gate $gate,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->render($request, 'admin/roles/index.twig', ['roles' => $this->roles->all()]);
    }

    public function edit(Request $request, int $id): Response
    {
        $role = $this->find($id);

        return $this->render($request, 'admin/roles/edit.twig', [
            'role' => $role,
            'granted' => $this->roles->permissions($id),
            'groups' => Permission::grouped(),
            'locked' => $role['slug'] === Role::SUPER_ADMIN,
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, ['name' => 'required|string|max:100', 'description' => 'nullable|string|max:255']);
        if ($this->roles->nameExists((string) $data['name'])) {
            throw ValidationException::withMessages(['name' => 'A role with this name already exists.']);
        }

        $id = $this->roles->create((string) $data['name'], $data['description']);
        $this->audit($request, 'role.created', 'role', $id, ['name' => $data['name']]);
        $this->flash($request, 'success', 'Role created. Choose its permissions now.');

        return $this->redirect('/admin/roles/' . $id);
    }

    public function update(Request $request, int $id): Response
    {
        $role = $this->find($id);
        if ($role['slug'] === Role::SUPER_ADMIN) {
            throw HttpException::forbidden('The super administrator role always has every permission.');
        }

        $data = $this->validate($request, ['name' => 'required|string|max:100', 'description' => 'nullable|string|max:255', 'permissions' => 'nullable|array']);
        if ($this->roles->nameExists((string) $data['name'], $id)) {
            throw ValidationException::withMessages(['name' => 'A role with this name already exists.']);
        }

        $permissions = array_values(array_filter((array) ($data['permissions'] ?? []), 'is_string'));
        $before = $this->roles->permissions($id);
        $this->roles->updateDetails($id, (string) $data['name'], $data['description']);
        $this->roles->syncPermissions($id, $permissions);
        $after = $this->roles->permissions($id);
        $this->gate->flush();

        $this->audit($request, 'role.permissions_updated', 'role', $id, [
            'granted' => array_values(array_diff($after, $before)),
            'revoked' => array_values(array_diff($before, $after)),
        ]);
        $this->flash($request, 'success', 'Role saved.');

        return $this->redirect('/admin/roles/' . $id);
    }

    public function delete(Request $request, int $id): Response
    {
        $role = $this->find($id);
        if ((int) $role['is_system'] === 1) {
            throw HttpException::forbidden('System roles cannot be deleted.');
        }
        if ($this->roles->userCount($id) > 0) {
            $this->flash($request, 'error', 'Assign the users of this role to another role first.');

            return $this->redirect('/admin/roles/' . $id);
        }

        $this->roles->delete($id);
        $this->audit($request, 'role.deleted', 'role', $id, ['name' => $role['name']]);
        $this->flash($request, 'success', 'Role deleted.');

        return $this->redirect('/admin/roles');
    }

    /**
     * @return array<string, mixed>
     */
    private function find(int $id): array
    {
        $role = $this->roles->find($id);
        if ($role === null || (int) $role['is_staff'] !== 1) {
            throw HttpException::notFound();
        }

        return $role;
    }
}
