<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Audit\AuditLogger;
use Nebrix\Auth\LoginHistory;
use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;

/**
 * Read-only views of the audit log and the login history.
 */
final class LogController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly LoginHistory $history,
    ) {
    }

    public function auditLog(Request $request): Response
    {
        $filters = [
            'action' => mb_substr($request->string('action'), 0, 100),
            'target_type' => mb_substr($request->string('target_type'), 0, 50),
            'target_id' => mb_substr($request->string('target_id'), 0, 64),
        ];
        $actor = $request->string('actor');
        if (ctype_digit($actor)) {
            $filters['actor'] = (int) $actor;
        }
        $page = $this->page($request);

        return $this->render($request, 'admin/logs/audit.twig', ['result' => $this->auditLogger->paginate($filters, $page), 'filters' => $filters, 'page' => $page]);
    }

    public function logins(Request $request): Response
    {
        $filters = [
            'success' => in_array($request->string('success'), ['0', '1'], true) ? $request->string('success') : '',
            'staff_only' => $request->string('staff') === '1',
            'search' => mb_substr($request->string('search'), 0, 100),
        ];
        $page = $this->page($request);

        return $this->render($request, 'admin/logs/logins.twig', ['result' => $this->history->paginate($filters, $page), 'filters' => $filters, 'page' => $page]);
    }
}
