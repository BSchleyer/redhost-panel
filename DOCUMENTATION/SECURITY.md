# Security

This document describes the protections implemented in Nebrix and the risks that remain. Findings from the original code base are listed in [AUDIT.md](AUDIT.md); the identifiers (C-01, H-01, …) refer to that document.

## Reporting

Report vulnerabilities privately to the repository owner. Do not open public issues for security problems.

## Implemented protections

### Secrets and configuration
* No secrets in the repository: `.env` is git-ignored; `.env.example` and `docker-compose.yml` contain placeholders only (C-01…C-03).
* Business secrets (SMTP password, Stripe/PayPal keys, integration credentials, 2FA secrets, generated service passwords) are encrypted at rest with **XChaCha20-Poly1305** (libsodium) using `APP_KEY`. Each ciphertext is bound to its context (column/row), so it cannot be moved to another field. Old keys can be listed in `APP_PREVIOUS_KEYS` for rotation.
* Secrets are write-only in the admin UI: forms show whether a value is stored, never the value. The audit log records which setting keys changed, never values.
* Logs pass through a redaction processor that masks keys such as `password`, `secret`, `token`, `authorization`, `api_key`.

### Database
* PDO with native prepared statements (`ATTR_EMULATE_PREPARES=false`), type-aware binding, `utf8mb4`, strict SQL mode.
* Table and column names used by helper methods are validated against `^[A-Za-z_][A-Za-z0-9_]{0,63}$`; update/delete helpers refuse empty WHERE clauses.
* Money movements (payments, refunds, credit) run in transactions with row locks (`SELECT … FOR UPDATE`), fixing the legacy double-spend race (C-08).

### Authentication
* **Argon2id** password hashing (bcrypt fallback only if PHP lacks Argon2). Legacy bcrypt hashes are upgraded on the next login (M-01).
* Password policy: 10–256 characters, deny-list of common passwords, may not contain the e-mail/username/name, minimum character variety.
* Generic login errors and equal timing for unknown accounts (dummy hash verification).
* Throttling: per account (configurable attempts/lockout) and per IP (30 per 15 minutes); also for registration attempts, password reset, 2FA, password confirmation/change, e-mail resends and customer actions (H-06). Every attempt is counted atomically *before* it is evaluated, so parallel requests cannot exceed a limit; signing in to one's own account does not reset the IP counter.
* **Sessions** are server-side in `nx_sessions`; the cookie holds a 256-bit random ID, the database stores only its SHA-256 hash (C-05). Cookies are `HttpOnly`, `SameSite=Lax`, `Secure` and `__Host-` prefixed on HTTPS. Idle timeout (default 60 min) and absolute lifetime (default 12 h). Existing sessions are only updated, never re-inserted, so a request that is still running cannot bring back a session revoked by logout, password change or "sign out other sessions".
* Session ID regeneration and CSRF token rotation on login, 2FA completion and password change (fixation).
* Password changes and resets terminate all other sessions and notify the user by e-mail.
* Password reset and e-mail verification tokens: 256-bit, stored as SHA-256, expiring (60 min / 48 h), single use; the reset request responds identically for unknown addresses (H-05, M-06).
* Unverified (pending) accounts cannot sign in; suspended or closed accounts are signed out on their next request.
* **Two-factor authentication** (TOTP, RFC 6238): secret encrypted, codes accepted once (last time slice stored with a conditional update), 10 single-use recovery codes stored as hashes and consumed under a row lock, QR code rendered locally. Enabling, disabling and regenerating codes require the current password. Staff 2FA can be enforced in the settings.

### Authorization
* RBAC with roles Super Administrator, Administrator, Support, Finance, Customer and custom staff roles. Permissions are defined in `Nebrix\Authorization\Permission` and assigned in Admin → Roles.
* Every admin route requires `admin.access` plus a route permission; actions that change data re-check the specific permission in the controller. Templates hide elements via `can()` only as a convenience.
* Privilege-escalation guards: only super administrators manage super administrators and roles; staff managers can only assign roles whose permissions they hold themselves and cannot manage more privileged staff; nobody changes their own role or status; role changes terminate the affected user's sessions.
* **IDOR protection**: every customer-facing lookup of services, invoices, orders and tickets is scoped to the signed-in user in SQL (`findForUser`) (H-04). Customers can only reference their own services in tickets.
* Customer groups can disable ordering and self-service actions; checked server-side.
* Impersonation ("log in as customer") is intentionally not implemented (C-04).

