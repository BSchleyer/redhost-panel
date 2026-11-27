<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Http\Request;
use Nebrix\Http\Response;

/**
 * Route-level permission check: "permission:customers.view". Passing several
 * permissions ("permission:a,b") requires any one of them.
 */
final class RequirePermission implements ParameterizedMiddleware
{
    /** @var list<string> */
    private array $permissions = [];

    public function __construct(private readonly Gate $gate)
    {
    }

    public function withParameters(array $parameters): static
    {
        $clone = clone $this;
        $clone->permissions = $parameters;

        return $clone;
    }

    public function process(Request $request, callable $next): Response
    {
        $user = $request->attribute('user');
        if (!$user instanceof User || $this->permissions === [] || !$this->gate->allowsAny($user, $this->permissions)) {
            throw \Nebrix\Http\HttpException::forbidden();
        }

        return $next($request);
    }
}
