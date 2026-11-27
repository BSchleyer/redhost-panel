# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/).

## [2.0.0-dev] – unreleased

Complete modernization of the RED-Host panel as **Nebrix**. The legacy PHP 7 application (only present in the git history) was audited and replaced; see [AUDIT.md](AUDIT.md) for findings and [UPGRADE_GUIDE.md](UPGRADE_GUIDE.md) for the migration.

### Added
- PHP 8.3/8.4 code base with Composer, PSR-4 autoloading, strict types, dependency injection container, HTTP kernel, router and middleware pipeline.
- MariaDB/MySQL schema with forward-only migrations (`nx_` tables next to legacy tables) and CLI (`bin/nebrix`).
- Authentication: Argon2id, password policy, e-mail verification, password reset, TOTP two-factor authentication with recovery codes, login history, account lockout and rate limiting, database sessions with hashed IDs.
- Role-based access control with Super Administrator, Administrator, Support, Finance, Customer and custom staff roles; audit log.
- Admin panel: dashboard, general/authentication/billing/branding/e-mail/system settings, logo and favicon upload, customers and customer groups, products for VPS/KVM, LXC, dedicated servers, webhosting, domains, Minecraft/game servers, TeamSpeak 3 and Nextcloud, orders, invoices, transactions and refunds, services, support tickets, integrations, e-mail templates, staff, roles, login history, background jobs.
- Customer panel: dashboard, store and ordering, services with live status and power actions, access data, orders, invoices and payments, credit top-up, support tickets, profile, security settings (password, 2FA, sessions), English and German.
- Integrations with encrypted credentials, SSRF protection and health checks: Proxmox VE (API tokens, KVM clone with cloud-init, LXC, multi-cluster), Pterodactyl, Nextcloud, TeamSpeak 3 ServerQuery.
- Payments: Stripe Checkout (official SDK) and PayPal Orders v2 with verified webhooks, account credit, manual payments, refunds and dispute handling, immutable payment event log, idempotent processing.
- Database job queue with retries/back-off and a scheduler for renewals, overdue suspension, optional termination, stale order cancellation, health checks and data retention.
- `legacy:import` for RED-Host customer accounts and balances.
- Docker image (PHP 8.4 + Apache, non-root), docker-compose with worker and scheduler, GitHub Actions CI (PHP 8.3/8.4, PHPStan, PHPUnit, composer audit).
- Test suite (unit, integration, feature) with mocked integrations and payment providers.
- Documentation set in `DOCUMENTATION/`.

### Added (infrastructure features)
- Dedicated servers through the **Hetzner Robot** API: assignment from a server pool or ordering (opt-in, test mode by default), installation, firewall-based suspension, rescue system, resets, reverse DNS, reinstall, wipe queue on termination, optional cancellation.
- **Proxmox**: IP pools with static IPv4 addressing (admin UI), reinstall with selectable templates, root password reset (guest agent / cloud-init), graphical **noVNC console** through the new `console:proxy` WebSocket relay.
- **Plesk** webhosting (customers, subscriptions, suspension, single sign-on, password reset) and **INWX** domain registrations and transfers (prechecks before payment, registrant contacts, name servers, auth codes, renewal modes) behind a registrar interface.
- Service actions with input fields and confirmation, staff remote actions, reinstall flow through the provisioning queue.
- `legacy:import --only=services,tickets,history` for RED-Host services (Proxmox, TeamSpeak, Pterodactyl, Plesk), tickets and payment history.
- Optional CAPTCHA (hCaptcha, Cloudflare Turnstile) on registration and password reset; `db:triggers` for append-only tables.

### Added (REST API)
- Token-authenticated REST API under `/api/v1` with an OpenAPI 3.1 document (`/api/v1/openapi.json`): account, products, order placement, services (status, actions, access data), invoices and payment start, support tickets; admin endpoints for customers, products, orders, invoices, service lifecycle and ticket replies.
- Personal access tokens (Account → API tokens): scopes, expiry, IP allowlist, shown once, stored hashed; admin overview and revocation (permission `api_tokens.manage`); settings to enable the API, allow customer tokens and set the rate limit.
- Stateless request handling for API routes (no session/CSRF, JSON errors), per-user rate limits, `Idempotency-Key` support for `POST` requests.
- Security review of the API; all findings fixed: single sign-on needs `services:credentials` and is never stored for replays, tokens are revoked on password change/reset, "sign out everywhere" and 2FA reset and their creation is e-mailed, scope checks before idempotent replays, write scopes require the view permission, suspension e-mails by default, internal suspension notes hidden from customers, customer tokens blocked while customer logins are disabled, rate limit per user plus a limit for live data, robust idempotency hashing and abandoned keys, anonymised token rows on account closure, super administrators' tokens protected. List-valued input for single-value fields is now a validation error instead of a server error (web and API).

