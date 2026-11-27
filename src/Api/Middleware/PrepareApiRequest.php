<?php

declare(strict_types=1);

namespace Nebrix\Api\Middleware;

use Nebrix\Api\ApiException;
use Nebrix\Http\Middleware\Middleware;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Settings\SettingsRepository;

/**
 * Global middleware of the stateless API stack: the API must be enabled,
 * request bodies must be JSON objects (or form data) of at most 1 MiB. The
 * decoded body is available as the request attribute "json".
 */
final class PrepareApiRequest implements Middleware
{
    private const MAX_BODY = 1048576;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$this->settings->bool('api.enabled')) {
            throw new ApiException(404, 'api_disabled', 'The API is not enabled on this installation.');
        }

        $body = $request->body();
        if ($body !== '' && !in_array($request->method(), ['GET', 'HEAD'], true)) {
            if (strlen($body) > self::MAX_BODY) {
                throw new ApiException(413, 'payload_too_large', 'The request body must not exceed 1 MiB.');
            }
            $type = strtolower(trim(explode(';', $request->header('Content-Type') ?? '')[0]));
            if ($type === 'application/json') {
                try {
                    $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    throw new ApiException(400, 'invalid_json', 'The request body is not valid JSON.');
                }
                if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
                    throw new ApiException(400, 'invalid_json', 'The request body must be a JSON object.');
                }
                $request->setAttribute('json', $decoded);
            } elseif ($type !== 'application/x-www-form-urlencoded') {
                throw new ApiException(415, 'unsupported_media_type', 'Send the request body as application/json.');
            }
        }

        return $next($request);
    }
}
