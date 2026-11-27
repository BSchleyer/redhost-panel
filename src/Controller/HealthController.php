<?php

declare(strict_types=1);

namespace Nebrix\Controller;

use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\Response;
use Throwable;

/**
 * Liveness/readiness probe for load balancers and container orchestration.
 * Exposes no version numbers or internal details.
 */
final class HealthController extends Controller
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function show(): Response
    {
        try {
            $this->db->fetchValue('SELECT 1');
            $database = 'ok';
        } catch (Throwable) {
            $database = 'unavailable';
        }

        $healthy = $database === 'ok';

        return Response::json(['status' => $healthy ? 'ok' : 'degraded', 'database' => $database], $healthy ? 200 : 503);
    }
}
