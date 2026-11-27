<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Core\Config;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Session\SessionStore;

/**
 * Loads the session from its cookie, persists it after the request and
 * sets a hardened cookie (Secure, HttpOnly, SameSite=Lax, __Host- prefix on
 * HTTPS).
 */
final class StartSession implements Middleware
{
    public function __construct(
        private readonly SessionStore $store,
        private readonly Config $config,
    ) {
    }

    public function cookieName(Request $request): string
    {
        return $this->isSecure($request) ? '__Host-nebrix_session' : 'nebrix_session';
    }

    private function isSecure(Request $request): bool
    {
        return $this->config->bool('session.secure_cookie') || $request->isSecure();
    }

    public function process(Request $request, callable $next): Response
    {
        $cookieName = $this->cookieName($request);
        $id = $request->cookie($cookieName);
        $session = ($id !== null ? $this->store->load($id) : null) ?? $this->store->create();
        $session->ageFlash();
        $request->setSession($session);

        $response = $next($request);

        $this->store->save($session, $request->clientIp(), $request->userAgent());

        $cookieMustBeSent = $session->previousId() !== null
            || ($session->isNew() && !$session->isEmpty())
            || ($id !== null && $id !== $session->id());

        if ($cookieMustBeSent) {
            if ($session->isEmpty()) {
                $response->withCookie($cookieName, '', $this->cookieOptions($request, time() - 3600));
            } else {
                $response->withCookie($cookieName, $session->id(), $this->cookieOptions($request));
            }
        }

        return $response;
    }

    /**
     * @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: string}
     */
    private function cookieOptions(Request $request, int $expires = 0): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => $this->isSecure($request),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
