<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Auth\User;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;

final class Authenticate implements Middleware
{
    public function process(Request $request, callable $next): Response
    {
        if (!$request->attribute('user') instanceof User) {
            if ($request->expectsJson() || !$request->isMethod('GET')) {
                throw new HttpException(401, 'Please sign in to continue.');
            }

            $request->session()->put('url.intended', $request->path());

            return Response::redirect('/login');
        }

        return $next($request);
    }
}
