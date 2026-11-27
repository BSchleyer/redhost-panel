<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Admin\DashboardStats;
use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;

final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStats $stats)
    {
    }

    public function show(Request $request): Response
    {
        return $this->render($request, 'admin/dashboard.twig', ['stats' => $this->stats->collect()]);
    }
}
