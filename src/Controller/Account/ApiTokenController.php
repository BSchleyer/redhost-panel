<?php

declare(strict_types=1);

namespace Nebrix\Controller\Account;

use InvalidArgumentException;
use Nebrix\Api\ApiScope;
use Nebrix\Api\ApiTokenService;
use Nebrix\Auth\AuthService;
use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Authorization\Permission;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Security\RateLimiter;
use Nebrix\Security\ValidationException;
use Nebrix\Settings\SettingsRepository;

/**
 * Account → API tokens: create (password confirmation, shown once), list
 * and revoke the user's own personal access tokens.
 */
final class ApiTokenController extends Controller
{
    public function __construct(
        private readonly ApiTokenService $tokens,
        private readonly AuthService $auth,
        private readonly Gate $gate,
        private readonly SettingsRepository $settings,
        private readonly RateLimiter $limiter,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->renderPage($request, $this->user($request));
    }

    public function store(Request $request): Response
    {
        $user = $this->user($request);
        if ($this->unavailableReason($user) !== null) {
            throw HttpException::forbidden();
        }
        $data = $this->validate($request, [
            'name' => 'required|string|min:1|max:100',
            'scopes' => 'required|array',
            'days' => 'required|int',
            'allowed_ips' => 'nullable|string|max:2000',
            'password' => 'required|string|max:1024',
        ]);
        // A hijacked session must not be able to mint long-lived credentials.
        if (!$this->auth->confirmPassword($user, (string) $data['password'])) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }
        if (!$this->limiter->attempt('api-token-create:' . $user->id, 10, 3600)) {
            throw HttpException::tooManyRequests();
        }

        $scopes = array_values(array_filter((array) $data['scopes'], 'is_string'));
        $ranges = preg_split('/[\s,]+/', (string) ($data['allowed_ips'] ?? '')) ?: [];
        try {
            $created = $this->tokens->create($user, (string) $data['name'], $scopes, (int) $data['days'], $ranges, $request->clientIp());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['scopes' => $exception->getMessage()]);
        }

        // The token is rendered once in this response and never stored in plaintext (no redirect/flash).
        return $this->renderPage($request, $user, $created['secret']);
    }

    public function revoke(Request $request, int $id): Response
    {
        $user = $this->user($request);
        $token = $this->tokens->find($id);
        if ($token === null || $token->userId !== $user->id) {
            throw HttpException::notFound();
        }
        $this->tokens->revoke($token, $user->id, $request->clientIp());
        $this->flash($request, 'success', 'The API token has been revoked.');

        return $this->redirect('/account/api-tokens');
    }

    private function renderPage(Request $request, User $user, ?string $newToken = null): Response
    {
        $available = ApiScope::availableFor($user, $this->gate);
        $customer = array_intersect_key($available, ApiScope::customer());

        $response = $this->render($request, 'account/api_tokens.twig', [
            'tokens' => $this->tokens->forUser($user->id),
            'customer_scopes' => $customer,
            'admin_scopes' => array_diff_key($available, $customer),
            'lifetimes' => array_combine(
                array_map('strval', ApiTokenService::LIFETIMES),
                array_map(static fn (int $days): string => $days . ' days', ApiTokenService::LIFETIMES),
            ),
            'new_token' => $newToken,
            'unavailable' => $this->unavailableReason($user),
            'now' => gmdate('Y-m-d H:i:s'),
        ]);
        $response->setHeader('Cache-Control', 'no-store');

        return $response;
    }

    private function unavailableReason(User $user): ?string
    {
        if (!$this->settings->bool('api.enabled')) {
            return 'The REST API is not enabled on this installation.';
        }
        if (!$this->settings->bool('api.customer_tokens') && !$this->gate->allows($user, Permission::ADMIN_ACCESS)) {
            return 'API access is currently only available to staff.';
        }

        return null;
    }
}
