<?php

declare(strict_types=1);

use Nebrix\Controller\Account;
use Nebrix\Controller\Admin;
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

    // -------------------------------------------------------- administration
    // Every admin route requires staff access (admin.access + optional 2FA
    // enforcement) and a specific permission. Controllers re-check permissions
    // for actions that need more than read access.
    $r->group(['prefix' => '/admin', 'middleware' => ['auth', 'staff']], static function (Router $r): void {
        $r->get('', [Admin\DashboardController::class, 'show'], ['middleware' => ['permission:dashboard.view']]);

        // Settings (permission checked per page in the controller)
        $r->get('/settings/{page:[a-z]+}', [Admin\SettingsController::class, 'show']);
        $r->post('/settings/{page:[a-z]+}', [Admin\SettingsController::class, 'save']);
        $r->post('/branding/upload', [Admin\SettingsController::class, 'uploadBranding']);
        $r->post('/branding/remove', [Admin\SettingsController::class, 'removeBranding']);
        $r->post('/email/test', [Admin\SettingsController::class, 'testEmail']);
        $r->post('/system/cache/clear', [Admin\SettingsController::class, 'clearCache']);

        $r->group(['middleware' => ['permission:mail_templates.manage']], static function (Router $r): void {
            $r->get('/mail-templates', [Admin\MailTemplateController::class, 'index']);
            $r->get('/mail-templates/{key:[a-z_]+}', [Admin\MailTemplateController::class, 'edit']);
            $r->post('/mail-templates/{key:[a-z_]+}', [Admin\MailTemplateController::class, 'update']);
            $r->post('/mail-templates/{key:[a-z_]+}/reset', [Admin\MailTemplateController::class, 'reset']);
            $r->get('/mail-templates/{key:[a-z_]+}/preview', [Admin\MailTemplateController::class, 'preview']);
        });

        // Customers
        $r->group(['middleware' => ['permission:customers.view']], static function (Router $r): void {
            $r->get('/customers', [Admin\CustomerController::class, 'index']);
            $r->get('/customers/create', [Admin\CustomerController::class, 'create']);
            $r->post('/customers', [Admin\CustomerController::class, 'store']);
            $r->get('/customers/{id:\d+}', [Admin\CustomerController::class, 'show']);
            $r->get('/customers/{id:\d+}/edit', [Admin\CustomerController::class, 'edit']);
            $r->post('/customers/{id:\d+}', [Admin\CustomerController::class, 'update']);
            $r->post('/customers/{id:\d+}/status', [Admin\CustomerController::class, 'status']);
            $r->post('/customers/{id:\d+}/credit', [Admin\CustomerController::class, 'adjustCredit']);
            $r->post('/customers/{id:\d+}/password-reset', [Admin\CustomerController::class, 'sendPasswordReset']);
            $r->post('/customers/{id:\d+}/verification', [Admin\CustomerController::class, 'verifyEmail']);
            $r->post('/customers/{id:\d+}/two-factor/reset', [Admin\CustomerController::class, 'resetTwoFactor']);
            $r->post('/customers/{id:\d+}/sessions/revoke', [Admin\CustomerController::class, 'revokeSessions']);
            $r->post('/customers/{id:\d+}/close', [Admin\CustomerController::class, 'close']);
        });

        $r->group(['middleware' => ['permission:customer_groups.manage']], static function (Router $r): void {
            $r->get('/customer-groups', [Admin\CustomerGroupController::class, 'index']);
            $r->post('/customer-groups', [Admin\CustomerGroupController::class, 'store']);
            $r->get('/customer-groups/{id:\d+}', [Admin\CustomerGroupController::class, 'edit']);
            $r->post('/customer-groups/{id:\d+}', [Admin\CustomerGroupController::class, 'update']);
            $r->post('/customer-groups/{id:\d+}/delete', [Admin\CustomerGroupController::class, 'delete']);
        });

        // Products
        $r->group(['middleware' => ['permission:products.view']], static function (Router $r): void {
            $r->get('/products', [Admin\ProductController::class, 'index']);
            $r->get('/products/create', [Admin\ProductController::class, 'create']);
            $r->post('/products', [Admin\ProductController::class, 'store']);
            $r->get('/products/{id:\d+}/edit', [Admin\ProductController::class, 'edit']);
            $r->post('/products/{id:\d+}', [Admin\ProductController::class, 'update']);
        });

        // Staff, roles, logs
        $r->group(['middleware' => ['permission:staff.view']], static function (Router $r): void {
            $r->get('/staff', [Admin\StaffController::class, 'index']);
            $r->get('/staff/create', [Admin\StaffController::class, 'create']);
            $r->post('/staff', [Admin\StaffController::class, 'store']);
            $r->get('/staff/{id:\d+}/edit', [Admin\StaffController::class, 'edit']);
            $r->post('/staff/{id:\d+}', [Admin\StaffController::class, 'update']);
            $r->post('/staff/{id:\d+}/two-factor/reset', [Admin\StaffController::class, 'resetTwoFactor']);
            $r->post('/staff/{id:\d+}/password-reset', [Admin\StaffController::class, 'sendPasswordReset']);
            $r->get('/login-history', [Admin\LogController::class, 'logins']);
        });

        $r->group(['middleware' => ['permission:roles.manage']], static function (Router $r): void {
            $r->get('/roles', [Admin\RoleController::class, 'index']);
            $r->post('/roles', [Admin\RoleController::class, 'store']);
            $r->get('/roles/{id:\d+}', [Admin\RoleController::class, 'edit']);
            $r->post('/roles/{id:\d+}', [Admin\RoleController::class, 'update']);
            $r->post('/roles/{id:\d+}/delete', [Admin\RoleController::class, 'delete']);
        });

        $r->get('/audit-log', [Admin\LogController::class, 'auditLog'], ['middleware' => ['permission:audit.view']]);

        $r->get('/jobs', [Admin\JobController::class, 'index'], ['middleware' => ['permission:jobs.view']]);
        $r->post('/jobs/{id:\d+}/retry', [Admin\JobController::class, 'retry'], ['middleware' => ['permission:jobs.manage']]);
        $r->post('/jobs/{id:\d+}/cancel', [Admin\JobController::class, 'cancel'], ['middleware' => ['permission:jobs.manage']]);
    });
};
