<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Api\ApiTokenService;
use Nebrix\Http\Controller;
use Nebrix\Repository\UserRepository;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;

/**
 * Admin → API tokens: overview of all tokens with revocation (permission
 * api_tokens.manage). Token secrets are never shown – only their prefix.
 */
final class ApiTokenController extends Controller
{
    public function __construct(
        private readonly ApiTokenService $tokens,
        private readonly UserRepository $users,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => mb_substr($request->string('search'), 0, 100),
            'state' => in_array($request->string('state'), ['active', 'inactive'], true) ? $request->string('state') : '',
        ];
        $page = $this->page($request);
        $result = $this->tokens->paginate($filters, $page, 50);
        foreach ($result['items'] as $index => $row) {
            $scopes = json_decode((string) $row['scopes'], true);
            $result['items'][$index]['scope_list'] = is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [];
        }

        return $this->render($request, 'admin/api_tokens/index.twig', [
            'result' => $result,
            'filters' => $filters,
            'page' => $page,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function revoke(Request $request, int $id): Response
    {
        $token = $this->tokens->find($id) ?? throw HttpException::notFound();
        $this->guardOwner($request, $token->userId);
        $this->tokens->revoke($token, $this->user($request)->id, $request->clientIp());
        $this->flash($request, 'success', 'The API token has been revoked.');

        return $this->redirect('/admin/api-tokens');
    }

    public function revokeForUser(Request $request, int $userId): Response
    {
        $this->guardOwner($request, $userId);
        $count = $this->tokens->revokeAllForUser($userId, $this->user($request)->id, $request->clientIp());
        $this->flash($request, 'success', sprintf('%d API token(s) revoked.', $count));

        return $this->redirect('/admin/api-tokens');
    }

    /** Only super administrators manage super administrators (same rule as for staff accounts). */
    private function guardOwner(Request $request, int $ownerId): void
    {
        $owner = $this->users->find($ownerId);
        if ($owner !== null && $owner->isSuperAdmin() && !$this->user($request)->isSuperAdmin()) {
            throw HttpException::forbidden('Only super administrators can revoke the tokens of a super administrator.');
        }
    }
}
