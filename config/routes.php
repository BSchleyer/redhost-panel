<?php

declare(strict_types=1);

use Nebrix\Controller\HealthController;
use Nebrix\Http\Router;

/*
 * Route table. Middleware aliases: auth, guest, verified, staff,
 * permission:<permission>[,<permission>…]
 */
return static function (Router $r): void {
    $r->get('/health', [HealthController::class, 'show']);
    $r->get('/branding.css', [Nebrix\Controller\BrandingController::class, 'stylesheet']);
};
