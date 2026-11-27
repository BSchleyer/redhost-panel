<?php

declare(strict_types=1);

namespace Nebrix\Controller\Api;

use Nebrix\Api\OpenApi;
use Nebrix\Api\Resource;
use Nebrix\Http\Request;
use Nebrix\Http\Response;

/**
 * API entry points: index, OpenAPI document and the token owner's account.
 */
final class AccountController extends ApiController
{
    public function index(Request $request): Response
    {
        return Response::json([
            'name' => 'Nebrix API',
            'version' => 'v1',
            'documentation' => '/api/v1/openapi.json',
        ]);
    }

    public function openApi(Request $request): Response
    {
        $response = Response::json(OpenApi::document());
        $response->setHeader('Cache-Control', 'public, max-age=300');

        return $response;
    }

    public function me(Request $request): Response
    {
        return $this->data(Resource::account($this->user($request)) + ['token' => Resource::token($this->token($request))]);
    }
}
