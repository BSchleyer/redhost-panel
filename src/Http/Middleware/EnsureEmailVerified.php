<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Auth\User;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Settings\SettingsRepository;

final class EnsureEmailVerified implements Middleware
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $user = $request->attribute('user');
        if ($user instanceof User
            && !$user->isEmailVerified()
            && !$user->isStaff()
            && $this->settings->bool('auth.require_email_verification')) {
            return Response::redirect('/email/verify');
        }

        return $next($request);
    }
}
