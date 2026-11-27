<?php

declare(strict_types=1);

/*
 * Schema of all runtime settings that administrators manage in the admin
 * panel. Values are stored in nx_settings; defaults apply until changed.
 *
 * Types: string, text, int, bool, email, url, color, select (with options),
 * list (multi-select with options). "secret" values are encrypted at rest and
 * never sent back to the browser.
 */

$languages = ['en' => 'English', 'de' => 'Deutsch'];
$currencies = ['EUR' => 'EUR – Euro', 'USD' => 'USD – US Dollar', 'GBP' => 'GBP – Pound Sterling', 'CHF' => 'CHF – Swiss Franc'];

return [
    // ---------------------------------------------------------------- general
    'general.company_name' => ['group' => 'general', 'type' => 'string', 'default' => 'Nebrix', 'label' => 'Company name', 'rules' => 'required|string|max:150'],
    'general.company_address' => ['group' => 'general', 'type' => 'text', 'default' => '', 'label' => 'Company address', 'help' => 'Shown on invoices and in e-mail footers.'],
    'general.company_email' => ['group' => 'general', 'type' => 'email', 'default' => '', 'label' => 'Contact e-mail'],
    'general.company_phone' => ['group' => 'general', 'type' => 'string', 'default' => '', 'label' => 'Contact phone', 'rules' => 'nullable|string|max:50'],
    'general.company_tax_id' => ['group' => 'general', 'type' => 'string', 'default' => '', 'label' => 'VAT / tax ID', 'rules' => 'nullable|string|max:50'],
    'general.support_email' => ['group' => 'general', 'type' => 'email', 'default' => '', 'label' => 'Support e-mail'],
    'general.website_url' => ['group' => 'general', 'type' => 'url', 'default' => '', 'label' => 'Website URL'],
    'general.default_language' => ['group' => 'general', 'type' => 'select', 'default' => 'en', 'label' => 'Default language', 'options' => $languages],
    'general.available_languages' => ['group' => 'general', 'type' => 'list', 'default' => ['en', 'de'], 'label' => 'Available languages', 'options' => $languages],
    'general.default_currency' => ['group' => 'general', 'type' => 'select', 'default' => 'EUR', 'label' => 'Default currency', 'options' => $currencies],
    'general.timezone' => ['group' => 'general', 'type' => 'string', 'default' => 'Europe/Berlin', 'label' => 'Time zone', 'rules' => 'required|timezone'],
    'general.date_format' => ['group' => 'general', 'type' => 'select', 'default' => 'd.m.Y', 'label' => 'Date format', 'options' => ['d.m.Y' => '31.12.2026', 'Y-m-d' => '2026-12-31', 'm/d/Y' => '12/31/2026', 'd/m/Y' => '31/12/2026']],
    'general.time_format' => ['group' => 'general', 'type' => 'select', 'default' => 'H:i', 'label' => 'Time format', 'options' => ['H:i' => '23:59', 'h:i A' => '11:59 PM']],
    'general.terms_url' => ['group' => 'general', 'type' => 'url', 'default' => '', 'label' => 'Terms and conditions URL'],
    'general.privacy_url' => ['group' => 'general', 'type' => 'url', 'default' => '', 'label' => 'Privacy policy URL'],
    'general.imprint_url' => ['group' => 'general', 'type' => 'url', 'default' => '', 'label' => 'Imprint URL'],

    // ------------------------------------------------- registration & security
    'auth.registration_enabled' => ['group' => 'auth', 'type' => 'bool', 'default' => true, 'label' => 'Allow new customer registrations'],
    'auth.login_enabled' => ['group' => 'auth', 'type' => 'bool', 'default' => true, 'label' => 'Allow customer logins', 'help' => 'Staff can always log in.'],
    'auth.require_email_verification' => ['group' => 'auth', 'type' => 'bool', 'default' => true, 'label' => 'Require e-mail verification'],
    'auth.require_legal_acceptance' => ['group' => 'auth', 'type' => 'bool', 'default' => true, 'label' => 'Require acceptance of terms and privacy policy at registration'],
    'auth.staff_require_2fa' => ['group' => 'auth', 'type' => 'bool', 'default' => false, 'label' => 'Require two-factor authentication for staff'],
    'auth.max_login_attempts' => ['group' => 'auth', 'type' => 'int', 'default' => 5, 'label' => 'Failed logins before lockout', 'rules' => 'required|int|min:3|max:50'],
    'auth.lockout_minutes' => ['group' => 'auth', 'type' => 'int', 'default' => 15, 'label' => 'Lockout duration (minutes)', 'rules' => 'required|int|min:1|max:1440'],
    'auth.captcha_provider' => ['group' => 'auth', 'type' => 'select', 'default' => 'none', 'label' => 'CAPTCHA on registration and password reset', 'options' => ['none' => 'Off', 'hcaptcha' => 'hCaptcha', 'turnstile' => 'Cloudflare Turnstile'], 'help' => 'Loads the provider\'s script on these pages; the token is verified server-side.'],
    'auth.captcha_site_key' => ['group' => 'auth', 'type' => 'string', 'default' => '', 'label' => 'CAPTCHA site key', 'rules' => 'nullable|string|max:100|regex:/^[A-Za-z0-9_-]*$/'],
    'auth.captcha_secret' => ['group' => 'auth', 'type' => 'string', 'default' => null, 'label' => 'CAPTCHA secret key', 'secret' => true, 'rules' => 'nullable|string|max:255'],

    // ---------------------------------------------------------------- billing
    'billing.invoice_prefix' => ['group' => 'billing', 'type' => 'string', 'default' => 'NX-', 'label' => 'Invoice number prefix', 'rules' => 'nullable|regex:/^[A-Za-z0-9-]{0,10}$/'],
    'billing.order_prefix' => ['group' => 'billing', 'type' => 'string', 'default' => 'ORD-', 'label' => 'Order number prefix', 'rules' => 'nullable|regex:/^[A-Za-z0-9-]{0,10}$/'],
    'billing.invoice_due_days' => ['group' => 'billing', 'type' => 'int', 'default' => 7, 'label' => 'Invoice payment term (days)', 'rules' => 'required|int|min:0|max:90'],
    'billing.renewal_notice_days' => ['group' => 'billing', 'type' => 'int', 'default' => 7, 'label' => 'Create renewal invoices … days before due date', 'rules' => 'required|int|min:1|max:60'],
    'billing.suspend_grace_days' => ['group' => 'billing', 'type' => 'int', 'default' => 1, 'label' => 'Suspend unpaid services … days after due date', 'rules' => 'required|int|min:0|max:60'],
    'billing.terminate_after_days' => ['group' => 'billing', 'type' => 'int', 'default' => 0, 'label' => 'Terminate suspended services after … days (0 = never automatically)', 'help' => 'Termination deletes the customer\'s server/account on the remote system. Leave at 0 to terminate manually.', 'rules' => 'required|int|min:0|max:365'],
    'billing.supported_currencies' => ['group' => 'billing', 'type' => 'list', 'default' => ['EUR'], 'label' => 'Supported currencies', 'options' => $currencies],

    // --------------------------------------------------------------- branding
    'branding.logo_path' => ['group' => 'branding_files', 'type' => 'string', 'default' => '', 'label' => 'Logo'],
    'branding.favicon_path' => ['group' => 'branding_files', 'type' => 'string', 'default' => '', 'label' => 'Favicon'],
    'branding.primary_color' => ['group' => 'branding', 'type' => 'color', 'default' => '#6d28d9', 'label' => 'Primary color'],
    'branding.secondary_color' => ['group' => 'branding', 'type' => 'color', 'default' => '#0f172a', 'label' => 'Secondary color'],
    'branding.accent_color' => ['group' => 'branding', 'type' => 'color', 'default' => '#22d3ee', 'label' => 'Accent color'],
    'branding.default_theme' => ['group' => 'branding', 'type' => 'select', 'default' => 'system', 'label' => 'Default theme', 'options' => ['system' => 'Follow the device setting', 'light' => 'Light', 'dark' => 'Dark']],
    'branding.login_headline' => ['group' => 'branding', 'type' => 'string', 'default' => 'Welcome to Nebrix', 'label' => 'Login page headline', 'rules' => 'nullable|string|max:120'],
    'branding.login_subtitle' => ['group' => 'branding', 'type' => 'string', 'default' => 'Manage your servers, storage and services in one place.', 'label' => 'Login page subtitle', 'rules' => 'nullable|string|max:255'],
    'branding.dashboard_welcome' => ['group' => 'branding', 'type' => 'text', 'default' => '', 'label' => 'Customer dashboard notice', 'help' => 'Optional plain-text notice shown on every customer dashboard.'],
    'branding.email_header_color' => ['group' => 'branding', 'type' => 'color', 'default' => '#6d28d9', 'label' => 'E-mail header color'],
    'branding.email_footer' => ['group' => 'branding', 'type' => 'text', 'default' => '', 'label' => 'E-mail footer text'],

    // ------------------------------------------------------------------ e-mail
    'mail.enabled' => ['group' => 'mail', 'type' => 'bool', 'default' => false, 'label' => 'Send e-mails via SMTP', 'help' => 'When disabled, e-mails are written to the application log instead.'],
    'mail.host' => ['group' => 'mail', 'type' => 'string', 'default' => '', 'label' => 'SMTP host', 'rules' => 'nullable|hostname'],
    'mail.port' => ['group' => 'mail', 'type' => 'int', 'default' => 587, 'label' => 'SMTP port', 'rules' => 'required|int|min:1|max:65535'],
    'mail.encryption' => ['group' => 'mail', 'type' => 'select', 'default' => 'tls', 'label' => 'Encryption', 'options' => ['tls' => 'STARTTLS', 'ssl' => 'SMTPS (implicit TLS)', 'none' => 'None (not recommended)']],
    'mail.username' => ['group' => 'mail', 'type' => 'string', 'default' => '', 'label' => 'SMTP username', 'rules' => 'nullable|string|max:255'],
    'mail.password' => ['group' => 'mail', 'type' => 'string', 'default' => null, 'label' => 'SMTP password', 'secret' => true, 'rules' => 'nullable|string|max:500'],
    'mail.from_address' => ['group' => 'mail', 'type' => 'email', 'default' => '', 'label' => 'Sender address'],
    'mail.from_name' => ['group' => 'mail', 'type' => 'string', 'default' => 'Nebrix', 'label' => 'Sender name', 'rules' => 'nullable|string|max:100'],

    // --------------------------------------------------------------- payments
    'payments.paypal.enabled' => ['group' => 'payments_paypal', 'type' => 'bool', 'default' => false, 'label' => 'Enable PayPal'],
    'payments.paypal.mode' => ['group' => 'payments_paypal', 'type' => 'select', 'default' => 'sandbox', 'label' => 'Mode', 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live']],
    'payments.paypal.client_id' => ['group' => 'payments_paypal', 'type' => 'string', 'default' => '', 'label' => 'Client ID', 'rules' => 'nullable|string|max:255'],
    'payments.paypal.client_secret' => ['group' => 'payments_paypal', 'type' => 'string', 'default' => null, 'label' => 'Client secret', 'secret' => true, 'rules' => 'nullable|string|max:255'],
    'payments.paypal.webhook_id' => ['group' => 'payments_paypal', 'type' => 'string', 'default' => '', 'label' => 'Webhook ID', 'help' => 'Required for webhook signature verification.', 'rules' => 'nullable|alpha_dash|max:64'],

    'payments.stripe.enabled' => ['group' => 'payments_stripe', 'type' => 'bool', 'default' => false, 'label' => 'Enable Stripe'],
    'payments.stripe.mode' => ['group' => 'payments_stripe', 'type' => 'select', 'default' => 'test', 'label' => 'Mode', 'options' => ['test' => 'Test', 'live' => 'Live']],
    'payments.stripe.publishable_key' => ['group' => 'payments_stripe', 'type' => 'string', 'default' => '', 'label' => 'Publishable key', 'rules' => 'nullable|regex:/^(pk_(test|live)_[A-Za-z0-9]+)?$/'],
    'payments.stripe.secret_key' => ['group' => 'payments_stripe', 'type' => 'string', 'default' => null, 'label' => 'Secret key', 'secret' => true, 'rules' => 'nullable|regex:/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/'],
    'payments.stripe.webhook_secret' => ['group' => 'payments_stripe', 'type' => 'string', 'default' => null, 'label' => 'Webhook signing secret', 'secret' => true, 'rules' => 'nullable|regex:/^whsec_[A-Za-z0-9]+$/'],

    'payments.credit.enabled' => ['group' => 'payments_credit', 'type' => 'bool', 'default' => true, 'label' => 'Allow paying invoices with account credit'],

    // ----------------------------------------------------------------- system
    'system.maintenance_mode' => ['group' => 'system', 'type' => 'bool', 'default' => false, 'label' => 'Maintenance mode', 'help' => 'Customers see a maintenance page; staff with admin access can still sign in.'],
    'system.maintenance_message' => ['group' => 'system', 'type' => 'text', 'default' => 'We are performing scheduled maintenance. Please check back shortly.', 'label' => 'Maintenance message'],
    'system.debug_logging' => ['group' => 'system', 'type' => 'bool', 'default' => false, 'label' => 'Verbose (debug) logging', 'help' => 'Writes detailed integration logs. Error details are never shown to visitors.'],
    'system.integration_log_retention_days' => ['group' => 'system', 'type' => 'int', 'default' => 30, 'label' => 'Keep integration logs for … days', 'rules' => 'required|int|min:1|max:365'],
    'system.login_history_retention_days' => ['group' => 'system', 'type' => 'int', 'default' => 180, 'label' => 'Keep login history for … days', 'rules' => 'required|int|min:7|max:3650'],
    'system.tickets_enabled' => ['group' => 'system', 'type' => 'bool', 'default' => true, 'label' => 'Enable support tickets'],
];
