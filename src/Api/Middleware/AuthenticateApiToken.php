<?php

declare(strict_types=1);

namespace Nebrix\Api\Middleware;

use Nebrix\Api\ApiException;
use Nebrix\Api\ApiTokenService;
use Nebrix\Authorization\Gate;
use Nebrix\Authorization\Permission;
use Nebrix\Http\Middleware\Middleware;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Security\RateLimiter;
use Nebrix\Settings\SettingsRepository;

/**
 * Authenticates "Authorization: Bearer nbx_…" and applies the per-user
 * rate limit. Sets the request attributes "user" and "api_token".
 *
 * Failed attempts are counted per client IP; after 30 failures within five
 * minutes further failures answer 429. Valid tokens are never blocked by
 * this counter, so clients behind a shared address cannot be locked out.
 */
final class AuthenticateApiToken implements Middleware
{
    private const FAILURE_LIMIT = 30;
    private const FAILURE_WINDOW = 300;

    public function __construct(
        private readonly ApiTokenService $tokens,
        private readonly RateLimiter $limiter,
        private readonly SettingsRepository $settings,
        private readonly Gate $gate,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $ip = $request->clientIp();
        $header = trim($request->header('Authorization') ?? '');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $match) !== 1) {
            throw ApiException::unauthenticated();
        }

        $resolved = $this->tokens->authenticate($match[1], $ip);
        if ($resolved === null) {
            $failureKey = 'api-auth-failure:' . $ip;
            if ($this->limiter->hit($failureKey, self::FAILURE_WINDOW) > self::FAILURE_LIMIT) {
                throw ApiException::rateLimited($this->limiter->availableIn($failureKey), 'Too many failed authentication attempts.');
            }

            throw ApiException::unauthenticated('The API token is invalid, expired, revoked or not allowed from this address.');
        }
        [$token, $user] = $resolved;

        $isStaff = $this->gate->allows($user, Permission::ADMIN_ACCESS);
        if (!$isStaff && (!$this->settings->bool('api.customer_tokens') || !$this->settings->bool('auth.login_enabled'))) {
            throw new ApiException(403, 'api_access_disabled', 'API access is currently only available to staff.');
        }
        if ($this->settings->bool('auth.require_email_verification') && !$user->isEmailVerified()) {
            throw new ApiException(403, 'email_unverified', 'Verify your e-mail address before using the API.');
        }
        if ($this->settings->bool('system.maintenance_mode') && !$isStaff) {
            throw ApiException::unavailable('The service is in maintenance mode. Please try again later.');
        }

        // Per user (shared by all tokens of the account), so more tokens do not mean more requests.
        $limit = max(10, $this->settings->int('api.rate_limit'));
        $rateKey = 'api-user:' . $user->id;
        $hits = $this->limiter->hit($rateKey, 60);
        if ($hits > $limit) {
            throw ApiException::rateLimited($this->limiter->availableIn($rateKey));
        }

        $request->setAttribute('user', $user);
        $request->setAttribute('api_token', $token);

        $response = $next($request);
        $response->setHeader('X-RateLimit-Limit', (string) $limit);
        $response->setHeader('X-RateLimit-Remaining', (string) max(0, $limit - $hits));

        return $response;
    }
}
