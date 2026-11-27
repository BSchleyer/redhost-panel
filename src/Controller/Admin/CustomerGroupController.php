<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Repository\CustomerGroupRepository;
use Nebrix\Security\ValidationException;

final class CustomerGroupController extends Controller
{
    public function __construct(private readonly CustomerGroupRepository $groups)
    {
    }

    public function index(Request $request): Response
    {
        return $this->render($request, 'admin/customer_groups/index.twig', ['groups' => $this->groups->all(), 'editing' => null]);
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->render($request, 'admin/customer_groups/index.twig', ['groups' => $this->groups->all(), 'editing' => $this->find($id)]);
    }

    public function store(Request $request): Response
    {
        $id = $this->groups->save(null, $this->validated($request, 0));
        $this->audit($request, 'customer_group.created', 'customer_group', $id);
        $this->flash($request, 'success', 'Customer group created.');

        return $this->redirect('/admin/customer-groups');
    }

    public function update(Request $request, int $id): Response
    {
        $this->find($id);
        $this->groups->save($id, $this->validated($request, $id));
        $this->audit($request, 'customer_group.updated', 'customer_group', $id);
        $this->flash($request, 'success', 'Customer group saved.');

        return $this->redirect('/admin/customer-groups');
    }

    public function delete(Request $request, int $id): Response
    {
        $this->find($id);
        $this->groups->delete($id);
        $this->audit($request, 'customer_group.deleted', 'customer_group', $id);
        $this->flash($request, 'success', 'Customer group deleted. Its members no longer belong to a group.');

        return $this->redirect('/admin/customer-groups');
    }

    /**
     * @return array{name: string, description: ?string, discount_bp: int, can_order: bool, can_manage_services: bool}
     */
    private function validated(Request $request, int $id): array
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'discount_percent' => 'required|decimal|between:0,100',
            'can_order' => 'bool',
            'can_manage_services' => 'bool',
        ]);
        if ($this->groups->nameExists((string) $data['name'], $id)) {
            throw ValidationException::withMessages(['name' => 'A group with this name already exists.']);
        }

        return [
            'name' => (string) $data['name'],
            'description' => $data['description'],
            'discount_bp' => (int) round((float) $data['discount_percent'] * 100),
            'can_order' => (bool) $data['can_order'],
            'can_manage_services' => (bool) $data['can_manage_services'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function find(int $id): array
    {
        return $this->groups->find($id) ?? throw HttpException::notFound();
    }
}
