<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Auth\AuthService;
use Nebrix\Auth\LoginHistory;
use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Authorization\Permission;
use Nebrix\Billing\CreditService;
use Nebrix\Billing\InsufficientCreditException;
use Nebrix\Billing\Money;
use Nebrix\Customer\CustomerService;
use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Repository\CustomerGroupRepository;
use Nebrix\Repository\RoleRepository;
use Nebrix\Repository\UserRepository;
use Nebrix\Security\ValidationException;
use Nebrix\Settings\SettingsRepository;
use RuntimeException;

final class CustomerController extends Controller
{
    private const PROFILE_RULES = [
        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'company' => 'nullable|string|max:150',
        'vat_id' => 'nullable|string|max:50',
        'address_line1' => 'nullable|string|max:255',
        'address_line2' => 'nullable|string|max:255',
        'postcode' => 'nullable|string|max:20',
        'city' => 'nullable|string|max:100',
        'country' => 'nullable|country',
        'phone' => 'nullable|string|max:50',
        'language' => 'required|in:en,de',
        'customer_group_id' => 'nullable|int|min:1',
        'notes' => 'nullable|string|max:10000',
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly CustomerGroupRepository $groups,
        private readonly RoleRepository $roles,
        private readonly CustomerService $customers,
        private readonly CreditService $credit,
        private readonly LoginHistory $history,
        private readonly AuthService $auth,
        private readonly SettingsRepository $settings,
        private readonly Connection $db,
        private readonly \Nebrix\Core\Clock $clock,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => mb_substr($request->string('search'), 0, 100),
            'status' => in_array($request->string('status'), User::STATUSES, true) ? $request->string('status') : '',
            'staff' => false,
        ];
        $group = $request->string('group');
        if (ctype_digit($group)) {
            $filters['group'] = (int) $group;
        }
        $page = $this->page($request);