### Web security
* **XSS**: Twig auto-escaping for all templates; no `|raw` on user data; messages and notes are rendered as escaped text (H-02/H-03).
* **CSP**: `default-src 'self'; script-src 'self'; style-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' + payment providers`. No inline scripts or styles; brand colours are served as `/branding.css`.
* **CSRF**: synchronizer token (256-bit) checked on every non-GET request with `hash_equals`; Origin header must match `APP_URL` when present; only the two payment webhooks are exempt because they are signature-verified (H-01).
* Additional headers: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy`, `Cache-Control: no-store`, HSTS on HTTPS.
* **Open redirects**: redirect targets from the session or headers are reduced to same-origin paths (`RedirectGuard`) (M-03). Response headers reject CR/LF.
* **Client IP**: `X-Forwarded-*` headers are only honoured from addresses listed in `TRUSTED_PROXIES` (H-10).
* **Input validation**: central `Validator` (types, lengths, enumerations, formats); only declared fields are returned (mass assignment). Product/integration configuration values that end up in remote API calls are restricted by regular expressions.
* **File uploads** (logo/favicon): content-based type detection (finfo + image header), PNG/JPEG/WebP/ICO only (no SVG), max 1 MB and 4096 px, random file names, stored under `public/uploads` where the Apache configuration disables PHP and denies script/HTML/SVG files.
* **Errors**: generic error pages with a reference ID; details only in the log. Debug output requires both `APP_ENV=local` and `APP_DEBUG=true` (H-09). PHP warnings become exceptions (nothing fails silently); deprecations are logged.
* **Path traversal**: no user-controlled file paths; uploaded asset deletion accepts only the generated file name pattern.

### Integrations (SSRF and remote safety)
* Integration URLs/hosts are validated when saved and again before every request: http(s) only, HTTPS required unless `INTEGRATIONS_ALLOW_HTTP=true`, no embedded credentials, cloud-metadata/link-local/multicast/unspecified ranges and IPv6 forms that embed IPv4 addresses (mapped, compatible, NAT64, 6to4, Teredo) always blocked, loopback blocked unless `INTEGRATIONS_ALLOW_LOOPBACK=true`. Private (RFC 1918) networks are allowed on purpose for LAN integrations. The resolved IP is pinned with `CURLOPT_RESOLVE` to prevent DNS rebinding; redirects are disabled; timeouts are enforced (M-04).
* Proxmox is accessed only through its API with scoped API tokens; there is no SSH or shell command execution anywhere in the application (C-06).
* Proxmox path segments (node, VMID, UPID, disk) and Pterodactyl/Nextcloud identifiers are validated before being placed into URLs.
* Remote resources are only adopted or deleted when they provably belong to the service: Proxmox guests carry the marker `nebrix-service-<id>` in their description, Pterodactyl objects an external ID, TeamSpeak voice ports are reserved per service in the database and a server on a reserved port is adopted only with the expected name.
* TeamSpeak ServerQuery commands are built from validated command/parameter names; all values are escaped per the ServerQuery specification, so names cannot inject commands.
* Credentials for customers' services (root passwords, panel logins, privilege keys) are encrypted and shown only on an explicit request; each view is written to the audit log.
* The admin panel never executes server commands or PHP code; there is no update or shell feature.

### Payments
* Payment success is never derived from a redirect: the return URL triggers a server-side lookup (Stripe session retrieval, PayPal capture) (C-07).
* Webhooks are verified: Stripe with `Webhook::constructEvent` (HMAC-SHA256, 5-minute tolerance), PayPal with the `verify-webhook-signature` API and the configured webhook ID.
* Webhook events are deduplicated by provider event ID; payments by `(gateway, type, reference)` with a unique index.
* Amount and currency must equal the invoice; mismatches are stored as failed transactions and flagged. The provider order/session is bound to the invoice (`custom_id`/metadata) and checked on return.
* Refunds cannot exceed the remaining paid amount, are serialised per payment and carry a one-time idempotency key (Stripe `Idempotency-Key`, PayPal `PayPal-Request-Id`), so a double submission refunds once; refunds issued in the provider dashboards are recorded once via webhooks.
* Refunds and chargebacks reverse what the payment bought: refunded/disputed credit top-ups are removed from the balance (staff cannot refund already spent credit), fully refunded or disputed orders/renewals suspend the services.
* All payment steps are appended to `nx_payment_events` (no update/delete code path). No card data is processed or stored.
* Provisioning runs after the payment transaction in idempotent jobs; payment success does not depend on provisioning.

### Background processing
* No HTTP-triggered cron endpoints; worker and scheduler run from the CLI (H-07).
* Jobs are reserved atomically (`FOR UPDATE SKIP LOCKED`), retried with exponential backoff, and visible/retryable in the admin panel (M-09).
* Queued e-mails are encrypted (they may contain single-use links) and scrubbed after delivery.

### Audit trail
* `nx_audit_log` records security-relevant actions (staff logins, settings changes, role/permission changes, staff and customer management, credit adjustments, refunds, integration changes, service actions, credential views) with actor, target, IP and time. There is no code path to modify or delete entries.

## Security review

After implementation an independent review of the new code base was performed (read-only, all files). It reported no critical issues; one high, five medium and five low findings were verified and fixed with regression tests (see CHANGELOG.md, "Security"). Areas confirmed as correct included CSRF enforcement, parameterised SQL, output escaping/CSP, customer data scoping, redirect handling, webhook verification, payment binding, admin route permissions, upload handling and secret storage.

## Remaining risks and recommendations

| Risk | Recommendation |
|---|---|
| Secrets committed to the repository history before this modernization (AUDIT C-01/C-02) | Rotate every listed credential. Optionally rewrite history with `git filter-repo` (owner decision). |
| `APP_KEY` compromise exposes all stored secrets | Keep `.env` readable only by the web/worker user, back up the key separately, rotate with `APP_PREVIOUS_KEYS`. |
| TeamSpeak ServerQuery over raw TCP is unencrypted | Allow the query port only from the panel host and use a private network/VPN. SSH-based ServerQuery is a possible extension. |
| Admin-edited e-mail templates may contain arbitrary HTML | Restricted to the `mail_templates.manage` permission; preview is sandboxed. |
| `verify_tls=false` on an integration allows MITM | Use valid certificates (e.g. ACME on Proxmox); disable verification only in labs. |
| Payment immutability is enforced in application code | For defence in depth add database triggers that reject `UPDATE`/`DELETE` on `nx_payment_events` and `nx_audit_log` if your database user may create triggers (see DATABASE.md). |
| Rate limits and sessions live in the database | Sufficient for typical loads; for very large deployments move them to Redis behind the same interfaces. |
| No WAF / bot protection on registration | Put the panel behind a reverse proxy with rate limiting; a CAPTCHA (hCaptcha/Turnstile) can be added to the registration form. |
| Integrations were tested against fakes only | Run the connection tests in the admin panel and test provisioning in a staging environment before going live (see INTEGRATIONS.md). |
