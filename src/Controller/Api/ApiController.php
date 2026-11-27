<?php

declare(strict_types=1);

namespace Nebrix\Controller\Api;

use Nebrix\Api\ApiException;
use Nebrix\Api\ApiToken;
use Nebrix\Audit\AuditLogger;
use Nebrix\Auth\User;
use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Security\RateLimiter;
use Nebrix\Security\Validator;

/**
 * Base class of the REST API controllers: JSON input, validation,
 * pagination and the response envelope ({"data": …, "meta": …}).
 *
 * Controllers call the same repositories and services as the web interface;
 * business rules are never re-implemented here.
 */
abstract class ApiController extends Controller
{
    protected const MAX_PER_PAGE = 100;

    /**
     * Request body (JSON object or form data) merged with the query string.
     *
     * @return array<string, mixed>
     */
    protected function input(Request $request): array
    {
        $json = $request->attribute('json');

        return (is_array($json) ? $json : []) + $request->all();
    }

    /**
     * @param array<string, string|list<string>> $rules
     * @return array<string, mixed>
     */
    protected function validateInput(Request $request, array $rules): array
    {
        return Validator::validate($this->input($request), $rules);
    }

    protected function token(Request $request): ApiToken
    {
        $token = $request->attribute('api_token');
        if (!$token instanceof ApiToken) {
            throw ApiException::unauthenticated();
        }

        return $token;
    }

    /**
     * @return array{0: int, 1: int} page, per page
     */
    protected function pagination(Request $request): array
    {
        $perPage = $request->query('per_page', '25');
        $perPage = is_string($perPage) && ctype_digit($perPage) ? max(1, min(self::MAX_PER_PAGE, (int) $perPage)) : 25;

        return [$this->page($request), $perPage];
    }

    /**
     * @param list<array<string, mixed>> $items already transformed
     */
    protected function paginated(array $items, int $total, int $page, int $perPage): Response
    {
        return Response::json([
            'data' => $items,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))],
        ]);
    }

    /**
     * Pages a list that the repository returns completely (small per-user lists).
     *
     * @template T
     * @param list<T> $all
     * @param callable(T): array<string, mixed> $transform
     */
    protected function pageOf(Request $request, array $all, callable $transform): Response
    {
        [$page, $perPage] = $this->pagination($request);

        return $this->paginated(array_map($transform, array_slice($all, ($page - 1) * $perPage, $perPage)), count($all), $page, $perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function data(array $data, int $status = 200): Response
    {
        return Response::json(['data' => $data], $status);
    }

    /** Shared limiter keys with the web interface, so the API is no way around them. */
    protected function throttle(string $key, int $max, int $seconds, string $message = 'Too many requests. Please try again later.'): void
    {
        $limiter = $this->container->get(RateLimiter::class);
        if (!$limiter->attempt($key, $max, $seconds)) {
            throw ApiException::rateLimited($limiter->availableIn($key), $message);
        }
    }

    /**
     * Audit entry marked as made through the API (with the token ID).
     *
     * @param array<string, mixed> $details
     */
    protected function apiAudit(Request $request, string $action, ?string $targetType = null, int|string|null $targetId = null, array $details = []): void
    {
        $user = $request->attribute('user');
        $token = $request->attribute('api_token');
        $this->container->get(AuditLogger::class)->log(
            $user instanceof User ? $user->id : null,
            $action,
            $targetType,
            $targetId,
            $details + ['via' => 'api', 'token_id' => $token instanceof ApiToken ? $token->id : null],
            $request->clientIp(),
        );
    }

    /**
     * Parses an optional positive integer query filter.
     */
    protected function intQuery(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        return is_string($value) && preg_match('/^\d{1,18}$/', $value) === 1 ? (int) $value : null;
    }

    protected function stringQuery(Request $request, string $key, int $max = 100): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }
}
