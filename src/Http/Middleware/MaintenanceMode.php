<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Authorization\Permission;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Settings\SettingsRepository;
use Nebrix\View\View;

/**
 * While maintenance mode is on, only staff (admin.access) can use the panel.
 * Login, static assets and payment webhooks stay reachable so that staff can
 * sign in and payments that complete during maintenance are not lost.
 */
final class MaintenanceMode implements Middleware
{
    // Cancellation and withdrawal must stay available at all times (§ 312k BGB).
    private const ALWAYS_ALLOWED_PREFIXES = ['/login', '/logout', '/two-factor', '/webhooks/', '/assets/', '/health', '/branding.css', '/cancel-contract', '/withdraw-contract'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly Gate $gate,
        private readonly View $view,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$this->settings->bool('system.maintenance_mode')) {
            return $next($request);
        }

        $user = $request->attribute('user');
        if ($user instanceof User && $this->gate->allows($user, Permission::ADMIN_ACCESS)) {
            return $next($request);
        }

        foreach (self::ALWAYS_ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($request->path(), rtrim($prefix, '/')) ) {
                return $next($request);
            }
        }

        $response = $this->view->response('errors/maintenance.twig', [
            'message' => $this->settings->string('system.maintenance_message'),
        ], 503, $request);
        $response->setHeader('Retry-After', '600');

        return $response;
    }
}
