<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Http\Request;
use Nebrix\Http\Response;

interface Middleware
{
    /**
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}
