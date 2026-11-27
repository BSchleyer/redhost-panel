<?php

declare(strict_types=1);

use Nebrix\Controller\Account;
use Nebrix\Controller\Auth;
use Nebrix\Controller\BrandingController;
use Nebrix\Controller\Customer;
use Nebrix\Controller\HealthController;
use Nebrix\Http\Router;

/*
 * Route table. Middleware aliases: auth, guest, verified, staff,
 * permission:<permission>[,<permission>…]
 *
 * Every POST route is CSRF-protected by the global middleware unless it sets
 * 'csrf' => false (only payment webhooks, which verify provider signatures).
 */
return static function (Router $r): void {
    $r->get('/health', [HealthController::class, 'show']);
    $r->get('/branding.css', [BrandingController::class, 'stylesheet']);
    $r->get('/', [Auth\LoginController::class, 'home']);

    // ---------------------------------------------------------------- guests
    $r->group(['middleware' => ['guest']], static function (Router $r): void {
        $r->get('/login', [Auth\LoginController::class, 'show']);
        $r->post('/login', [Auth\LoginController::class, 'login']);
        $r->get('/two-factor', [Auth\LoginController::class, 'showTwoFactor']);
        $r->post('/two-factor', [Auth\LoginController::class, 'twoFactor']);
        $r->get('/register', [Auth\RegisterController::class, 'show']);
        $r->post('/register', [Auth\RegisterController::class, 'register']);
        $r->get('/password/forgot', [Auth\PasswordResetController::class, 'showRequest']);
        $r->post('/password/forgot', [Auth\PasswordResetController::class, 'sendLink']);
        $r->get('/password/reset/{token:[a-f0-9]{64}}', [Auth\PasswordResetController::class, 'showReset']);
        $r->post('/password/reset', [Auth\PasswordResetController::class, 'reset']);
        $r->post('/email/resend', [Auth\EmailVerificationController::class, 'resendGuest']);
    });

    $r->get('/email/verify/{token:[a-f0-9]{64}}', [Auth\EmailVerificationController::class, 'verify']);

    // ------------------------------------------------- authenticated, any state
    $r->group(['middleware' => ['auth']], static function (Router $r): void {
        $r->post('/logout', [Auth\LoginController::class, 'logout']);
        $r->get('/email/verify', [Auth\EmailVerificationController::class, 'notice']);
        $r->post('/email/verify/resend', [Auth\EmailVerificationController::class, 'resend']);
    });

    // ----------------------------------------- authenticated + verified e-mail
    $r->group(['middleware' => ['auth', 'verified']], static function (Router $r): void {
        $r->get('/dashboard', [Customer\DashboardController::class, 'show']);

        $r->get('/account/profile', [Account\ProfileController::class, 'show']);
        $r->post('/account/profile', [Account\ProfileController::class, 'update']);
        $r->get('/account/security', [Account\SecurityController::class, 'show']);
        $r->post('/account/password', [Account\SecurityController::class, 'changePassword']);
        $r->get('/account/two-factor/setup', [Account\SecurityController::class, 'setupTwoFactor']);
        $r->post('/account/two-factor/enable', [Account\SecurityController::class, 'enableTwoFactor']);
        $r->post('/account/two-factor/disable', [Account\SecurityController::class, 'disableTwoFactor']);
        $r->post('/account/two-factor/recovery-codes', [Account\SecurityController::class, 'regenerateRecoveryCodes']);
        $r->post('/account/sessions/logout-others', [Account\SecurityController::class, 'logoutOtherSessions']);
    });
};
