<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Core\Config;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Security\Csrf;

/**
 * Verifies the CSRF token on every state-changing request. Routes can only
 * opt out with the explicit route option `csrf => false`, which is reserved
 * for payment webhooks that are authenticated by provider signatures.
 *
 * As defense in depth, a present Origin header must match APP_URL.
 */
final class VerifyCsrfToken implements Middleware
{
    public function __construct(private readonly Config $config)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $route = $request->attribute('_route');
        $exempt = $route instanceof \Nebrix\Http\Route && $route->csrf === false;

        if (!$exempt && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $this->assertSameOrigin($request);

            $token = $request->input(Csrf::FIELD) ?? $request->header(Csrf::HEADER);
            if (!Csrf::validate($request->session(), $token)) {
                throw new HttpException(419, 'Your session has expired or the form was submitted twice. Please reload the page and try again.');
            }
        }

        return $next($request);
    }

    private function assertSameOrigin(Request $request): void
    {
        $origin = $request->header('Origin');
        if ($origin === null || $origin === '' || $origin === 'null') {
            return;
        }

        $appUrl = $this->config->string('app.url');
        $expectedHost = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $requestHost = strtolower(explode(':', $request->header('Host') ?? '')[0]);

        if ($originHost !== '' && $originHost !== $expectedHost && $originHost !== $requestHost) {
            throw new HttpException(419, 'Cross-origin form submissions are not allowed.');
        }
    }
}