        return $this->render($request, 'admin/customers/index.twig', [
            'result' => $this->users->paginate($filters, $page),
            'filters' => $filters + ['group' => $group],
            'groups' => $this->groups->options(),
            'page' => $page,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $customer = $this->findCustomer($id);

        return $this->render($request, 'admin/customers/show.twig', [
            'customer' => $customer,
            'vat_status' => $this->container->get(\Nebrix\Billing\Tax\TaxService::class)->vatIdStatus($customer),
            'group' => $customer->customerGroupId !== null ? $this->groups->find($customer->customerGroupId) : null,
            'services' => $this->db->fetchAll('SELECT s.*, p.`name` AS product_name FROM `nx_services` s LEFT JOIN `nx_products` p ON p.`id` = s.`product_id` WHERE s.`user_id` = ? ORDER BY s.`id` DESC', [$id]),
            'orders' => $this->db->fetchAll('SELECT * FROM `nx_orders` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 20', [$id]),
            'invoices' => $this->db->fetchAll('SELECT * FROM `nx_invoices` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 20', [$id]),
            'transactions' => $this->db->fetchAll('SELECT * FROM `nx_transactions` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 20', [$id]),
            'tickets' => $this->db->fetchAll('SELECT * FROM `nx_tickets` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 20', [$id]),
            'credit_history' => $this->credit->history($id, 20),
            'legacy_records' => $this->db->fetchAll('SELECT * FROM `nx_legacy_records` WHERE `user_id` = ? ORDER BY `occurred_at` DESC LIMIT 50', [$id]),
            'logins' => $this->can($request, Permission::STAFF_VIEW) || $this->can($request, Permission::CUSTOMERS_MANAGE) ? $this->history->forUser($id, 10) : [],
            'active_sessions' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_sessions` WHERE `user_id` = ?', [$id]),
            'currency' => $this->settings->string('general.default_currency') ?: 'EUR',
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_MANAGE);

        return $this->render($request, 'admin/customers/form.twig', ['customer' => null, 'groups' => $this->groups->options()]);
    }

    public function store(Request $request): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_MANAGE);
        $data = $this->validate($request, ['email' => 'required|email|max:254'] + self::PROFILE_RULES);
        $this->assertGroupExists($data['customer_group_id']);
        if ($this->users->emailExists((string) $data['email'])) {
            throw ValidationException::withMessages(['email' => 'An account with this e-mail address already exists.']);
        }

        $email = (string) $data['email'];
        unset($data['email']);
        $id = $this->customers->createAccount($email, $this->roles->idForSlug(Role::CUSTOMER), $data, $this->user($request)->id, $request->clientIp());
        $this->flash($request, 'success', 'Customer created. A link to set the password has been e-mailed.');

        return $this->redirect('/admin/customers/' . $id);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_MANAGE);

        return $this->render($request, 'admin/customers/form.twig', ['customer' => $this->findCustomer($id), 'groups' => $this->groups->options()]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_MANAGE);
        $customer = $this->findCustomer($id);
        $data = $this->validate($request, ['email' => 'required|email|max:254'] + self::PROFILE_RULES);
        $this->assertGroupExists($data['customer_group_id']);
        if ($this->users->emailExists((string) $data['email'], $id)) {
            throw ValidationException::withMessages(['email' => 'An account with this e-mail address already exists.']);
        }

        $emailChanged = $data['email'] !== $customer->email;
        if ($emailChanged) {
            $data['email_verified_at'] = null;
        }
        $this->users->update($id, $data);
        $this->audit($request, 'customer.updated', 'user', $id, ['email_changed' => $emailChanged]);
        if (($data['vat_id'] ?? null) !== ($customer->attributes['vat_id'] ?? null) || ($data['country'] ?? null) !== ($customer->attributes['country'] ?? null)) {
            $refreshed = $this->users->find($id);
            if ($refreshed !== null) {
                $this->container->get(\Nebrix\Billing\Tax\TaxService::class)->verifyUser($refreshed, ($data['vat_id'] ?? null) !== ($customer->attributes['vat_id'] ?? null));
            }
        }

        if ($emailChanged) {
            $updated = $this->users->find($id);
            if ($updated !== null) {
                $this->auth->sendVerification($updated);
            }
        }
        $this->flash($request, 'success', $emailChanged ? 'Customer saved. A confirmation link was sent to the new address.' : 'Customer saved.');

        return $this->redirect('/admin/customers/' . $id);
    }

    public function status(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_MANAGE);
        $customer = $this->findCustomer($id);
        $data = $this->validate($request, ['status' => 'required|in:active,suspended', 'reason' => 'nullable|string|max:255']);

        try {
            $this->customers->setStatus($customer, (string) $data['status'], $data['reason'], $this->user($request)->id, $request->clientIp());
            $this->flash($request, 'success', $data['status'] === 'active' ? 'Account activated.' : 'Account suspended and signed out.');
        } catch (RuntimeException $exception) {
            $this->flash($request, 'error', $exception->getMessage());
        }

        return $this->redirect('/admin/customers/' . $id);
    }

    public function adjustCredit(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_CREDIT);
        $this->findCustomer($id);
        $data = $this->validate($request, ['amount' => 'required|decimal', 'reason' => 'required|string|max:255']);
        $currency = $this->settings->string('general.default_currency') ?: 'EUR';
        $amount = Money::parse((string) $data['amount'], $currency);
        if ($amount === 0 || abs($amount) > 100_000_00) {
            throw ValidationException::withMessages(['amount' => 'Enter a non-zero amount up to 100,000.']);
        }

        try {
            $balance = $this->credit->adjust($id, $amount, (string) $data['reason'], 'admin', null, $this->user($request)->id);
            $this->audit($request, 'customer.credit_adjusted', 'user', $id, ['amount' => $amount, 'reason' => $data['reason']]);
            $this->flash($request, 'success', 'Credit balance updated to ' . Money::format($balance, $currency) . '.');
        } catch (InsufficientCreditException $exception) {
            $this->flash($request, 'error', $exception->getMessage());
        }

        return $this->redirect('/admin/customers/' . $id);
    }

    public function sendPasswordReset(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_MANAGE);
        $customer = $this->findCustomer($id);
        $this->customers->sendPasswordSetupLink($customer);
        $this->audit($request, 'customer.password_reset_sent', 'user', $id);
        $this->flash($request, 'success', 'A password reset link has been e-mailed to the customer.');

        return $this->redirect('/admin/customers/' . $id);
    }

    public function verifyEmail(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_MANAGE);
        $customer = $this->findCustomer($id);
        if ($request->string('action') === 'mark') {
            $this->users->update($id, ['email_verified_at' => $this->clock->nowString()] + ($customer->status === User::STATUS_PENDING ? ['status' => User::STATUS_ACTIVE] : []));
            $this->audit($request, 'customer.email_marked_verified', 'user', $id);
            $this->flash($request, 'success', 'E-mail address marked as verified.');
        } else {
            $this->auth->sendVerification($customer);
            $this->audit($request, 'customer.verification_resent', 'user', $id);
            $this->flash($request, 'success', 'Confirmation link sent.');
        }

        return $this->redirect('/admin/customers/' . $id);
    }

    public function resetTwoFactor(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_MANAGE);
        $this->customers->resetTwoFactor($this->findCustomer($id), $this->user($request)->id, $request->clientIp());
        $this->flash($request, 'success', 'Two-factor authentication has been disabled for this customer; sessions and API tokens have been revoked.');

        return $this->redirect('/admin/customers/' . $id);
    }

    public function revokeSessions(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_MANAGE);
        $count = $this->customers->revokeSessions($this->findCustomer($id), $this->user($request)->id, $request->clientIp());
        $this->flash($request, 'success', sprintf('%d session(s) signed out; all API tokens have been revoked.', $count));

        return $this->redirect('/admin/customers/' . $id);
    }

    public function close(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::CUSTOMERS_DELETE);
        $customer = $this->findCustomer($id);
        $data = $this->validate($request, ['confirm_email' => 'required|string|max:254']);
        if (!hash_equals(mb_strtolower($customer->email), mb_strtolower((string) $data['confirm_email']))) {
            throw ValidationException::withMessages(['confirm_email' => 'Type the customer\'s e-mail address to confirm.']);
        }

        try {
            $this->customers->closeAndAnonymize($customer, $this->user($request)->id, $request->clientIp());
            $this->flash($request, 'success', 'The account has been closed and personal data removed.');
        } catch (RuntimeException $exception) {
            $this->flash($request, 'error', $exception->getMessage());
        }

        return $this->redirect('/admin/customers/' . $id);
    }

    private function findCustomer(int $id): User
    {
        $user = $this->users->find($id);
        if ($user === null || $user->isStaff()) {
            throw HttpException::notFound();
        }

        return $user;
    }

    private function assertGroupExists(mixed $groupId): void
    {
        if ($groupId !== null && $this->groups->find((int) $groupId) === null) {
            throw ValidationException::withMessages(['customer_group_id' => 'The selected group does not exist.']);
        }
    }
}
