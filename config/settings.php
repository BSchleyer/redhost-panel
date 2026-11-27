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

    // --------------------------------------------------------------- invoices
    'invoice.seller_street' => ['group' => 'invoice', 'type' => 'string', 'default' => '', 'label' => 'Street and number', 'rules' => 'nullable|string|max:150'],
    'invoice.seller_postcode' => ['group' => 'invoice', 'type' => 'string', 'default' => '', 'label' => 'Postcode', 'rules' => 'nullable|string|max:20'],
    'invoice.seller_city' => ['group' => 'invoice', 'type' => 'string', 'default' => '', 'label' => 'City', 'rules' => 'nullable|string|max:100'],
    'invoice.seller_vat_id' => ['group' => 'invoice', 'type' => 'string', 'default' => '', 'label' => 'VAT ID (USt-IdNr.)', 'rules' => 'nullable|string|max:20|regex:/^([A-Z]{2}[A-Z0-9]{2,12})?$/'],
    'invoice.seller_tax_number' => ['group' => 'invoice', 'type' => 'string', 'default' => '', 'label' => 'Tax number (Steuernummer)', 'help' => 'Needed when there is no VAT ID (e.g. small business).', 'rules' => 'nullable|string|max:30'],
    'invoice.contact_name' => ['group' => 'invoice', 'type' => 'string', 'default' => '', 'label' => 'Contact person for invoices', 'help' => 'Together with the contact e-mail and phone (General) required for e-invoices (XRechnung).', 'rules' => 'nullable|string|max:100'],
    'invoice.bank_account_holder' => ['group' => 'invoice', 'type' => 'string', 'default' => '', 'label' => 'Account holder', 'rules' => 'nullable|string|max:100'],
    'invoice.bank_iban' => ['group' => 'invoice', 'type' => 'string', 'default' => '', 'label' => 'IBAN (for bank transfers)', 'rules' => 'nullable|string|max:34|regex:/^([A-Z]{2}[0-9]{2}[A-Z0-9]{10,30})?$/'],
    'invoice.bank_bic' => ['group' => 'invoice', 'type' => 'string', 'default' => '', 'label' => 'BIC', 'rules' => 'nullable|string|max:11|regex:/^([A-Z0-9]{8}([A-Z0-9]{3})?)?$/'],
    'invoice.footer' => ['group' => 'invoice', 'type' => 'text', 'default' => '', 'label' => 'Invoice footer', 'help' => 'E.g. commercial register, managing directors, court of registration.'],
    'invoice.e_invoice_b2b' => ['group' => 'invoice', 'type' => 'bool', 'default' => true, 'label' => 'Attach e-invoices (XRechnung) for business customers', 'help' => 'Customers with a company name or VAT ID receive the XML file in addition to the PDF.'],

    // ------------------------------------------------------------------ taxes
    'tax.home_country' => ['group' => 'tax', 'type' => 'string', 'default' => 'DE', 'label' => 'Country of the business (ISO code)', 'rules' => 'required|country'],
    'tax.small_business' => ['group' => 'tax', 'type' => 'bool', 'default' => false, 'label' => 'Small business (§ 19 UStG): no VAT on invoices', 'help' => 'Invoices show 0 % VAT and the note below. Not the same as a sole proprietorship – ask your tax advisor.'],
    'tax.small_business_note' => ['group' => 'tax', 'type' => 'string', 'default' => 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.', 'label' => 'Note on invoices (small business)', 'rules' => 'nullable|string|max:255'],
    'tax.reverse_charge' => ['group' => 'tax', 'type' => 'bool', 'default' => true, 'label' => 'Reverse charge for EU business customers', 'help' => 'Customers from another EU country with a VAT ID verified through VIES are invoiced without VAT.'],
    'tax.reverse_charge_note' => ['group' => 'tax', 'type' => 'string', 'default' => 'Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge).', 'label' => 'Note on invoices (reverse charge)', 'rules' => 'nullable|string|max:255'],
    'tax.oss' => ['group' => 'tax', 'type' => 'bool', 'default' => false, 'label' => 'OSS: VAT rate of the customer\'s country for EU private customers', 'help' => 'Enable when your EU-wide B2C sales exceed the threshold (10,000 EUR) or you opted in to OSS.'],
    'tax.rate_overrides' => ['group' => 'tax', 'type' => 'text', 'default' => '', 'label' => 'Standard rate overrides (one per line, e.g. EE=2400 for 24 %)', 'help' => 'The built-in EU standard rates were current in November 2026; correct changed rates here (basis points).', 'rules' => 'nullable|string|max:2000|regex:/^([A-Za-z]{2}=\d{1,4}\s*)*$/'],
    'tax.non_eu_exempt' => ['group' => 'tax', 'type' => 'bool', 'default' => false, 'label' => 'No VAT for customers outside the EU', 'help' => 'Services to customers in third countries are usually not taxable in the EU; check registration duties abroad.'],
    'tax.non_eu_note' => ['group' => 'tax', 'type' => 'string', 'default' => 'Nicht im Inland steuerbare Leistung.', 'label' => 'Note on invoices (outside the EU)', 'rules' => 'nullable|string|max:255'],

    // ---------------------------------------------------------------- billing
    'billing.invoice_prefix' => ['group' => 'billing', 'type' => 'string', 'default' => 'NX-', 'label' => 'Invoice number prefix', 'rules' => 'nullable|regex:/^[A-Za-z0-9-]{0,10}$/'],
    'billing.credit_note_prefix' => ['group' => 'billing', 'type' => 'string', 'default' => 'RK-', 'label' => 'Number prefix for cancellation and correction documents', 'rules' => 'nullable|regex:/^[A-Za-z0-9-]{0,10}$/'],
    'billing.order_prefix' => ['group' => 'billing', 'type' => 'string', 'default' => 'ORD-', 'label' => 'Order number prefix', 'rules' => 'nullable|regex:/^[A-Za-z0-9-]{0,10}$/'],
    'billing.invoice_due_days' => ['group' => 'billing', 'type' => 'int', 'default' => 7, 'label' => 'Invoice payment term (days)', 'rules' => 'required|int|min:0|max:90'],
    'billing.renewal_notice_days' => ['group' => 'billing', 'type' => 'int', 'default' => 7, 'label' => 'Create renewal invoices … days before due date', 'rules' => 'required|int|min:1|max:60'],
    'billing.suspend_grace_days' => ['group' => 'billing', 'type' => 'int', 'default' => 1, 'label' => 'Suspend unpaid services … days after due date', 'rules' => 'required|int|min:0|max:60'],
    'billing.terminate_after_days' => ['group' => 'billing', 'type' => 'int', 'default' => 0, 'label' => 'Terminate suspended services after … days (0 = never automatically)', 'help' => 'Termination deletes the customer\'s server/account on the remote system. Leave at 0 to terminate manually.', 'rules' => 'required|int|min:0|max:365'],
    'billing.auto_pay_credit' => ['group' => 'billing', 'type' => 'bool', 'default' => true, 'label' => 'Pay renewal invoices automatically from account credit', 'help' => 'Paid on the due date. Customers can switch this off in their profile. Requires "Allow paying invoices with account credit".'],
    'billing.reminder_offsets' => ['group' => 'billing', 'type' => 'string', 'default' => '-3,1', 'label' => 'Payment reminders (days relative to the due date)', 'help' => 'Comma-separated, e.g. "-3,1" = 3 days before and 1 day after the due date. Empty = no reminders. Services are suspended after the grace period above.', 'rules' => 'nullable|string|max:30|regex:/^(-?\d{1,2}(,\s*-?\d{1,2}){0,4})?$/'],
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
    'payments.stripe.mode' => ['group' => 'payments_stripe', 'type' => 'select', 'default' => 'test', 'label' => 'Mode', 'options' => ['test' => 'Test mode', 'live' => 'Live']],
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

    // ---------------------------------------------------------------- REST API
    'api.enabled' => ['group' => 'api', 'type' => 'bool', 'default' => false, 'label' => 'Enable the REST API', 'help' => 'Token-authenticated JSON API under /api/v1 (documentation: /api/v1/openapi.json).'],
    'api.customer_tokens' => ['group' => 'api', 'type' => 'bool', 'default' => true, 'label' => 'Customers may create API tokens', 'help' => 'When disabled, only staff tokens work.'],
    'api.rate_limit' => ['group' => 'api', 'type' => 'int', 'default' => 120, 'label' => 'Requests per minute and user (all tokens together)', 'rules' => 'required|int|min:10|max:6000'],
];
