<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Repository\UserRepository;

/**
 * Resolves the logged-in user from the session on every request. Accounts
 * that were suspended or closed in the meantime are logged out immediately.
 */
final class LoadAuthenticatedUser implements Middleware
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $session = $request->session();
        $userId = $session->userId();

        if ($userId !== null) {
            $user = $this->users->find($userId);
            if ($user === null || !$user->isActive()) {
                $session->invalidate();
                $session->flash('error', 'Your session has ended. Please sign in again.');
            } else {
                $request->setAttribute('user', $user);
            }
        }

        return $next($request);
    }
}
