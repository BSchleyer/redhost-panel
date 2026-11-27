<?php

declare(strict_types=1);

namespace Nebrix\Api\Middleware;

use Nebrix\Api\ApiException;
use Nebrix\Api\ApiScope;
use Nebrix\Api\ApiToken;
use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Authorization\Permission;
use Nebrix\Http\Middleware\ParameterizedMiddleware;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Settings\SettingsRepository;

/**
 * "api.scope:services:read" – the token must carry every listed scope.
 *
 * Admin scopes additionally require, at request time, admin access, the
 * permission mapped to the scope and (when enforced) two-factor
 * authentication – the same conditions as the admin web interface.
 */
final class RequireApiScope implements ParameterizedMiddleware
{
    /** @var list<string> */
    private array $scopes = [];

    public function __construct(
        private readonly Gate $gate,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function withParameters(array $parameters): static
    {
        foreach ($parameters as $scope) {
            if (!ApiScope::exists($scope)) {
                throw new \RuntimeException(sprintf('Unknown API scope "%s".', $scope));
            }
        }
        $clone = clone $this;
        $clone->scopes = $parameters;

        return $clone;
    }

    public function process(Request $request, callable $next): Response
    {
        $token = $request->attribute('api_token');
        $user = $request->attribute('user');
        if (!$token instanceof ApiToken || !$user instanceof User || $this->scopes === []) {
            throw ApiException::unauthenticated();
        }

        foreach ($this->scopes as $scope) {
            if (!$token->hasScope($scope)) {
                throw new ApiException(403, 'insufficient_scope', sprintf('The token lacks the scope "%s".', $scope));
            }
            $permissions = ApiScope::permissionsFor($scope);
            if ($permissions === []) {
                continue;
            }
            foreach ([Permission::ADMIN_ACCESS, ...$permissions] as $permission) {
                if (!$this->gate->allows($user, $permission)) {
                    throw new ApiException(403, 'forbidden', 'Your account no longer has the permission this request needs.');
                }
            }
            if ($this->settings->bool('auth.staff_require_2fa') && !$user->hasTwoFactor()) {
                throw new ApiException(403, 'two_factor_required', 'Two-factor authentication is required for staff accounts.');
            }
        }

        return $next($request);
    }
}
