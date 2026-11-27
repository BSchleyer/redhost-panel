<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Authorization\Permission;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Settings\SettingsRepository;

/**
 * Guards the administration area: requires the admin.access permission and,
 * when enforced in the settings, an enabled second factor.
 */
final class RequireStaff implements Middleware
{
    public function __construct(
        private readonly Gate $gate,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $user = $request->attribute('user');
        if (!$user instanceof User || !$this->gate->allows($user, Permission::ADMIN_ACCESS)) {
            throw HttpException::forbidden();
        }

        if ($this->settings->bool('auth.staff_require_2fa') && !$user->hasTwoFactor()) {
            $request->session()->flash('warning', 'Two-factor authentication is required for staff accounts. Please enable it to access the administration area.');

            return Response::redirect('/account/security');
        }

        return $next($request);
    }
}
