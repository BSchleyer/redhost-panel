<?php

declare(strict_types=1);

namespace Nebrix\Controller\Customer;

use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;

final class DashboardController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->render($request, 'customer/dashboard.twig');
    }
}
