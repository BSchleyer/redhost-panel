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

### REST API
* Off by default (`api.enabled`). Stateless: no session is read or created and no cookie is set, so CSRF does not apply; a browser session never authenticates an API request.
* **Tokens**: 192-bit random secrets with the prefix `nbx_` (recognisable by secret scanners), stored only as SHA-256 hash, shown once; creation requires the account password, is rate-limited (10/hour) and e-mailed to the owner. Every token expires (max. 365 days), has explicit scopes and an optional IP allowlist; at most 20 active tokens per user. Owners and staff (`api_tokens.manage`; super administrators' tokens only by super administrators) can revoke tokens. Password change/reset, staff "sign out everywhere", 2FA reset and account closure revoke all tokens of the account; suspended accounts and – while customer logins are disabled – customers cannot use them; creation and revocation are audit-logged.
* **Authorization**: each route declares one scope (enforced by middleware; a test keeps the route table and the OpenAPI document in sync). Admin scopes can only be chosen for permissions the staff member has (write scopes need the view permission too, like the admin routes) and are re-checked against the role, `admin.access` and enforced staff 2FA on every request – also before an idempotent replay. Single sign-on actions need the `services:credentials` scope in addition to `services:manage`. Customer endpoints use the same user-scoped queries as the web interface (`findForUser`, `user_id` filters) – other customers' IDs answer 404.
* **Same rules as the web interface**: controllers call the existing services (orders, payments, service actions, tickets) with the same validation, prechecks and per-user rate limits; payments are only settled by verified provider confirmations; dangerous actions need an explicit confirmation (`"confirm": true`, `"confirm": "TERMINATE"`).
* **Output**: resources are built from explicit field lists – remote IDs, provisioning configuration, encrypted values, secret order options, password hashes and internal notes are never returned; access data only with the `services:credentials` scope (audit-logged).
* **Abuse limits**: rate limit per user across all tokens (default 120/min), live status/actions at most 30/min per user; failed authentications per IP answer 429 after 30 failures in 5 minutes without blocking valid tokens from that address; JSON bodies max. 1 MiB and depth 32; `Idempotency-Key` replays only for the same token and an identical request; one-time login URLs are never stored. Input that is a list where a single value is expected is a validation error (previously a server error).
* Errors are JSON with stable codes and never contain internal details (server errors carry a log reference only).

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
* Remote resources are only adopted or deleted when they provably belong to the service: Proxmox guests carry the marker `nebrix-service-<id>` in their description, Pterodactyl objects an external ID, TeamSpeak voice ports are reserved per service in the database and a server on a reserved port is adopted only with the expected name. Services imported from RED-Host (no marker) are only deleted while the remote object still matches the legacy record (Proxmox host name without a foreign marker, Pterodactyl identifier).
* TeamSpeak ServerQuery commands are built from validated command/parameter names; all values are escaped per the ServerQuery specification, so names cannot inject commands.
* Credentials for customers' services (root passwords, panel logins, privilege keys) are encrypted and shown only on an explicit request; each view is written to the audit log.
* The admin panel never executes server commands or PHP code; there is no update or shell feature.

### Server consoles
* The browser receives only a random 256-bit, single-use token valid for 60 seconds and the VNC password; Proxmox API credentials stay on the server (the upstream target is stored encrypted, the token as SHA-256 hash).
* The console proxy checks the WebSocket handshake, the `Origin` (must equal `APP_URL`), redeems the token atomically, re-checks that service and user are active, pins the upstream address, verifies TLS per integration and the upstream `Sec-WebSocket-Accept`, and limits header size, handshake time, concurrent connections and handshakes, connections per client IP, open consoles per user, session duration and buffers (with backpressure). It listens on 127.0.0.1 by default and runs with its own memory limit.
* The console page extends the CSP only by the console WebSocket origin; opening a console is rate-limited and audit-logged.

### Remote resources and orders
* Provisioner actions are whitelisted per service; action input is validated (select fields only accept offered values). Reinstalls are rate-limited (3 per hour and service) and start with an atomic status change (`active` → `provisioning`), so concurrent requests cannot start two reinstalls; a refused reinstall restores the status.
* Hetzner: servers are only taken from the explicitly named pool (prefixes overlapping the reserved names are rejected), reserved under a lock, and every action, installation step and reinstall checks the server name `nebrix-service-<id>` live (no cached result); ordering servers (costs) must be enabled explicitly and runs in test mode by default; an order is found by its comment instead of being placed twice. Terminated servers are not wiped automatically but moved to a wipe queue.
* Plesk: XML requests are built with escaped values; responses with DTDs are rejected (no XXE); subscriptions of other customers are never adopted and a domain can only have one active subscription per server. Single sign-on links are one-time Plesk sessions shown as a link (no open redirect, CSP `form-action` stays `'self'`).
* Domains: availability, registrant data and duplicates (a domain managed by another service) are checked before payment; a domain in the registrar account is only managed by the service that submitted it and whose registrant contact it carries, so customers cannot take over each other's domains; a service only becomes active when the registry reports the domain as `OK` (pending transfers time out after 10 days); transfer auth codes are stored encrypted and removed after the registry accepted them; termination never deletes a domain immediately.
* IP pools: an address is assigned to at most one service (row lock + unique key) and only released after the guest has been deleted.
* Backups: customers can only list, restore, download or delete backups of their own server – Proxmox backups must carry the note `nebrix-service-<id>`, lie on the product's backup storage, belong to the guest's VMID and be newer than the service (a reused VMID never exposes an earlier customer's backups); Pterodactyl backups are read from the service's own server. Backup volume IDs and UUIDs are validated against strict patterns and against the live list before every operation. The number of backups is enforced under a per-service lock; restores run through the queue with the same ownership checks and never touch a guest without the service's marker.

### Public forms
* Optional CAPTCHA (hCaptcha/Turnstile) on registration, password reset and the public cancellation/withdrawal forms (for visitors who are not signed in), verified server-side (fail closed); the provider's sources are added to the CSP of these pages only.
* Cancellation and withdrawal forms (§ 312k BGB / EU withdrawal function) are CSRF-protected and limited to 10 declarations per client network and hour (IPv4 address or IPv6 /64) and, for guests, 3 receipts per recipient address and day. A guest's declaration never changes a contract automatically – it is reviewed by staff, because the identity cannot be verified; only signed-in customers can schedule the end of their own services directly. The confirmation page and e-mail repeat only what was entered (no account or contract data is disclosed to guests).

### Invoices and taxes
* Invoice PDFs and XML are only served through user-scoped lookups (customer: own invoices; API: `invoices:read` and own invoices; admin: invoice permissions). File names are reduced to `[A-Za-z0-9._-]` and sent as `attachment`.
* PDFs are rendered by dompdf from an isolated Twig environment with auto-escaping, remote resources, PHP and JavaScript disabled and the file access restricted (`chroot`) to the PDF template directory; customer-entered values cannot load files or URLs. XML is built with DOM (escaped values).
* Issued documents are archived once (under a database lock) and served from the archive only if their SHA-256 still matches; a missing or modified file is refused and logged as critical instead of being regenerated with today's data. Issued invoices are never edited – corrections create separate documents.
* Reverse charge is applied only for a VAT ID confirmed by VIES for the customer's own (EU) country and a company name that matches the holder name VIES reports; for a newly entered or invalid VAT ID, VAT is charged (fail-safe for the seller). A temporary VIES outage does not remove an earlier confirmation of an unchanged VAT ID. Tax decisions are stored on each invoice.
* Invoice documents are generated by the queue worker after the transaction that created the invoice (no rendering while number sequences or service rows are locked, no files of rolled-back invoices).

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

A second independent review covered the infrastructure features (Hetzner, Proxmox IP pools/reinstall/console, Plesk, INWX, legacy service import, CAPTCHA, triggers). It reported one high, four medium and seven low findings – domain transfers activated before completion and domains not bound to the submitting service (high); duplicate Plesk subscriptions, Hetzner reinstall without ownership check, console proxy resource exhaustion, too trusting legacy ownership checks (medium); non-atomic reinstall, provisioning retry after a completed job, test-mode orders blocking a service, pool prefixes overlapping the markers, cloud-init password fallback without a cloud-init drive, a template error and IPv6 `Host` headers (low). All were fixed with regression tests. Of the points it could not verify, empty Robot list responses (404), rescue/installation conflicts, the firewall output rules and the console token in access logs were addressed as well; the remaining ones are listed below.

A fourth independent review covered automatic payment, localized e-mails, cancellations, EU VAT, invoice documents and backups. It found no IDOR or injection issue; one high finding (a rejected scheduled cancellation still terminated the service), four medium findings (receipt e-mails to arbitrary addresses through the public forms, automatic credit payment of open invoices of cancelled services, reverse charge for a VAT ID of another company, loss of a verified VAT ID during a VIES outage) and seven low findings (number prefix collisions, backup listing failures hiding all actions, refund wording on voided invoices, out-of-sync cancellation states, past end dates, PDF rendering inside transactions, automatic payment switched on for existing customers) were fixed with regression tests. Remaining points are listed below.

A third independent review covered the REST API. It found no high-severity issue; its two medium findings (single sign-on through the API with only `services:manage`, and API tokens surviving password resets and session revocation) and nine low findings (idempotent replay before the scope check, write scopes without the view permission, silent suspensions, suspension notes visible to customers, customer tokens while customer logins are disabled, per-token instead of per-user rate limits, idempotency hash/abandoned keys, token data after account closure, revocation of super administrators' tokens) were fixed with regression tests, as was a server error on list-valued input.

## Remaining risks and recommendations

| Risk | Recommendation |
|---|---|
| Secrets committed to the repository history before this modernization (AUDIT C-01/C-02) | Rotate every listed credential. Optionally rewrite history with `git filter-repo` (owner decision). |
| `APP_KEY` compromise exposes all stored secrets | Keep `.env` readable only by the web/worker user, back up the key separately, rotate with `APP_PREVIOUS_KEYS`. |
| TeamSpeak ServerQuery over raw TCP is unencrypted | Allow the query port only from the panel host and use a private network/VPN. SSH-based ServerQuery is a possible extension. |
| Admin-edited e-mail templates may contain arbitrary HTML | Restricted to the `mail_templates.manage` permission; preview is sandboxed. |
| `verify_tls=false` on an integration allows MITM | Use valid certificates (e.g. ACME on Proxmox); disable verification only in labs. |
| Payment immutability is enforced in application code | For defence in depth run `php bin/nebrix db:triggers --install` (rejects `UPDATE`/`DELETE` on payment events, audit log and credit ledger; needs the `TRIGGER` privilege). |
| The console proxy is a long-running PHP process | Run it behind the TLS reverse proxy only (default bind 127.0.0.1), set `TRUSTED_PROXIES` so per-IP limits see client addresses, and disable access logging for the console path (the single-use token is part of the URL). It connects to Proxmox synchronously, so an unreachable Proxmox host delays other console handshakes by up to 3 seconds. |
| New integrations (Hetzner, Plesk, INWX) were tested against fakes only | Use Hetzner order test mode, INWX OTE and a staging Plesk server before going live. |
| Ownership markers (`nebrix-service-<id>`, `nebrix-wipe-<n>`, `nebrix-user-<id>`) are not unique per installation | Never connect a staging and a production installation to the same Proxmox cluster, Robot account, Plesk server or INWX account (or at least use different Hetzner pool prefixes). |
| Hetzner Robot request limits | Server status is cached for 5 minutes per service; very frequent page views of many dedicated servers can still use up the hourly Robot request budget. |
| Rate limits and sessions live in the database | Sufficient for typical loads; for very large deployments move them to Redis behind the same interfaces. |
| Bots on public forms | Enable the CAPTCHA in Admin → Settings → Authentication and rate-limit at the reverse proxy. |
| Integrations were tested against fakes only | Run the connection tests in the admin panel and test provisioning in a staging environment before going live (see INTEGRATIONS.md). This includes Proxmox/Pterodactyl backups and the EU VIES service. |
| Public cancellation forms send a receipt to the e-mail address entered | Required by law (immediate confirmation, including the declaration's content). Abuse is limited per client network and per recipient; enable the CAPTCHA and rate-limit at the reverse proxy if the forms are misused. |
| OSS rates and the third-country exemption rely on the country the customer enters | Keep evidence of the customer's location (e.g. payment country) as required for OSS, and check unusual cases. VIES does not disclose holder names for some member states. |
| Tax and invoice rules are a technical implementation | Have the tax settings, invoice texts and a sample XRechnung (KoSIT validator) checked by a tax advisor before going live; keep the invoice archive on durable, backed-up storage. |
