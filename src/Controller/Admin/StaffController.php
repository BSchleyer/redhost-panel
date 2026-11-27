<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Authorization\Permission;
use Nebrix\Customer\CustomerService;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Repository\RoleRepository;
use Nebrix\Repository\UserRepository;
use Nebrix\Security\ValidationException;

/**
 * Staff account management with privilege-escalation guards:
 * - only super administrators can create, edit or assign super administrators;
 * - nobody can change their own role or status;
 * - the last active super administrator cannot be demoted or suspended.
 */
final class StaffController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly CustomerService $accounts,
        private readonly Gate $gate,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->render($request, 'admin/staff/index.twig', [
            'result' => $this->users->paginate(['staff' => true, 'search' => mb_substr($request->string('search'), 0, 100)], $this->page($request), 100),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize($request, Permission::STAFF_MANAGE);

        return $this->render($request, 'admin/staff/form.twig', [
            'member' => null,
            'roles' => $this->assignableRoles($this->user($request)),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->authorize($request, Permission::STAFF_MANAGE);
        $actor = $this->user($request);
        $data = $this->validate($request, [
            'email' => 'required|email|max:254',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'role_id' => 'required|int|min:1',
        ]);

        $roleId = (int) $data['role_id'];
        $this->assertAssignableRole($actor, $roleId);
        if ($this->users->emailExists((string) $data['email'])) {
            throw ValidationException::withMessages(['email' => 'An account with this e-mail address already exists.']);
        }

        $id = $this->accounts->createAccount((string) $data['email'], $roleId, [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
        ], $actor->id, $request->clientIp());

        $this->flash($request, 'success', 'Staff account created. An e-mail with a link to set the password has been sent.');

        return $this->redirect('/admin/staff');
    }

    public function edit(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::STAFF_MANAGE);
        $member = $this->findStaff($id);
        $this->assertCanManage($this->user($request), $member);

        return $this->render($request, 'admin/staff/form.twig', [
            'member' => $member,
            'roles' => $this->assignableRoles($this->user($request)),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::STAFF_MANAGE);
        $actor = $this->user($request);
        $member = $this->findStaff($id);
        $this->assertCanManage($actor, $member);

        $data = $this->validate($request, [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'role_id' => 'required|int|min:1',
            'status' => 'required|in:active,suspended',
        ]);

        $roleId = (int) $data['role_id'];
        $roleChanged = $roleId !== $member->roleId;
        $statusChanged = $data['status'] !== $member->status;

        if (($roleChanged || $statusChanged) && $member->id === $actor->id) {
            throw ValidationException::withMessages(['role_id' => 'You cannot change your own role or status.']);
        }
        if ($roleChanged) {
            $this->assertAssignableRole($actor, $roleId);
        }
        if ($member->isSuperAdmin() && ($roleChanged || $data['status'] !== User::STATUS_ACTIVE)
            && $this->users->countUsersWithRole(Role::SUPER_ADMIN, User::STATUS_ACTIVE) <= 1) {
            throw ValidationException::withMessages(['role_id' => 'The last active super administrator cannot be demoted or suspended.']);
        }

        $this->users->update($id, ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'role_id' => $roleId]);
        if ($statusChanged) {
            $this->accounts->setStatus($member, (string) $data['status'], null, $actor->id, $request->clientIp());
        }
        if ($roleChanged) {
            // Force re-authentication so the new privileges apply cleanly.
            $this->accounts->revokeSessions($member, $actor->id, $request->clientIp());
        }

        $this->audit($request, 'staff.updated', 'user', $id, ['role_changed' => $roleChanged, 'new_role_id' => $roleId, 'status' => $data['status']]);
        $this->flash($request, 'success', 'Staff account saved.');

        return $this->redirect('/admin/staff');
    }

    public function resetTwoFactor(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::STAFF_MANAGE);
        $actor = $this->user($request);
        $member = $this->findStaff($id);
        $this->assertCanManage($actor, $member);
        if ($member->id === $actor->id) {
            throw HttpException::forbidden('Use your own security settings to manage your second factor.');
        }

        $this->accounts->resetTwoFactor($member, $actor->id, $request->clientIp());
        $this->flash($request, 'success', 'Two-factor authentication reset. The staff member must set it up again.');

        return $this->redirect('/admin/staff');
    }

    public function sendPasswordReset(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::STAFF_MANAGE);
        $member = $this->findStaff($id);
        $this->assertCanManage($this->user($request), $member);
        $this->accounts->sendPasswordSetupLink($member);
        $this->audit($request, 'staff.password_reset_sent', 'user', $id);
        $this->flash($request, 'success', 'A password reset link has been e-mailed.');

        return $this->redirect('/admin/staff');
    }

    private function findStaff(int $id): User
    {
        $user = $this->users->find($id);
        if ($user === null || !$user->isStaff()) {
            throw HttpException::notFound();
        }

        return $user;
    }

    private function assertCanManage(User $actor, User $member): void
    {
        if ($member->isSuperAdmin() && !$actor->isSuperAdmin()) {
            throw HttpException::forbidden('Only super administrators can manage super administrator accounts.');
        }
        if ($this->exceedsActor($actor, $member->roleId)) {
            throw HttpException::forbidden('You cannot manage staff members who have permissions you do not have yourself.');
        }
    }

    /**
     * A role grants more than the actor holds (privilege escalation guard):
     * staff managers may only hand out subsets of their own permissions.
     */
    private function exceedsActor(User $actor, int $roleId): bool
    {
        if ($actor->isSuperAdmin()) {
            return false;
        }

        return array_diff($this->gate->permissionsForRole($roleId), $this->gate->permissionsForRole($actor->roleId)) !== [];
    }

    /**
     * @return array<int, string>
     */
    private function assignableRoles(User $actor): array
    {
        return array_filter(
            $this->roles->staffOptions($actor->isSuperAdmin()),
            fn (int $roleId): bool => !$this->exceedsActor($actor, $roleId),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function assertAssignableRole(User $actor, int $roleId): void
    {
        $role = $this->roles->find($roleId);
        if ($role === null || (int) $role['is_staff'] !== 1) {
            throw ValidationException::withMessages(['role_id' => 'Choose a staff role.']);
        }
        if ($role['slug'] === Role::SUPER_ADMIN && !$actor->isSuperAdmin()) {
            throw ValidationException::withMessages(['role_id' => 'Only super administrators can assign this role.']);
        }
        if ($this->exceedsActor($actor, $roleId)) {
            throw ValidationException::withMessages(['role_id' => 'You cannot assign a role with permissions you do not have yourself.']);
        }
    }
}
