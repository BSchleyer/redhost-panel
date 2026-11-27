<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Auth\User;
use Nebrix\Http\Request;
use Nebrix\Http\Response;

final class RedirectIfAuthenticated implements Middleware
{
    public function process(Request $request, callable $next): Response
    {
        if ($request->attribute('user') instanceof User) {
            return Response::redirect('/dashboard');
        }

        return $next($request);
    }
}