### Added (billing, contracts and backups)
- **Automatic renewal from credit**: renewal invoices are paid from account credit on the due date (setting + per-customer opt-out); configurable **payment reminders** before/after the due date.
- **E-mails in the recipient's language**: every template in English and German, editor with language switch; customers receive their account language.
- **Cancellation button (§ 312k BGB) and withdrawal function** (EU Directive 2023/2673, since 19 June 2026): public forms linked on every page, immediate receipt e-mail, automatic end of the service at the end of the paid term for signed-in customers, review queue for guests/extraordinary cancellations/withdrawals (Admin → Cancellations), take-back by the customer, API endpoints.
- **EU VAT**: tax decision per invoice (standard, reverse charge with VIES-verified VAT ID, OSS rates per country, small business switch for § 19 UStG, optional exemption outside the EU), VAT ID check on profile changes and daily re-check, invoice tax notes.
- **Invoice documents**: PDF invoices (dompdf), XRechnung 3.0 (UN/CEFACT CII) for business customers, attachments in order/renewal e-mails, unchangeable archive with SHA-256 checks, cancellation and correction documents for cancelled invoices and refunds (prefix `RK-`), downloads for customers, staff and the API.
- **Customer backups**: Proxmox (vzdump to a backup storage, restore through the provisioning queue, deletion, cleanup on termination) and Pterodactyl (create, restore, download, delete) with per-product limits and strict ownership checks.

### Fixed (system test of the Docker installation)
- Docker build: the composer stage failed on the missing `pdo_mysql` extension of the composer image; it is now ignored there (the runtime image installs it).
- New `.dockerignore`: `.env`, `.git`, local `vendor/`, tests, logs and runtime data are no longer copied into the image (previously a `.env` next to the Dockerfile would have ended up in the image).
- docker-compose: worker and scheduler no longer report "unhealthy" (the image's Apache health check does not apply to them); the console service checks its own port.
- German interface completed (over 1,000 texts: customer area, administration, settings, product fields, permissions, help texts) with a test that keeps every interface text translated; visitors get their browser language; tax rates and discounts are formatted per language ("19 %", "7,5 %"); amounts no longer wrap in tables.
- Order form: the tax line shows the rate that is actually charged (0 % for reverse charge) instead of the product's rate.
- Invoice lines, renewal descriptions, refund lines and e-mail dates are written in the customer's language and date format.

### Security (billing, contracts and backups)
- Fourth independent review; all findings fixed with regression tests: rejected or replaced cancellations no longer terminate the service (the end date is removed, only scheduled declarations are executed), customers can only take back their own automatic cancellations, staff cannot set past end dates, already cancelled services are not offered again; open invoices of cancelled or ended services are not paid automatically from credit or reminded; reverse charge requires a company name matching the VIES holder name, and a VIES outage keeps an earlier confirmation; per-network and per-recipient limits on the public cancellation forms; distinct number prefix for correction documents; backup listing failures no longer hide power actions; voided unpaid invoices no longer promise a refund; invoice documents are generated after the transaction by the mail worker; existing customers keep manual payment until they opt in.

### Security
- Removed committed secrets from the working tree (they remain in the history and must be rotated by the owner), insecure Docker defaults and phpMyAdmin.
- Fixed the legacy vulnerabilities documented in AUDIT.md (session token design, impersonation, SSH command execution, unverified PayPal IPN, double-spend race, CSRF, XSS, IDOR, open redirects, SSRF, unprotected cron endpoints, plaintext third-party credentials).
- Independent security review of the new code; all reported findings were fixed: TeamSpeak port reservation and safe adoption, revoked sessions cannot be resurrected, Proxmox guest ownership marker, staff role-escalation guard, atomic rate limiting, refund/chargeback reversal, refund idempotency keys, atomic TOTP/recovery code use, password confirmation for enabling 2FA, IPv4-embedding IPv6 ranges blocked, free renewal settlement, raw PayPal webhook verification.
- Second independent review of the infrastructure features; all findings fixed: domains cannot be taken over by another customer (registrant ownership, duplicate checks) and transfers activate only after completion (with a deadline); one Plesk subscription per domain; Hetzner ownership checked live before every action, rescue system deactivated before installations, full firewall backup, safe pool prefixes, test-mode orders replaced by real orders; console proxy with backpressure, per-IP/per-user/handshake limits, loopback default and memory limit; stricter legacy ownership (Proxmox host name, Pterodactyl identifier); atomic reinstall, provisioning retry after completed jobs, cloud-init fallback only with a cloud-init drive, IPv6 `Host` header for console upstreams.

### Removed
- Legacy RED-Host code paths: Venocix, Plesk and paysafecard/Boltlayer integrations, the PayPal IPN handler, HTTP cron endpoints, support-PIN login, the Discord bot API.
- Archived/unused dependencies (`paypal/rest-api-sdk-php`, duplicate Proxmox and TeamSpeak libraries).

### Known limitations
- Integrations and payment providers were tested against stateful fakes only; live/sandbox verification is pending. This includes Proxmox/Pterodactyl backups and the EU VIES service.
- XRechnung files are validated against the CII XSD in the tests; the official EN 16931/XRechnung Schematron rules (KoSIT validator) have not been run yet.
- Built-in EU VAT rates reflect the state of November 2026 and must be kept current (`tax.rate_overrides`). Tax and legal texts are technical defaults, not legal advice.
- LXC root passwords can only be changed by reinstalling (no Proxmox API for it); IPv6 is assigned by SLAAC only.
- Hetzner: disks of terminated pool servers are not wiped automatically; server market (auction) orders are not supported.
