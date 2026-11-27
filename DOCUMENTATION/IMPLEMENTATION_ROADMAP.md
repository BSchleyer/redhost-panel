# Nebrix Panel – Implementation Roadmap

This file is the single source of truth for modernization progress. It is updated at the end of every phase so that work can be resumed after an interruption.

* Branch: `claude/determined-darwin-pin7xv`
* Target runtime: PHP 8.4 (also tested on 8.3), MariaDB 10.6+/11.x or MySQL 8.0+
* Test environment used: `php:8.4-cli` and `mariadb:11.4` containers, host PHP 8.3

## Status legend

`[x]` done and tested · `[~]` in progress / partially done · `[ ]` not started

## Phase overview

| Phase | Scope | Status |
|---|---|---|
| A | Codebase audit, architecture documentation | [x] |
| B | PHP 8.4 compatibility, Composer modernization | [x] |
| C | Security remediation (core primitives) | [x] |
| D | Database & core architecture (migrations, container, HTTP kernel) | [x] |
| E | Authentication, 2FA, password reset, RBAC | [x] |
| F | Administration panel | [x] |
| G | Integration architecture + Proxmox VE | [x] |
| H | Pterodactyl, Nextcloud, TeamSpeak 3 | [x] |
| I | PayPal & Stripe | [x] |
| J | Orders & automated provisioning | [x] |
| K | Customer dashboard | [x] |
| L | Tests, documentation, final security review | [x] |

## Detailed plan

### Phase A – Audit
- [x] Inspect HEAD and full git history (legacy app at `26ccb14`)
- [x] `DOCUMENTATION/AUDIT.md`
- [x] This roadmap

### Phase B – PHP 8.4 & dependencies
- [x] New `composer.json` (PSR-4 `Nebrix\`, PHP `~8.3.0 || ~8.4.0`, stable deps, dev tools), commit `composer.lock`
- [x] Remove unused/abandoned packages (see AUDIT §6)
- [x] Docker image on `php:8.4-apache`, non-root, hardened Apache vhost
- [x] Remove broken skeleton files (`software/`, `debug/`, `system.php`) after replacement

### Phase C/D – Core
- [x] Config/env loader, service container, error handler (no debug output in production)
- [x] PDO connection (utf8mb4, strict mode, exceptions, no emulated prepares), transactions
- [x] Migration runner + initial schema (`nx_` prefix, FKs, indexes)
- [x] Request/Response, router, middleware pipeline
- [x] Security headers + CSP, CSRF, session (DB, hashed IDs), rate limiter, encrypter, validator, URL & redirect guards
- [x] Twig view layer with auto-escaping, layout, CSS/JS assets (no inline JS)
- [x] CLI `bin/nebrix`

### Phase E – Auth & RBAC
- [x] Register, e-mail verification, login, logout, throttling, login history
- [x] Password reset (hashed, expiring, single-use tokens)
- [x] TOTP 2FA with recovery codes
- [x] Roles (Super Administrator, Administrator, Support, Finance, Customer), configurable permissions, gate + middleware
- [x] Audit log

### Phase F – Admin panel
- [x] Dashboard KPIs, health, notifications, job status
- [x] General / branding / e-mail (SMTP + test) / system settings
- [x] E-mail templates editor
- [x] Customers, customer groups
- [x] Products (all categories, billing cycles, tax, stock, provisioning)
- [x] Staff, roles & permissions, audit log, login history

### Phase G/H – Integrations
- [x] Integration registry, encrypted credentials, health checks, logs, enable/disable
- [x] Proxmox VE (multi-cluster, nodes, VM + LXC lifecycle, tasks)
- [x] Pterodactyl (Application API: nodes, nests, eggs, locations, users, servers)
- [x] Nextcloud (OCS provisioning: users, quota, enable/disable)
- [x] TeamSpeak 3 (ServerQuery: virtual servers, slots, ports, tokens)

### Phase I – Payments
- [x] Gateway abstraction, settings (enable, sandbox/live, credentials)
- [x] PayPal Orders v2: create, approve, capture, refund, webhook verification
- [x] Stripe Checkout: session, webhooks (signature), refunds, status sync
- [x] Idempotent payment recording, amount/currency validation, immutable payment events
- [x] Account credit (legacy balance)

### Phase J – Orders & provisioning
- [x] Cart/order/invoice flow
- [x] DB job queue + worker + admin retry
- [x] Idempotent provisioners per category
- [x] Renewals, expiry, suspension, optional termination

### Phase K – Customer panel
- [x] Dashboard, profile, security (password, 2FA, sessions)
- [x] Services list + per-type management
- [x] Orders, invoices, transactions, renewals, store
- [x] Support tickets

### Phase L – QA & docs
- [x] PHPUnit (unit, integration with MariaDB, HTTP feature tests) – 196 tests
- [x] PHPStan level 6, `php -l` on 8.3/8.4, `composer validate`, `composer audit`
- [x] GitHub Actions CI (PHP 8.3/8.4 matrix, MariaDB service)
- [x] Legacy importer (`legacy:import`) for RED-Host accounts and balances
- [x] All documentation files, README
- [x] Final security review (independent reviewer) – all findings fixed with regression tests
- [x] Docker runtime image check (Apache config, non-root, exposure of `.env`/code)

## Session log

### Session 1 (2026-11-27)
- Completed Phase A (audit) – commit `37d2d02`.
- Completed Phases B–D (core, schema, security primitives) – commit `cf9911f`.
- Completed Phase E (auth, 2FA, reset, verification, RBAC gate, job queue, mailer) – commit `6305abb`. 81 tests green on PHP 8.4.28 + MariaDB 11.4; PHPStan level 6 clean.
- Environment notes: Composer dist downloads from GitHub are blocked in this sandbox; dependencies were installed with `--prefer-source` (PHPStan from a locally built archive). Tests run in Docker (`php:8.4-cli` + `pdo_mysql`, `mariadb:11.4`).
- Completed Phase F (admin dashboard, settings pages, branding uploads, SMTP test, mail templates, customers, groups, products with per-category provisioning schema, staff with escalation guards, roles/permissions matrix, audit log, login history, jobs). 114 tests green.
- Admin pages for orders, invoices, transactions, services, integrations and tickets are linked in the navigation and are implemented in phases G–K.
- Completed Phases G–H: integration registry, encrypted credentials, SSRF-guarded API client with DNS pinning, health checks, per-integration logs, admin UI (CRUD, test, logs, remote resources), Proxmox/Pterodactyl/Nextcloud/TeamSpeak clients and idempotent provisioners. All tested against fake APIs/ServerQuery (no live systems available). 144 tests green.
- Fixed a flaky test (EncrypterTest tamper case, ~1/64 false pass).
- Completed Phase I: invoice service (gapless numbers, billing snapshots, renewal/credit invoices, markPaid side effects via idempotent jobs), PaymentService (idempotency, amount/currency validation, append-only events), Stripe Checkout + verified webhooks + refunds (official SDK), PayPal Orders v2 + capture + verified webhooks + refunds, account credit gateway, customer invoice payment flow, admin transactions/refunds/webhook events, provider connection tests. 158 tests green.
- Completed Phase J: order placement (server-side pricing, group discounts, stock reservation, free orders), fulfilment/provisioning/suspend/unsuspend/terminate jobs, worker with release/backoff/failure hooks, scheduler (renewal invoices, overdue suspension, opt-in termination, stale order cancellation, health checks, cleanup), admin orders/invoices/services incl. manual payments and manual activation, customer store/services/orders. Fixed: provisioning retry now re-evaluates the node while nothing exists remotely. 166 tests green.
- Completed Phase K: customer dashboard (services, renewals, open invoices, credit, tickets, admin notice), support tickets for customers and staff (assignment, priority, e-mail on reply, rate limits, isolation), per-user locale with German catalog. 172 tests green.
- Completed Phase L:
  - `legacy:import` (idempotent, dry run, staff imported suspended, balances via ledger) and GitHub Actions CI.
  - Independent security review of the whole code base: 0 critical, 1 high, 5 medium, 5 low. All fixed with tests – TeamSpeak port reservation/safe adoption (high); session resurrection, Proxmox ownership marker, staff role-escalation guard, atomic rate limiting, refund/chargeback reversal (medium); registration probing limit, atomic TOTP/recovery codes, password for enabling 2FA, IPv4-embedding IPv6 ranges, free renewals (low). Additionally: refund idempotency keys + per-payment lock, raw PayPal webhook verification, Proxmox termination ownership check, unprivileged-only LXC feature flags.
  - Docker runtime image built and probed: Apache config valid, runs as `www-data`, `/health` 200, `.env` 403, `composer.json`/`config/` not served, CSP present. The composer build stage itself could not be verified here (registry downloads restricted).
  - Documentation: ARCHITECTURE, SECURITY, INSTALLATION, CONFIGURATION, DATABASE, INTEGRATIONS, PAYMENTS, ADMIN_GUIDE, API, UPGRADE_GUIDE, CHANGELOG, README; AUDIT corrected (Proxmox password/console flow is not implemented; password check is a deny list, not a breach lookup).
  - Final state: 196 tests (1068 assertions) green on PHP 8.4.28 and PHP 8.3.6 with MariaDB 11.4, PHPStan level 6 clean, lint clean on 8.3/8.4, `composer validate --strict` and `composer audit` without findings.
- **Next step:** live verification in a staging environment (see "Remaining work").

### Session 2 (2026-11-28) – missing infrastructure features
- Foundation: service actions with validated input, staff remote actions, reinstall flow via the provisioning queue, integration-aware provisioning (manual products stay manual) – commit `9494a07`.
- Dedicated servers via **Hetzner Robot** (pool/order modes, install, firewall suspension, rescue, rDNS, reinstall, wipe queue) – `9494a07`.
- **Proxmox**: IP pools (admin UI, static IPv4), reinstall, root password reset, noVNC console with WebSocket proxy (`console:proxy`) – `40abe15`.
- **Plesk** webhosting and **INWX** domains (prechecks before payment, encrypted auth codes) – `58f96a1`.
- Legacy import of services, tickets and payment history; optional CAPTCHA; `db:triggers` – `6db9f8f`.
- Documentation updated – `7db4e7c`. 233 tests green on PHP 8.4.28 and 8.3.6 (MariaDB 11.4), PHPStan level 6 clean; UI of the new pages checked in Chromium (no CSP/console errors).
- Second independent security review of the session-2 features (1 high, 4 medium, 7 low, plus 6 unverified points); all findings and four of the unverified points fixed with regression tests (see SECURITY.md, "Security review"). 245 tests (1636 assertions) green on PHP 8.4.28 and 8.3.6, PHPStan level 6 clean.
- **Next step:** public REST API (scoped tokens), then live verification of all integrations on staging.

### Session 3 (2026-11-27) – REST API
- REST API `/api/v1` (customer and admin endpoints, OpenAPI 3.1 document), personal access tokens with scopes/expiry/IP allowlist, token UI for users and staff, stateless kernel path, rate limits, idempotency keys. Documentation in API.md, SECURITY.md, CONFIGURATION.md, ADMIN_GUIDE.md, DATABASE.md, ARCHITECTURE.md.
- Independent security review of the API (0 high, 2 medium, 9 low); all fixed with regression tests. Token pages checked in Chromium (no console/CSP errors) and a token created in the UI used against the running API. 271 tests (1980 assertions) green on PHP 8.4.28 and 8.3.6, PHPStan level 6 clean.
- **Next step:** live verification of all integrations and the payment providers on staging.

### Session 4 (2026-11-27) – billing, contracts, taxes, invoices, backups
- Automatic renewal payment from credit and payment reminders – `7c79d76`.
- E-mails in the recipient's language (EN/DE templates, editor with language switch) – `46d2503`.
- Cancellation button (§ 312k BGB) and EU withdrawal function, admin review queue, API endpoints – `3359217`.
- EU VAT: reverse charge with VIES verification, OSS, small-business switch, third countries – `631fef3`.
- PDF invoices (dompdf), XRechnung 3.0 CII, archive with SHA-256, cancellation/correction documents – `dfaf26e`. XML validated against the CII XSD; the official Schematron validation could not be run in this environment.
- Customer backups for Proxmox and Pterodactyl – `dbf04c3`.
- Fourth independent security review (1 high, 4 medium, 7 low; no IDOR/injection); all findings fixed with regression tests (see SECURITY.md). 304 tests (2365 assertions) green on PHP 8.4.28 and 8.3.6, PHPStan level 6 clean.
- **Next step:** tax advisor review of the tax settings and invoice texts, KoSIT validation of sample XRechnung files, live verification of integrations and payment providers on staging.

### Session 5 (2026-11-27) – system test of the Docker installation
- Installed with docker compose exactly as INSTALLATION.md describes (build, key, migrate, admin) plus a local SMTP catcher, and walked through the panel in Chromium: admin setup, SMTP test mail, registration with e-mail confirmation, German profile, credit, order, payment from credit, invoice PDF/XRechnung download, order and payment mails with attachments, manual activation, cancellation with receipt, take-back, guest withdrawal, staff review and rejection, API token and API calls, partial refund with correction document, scheduler run. Live check of the EU VIES interface (valid ID with holder name, invalid ID).
- Fixed: Docker build (composer platform check), secrets in the build context (`.dockerignore`), health checks of worker/scheduler/console, incomplete German interface (translation test added), guest language, order form tax rate, localized invoice lines and mail dates, table layout.
- Not covered (needs credentials/test systems): Stripe/PayPal sandbox, Proxmox, Hetzner Robot, Plesk, INWX OTE, Pterodactyl, Nextcloud, TeamSpeak; official KoSIT validation of XRechnung files.

## Remaining work
- Run the KoSIT validator (EN 16931 + XRechnung Schematron) on sample invoices of every tax category; optionally add ZUGFeRD (PDF/A-3 with embedded XML).
- Keep the built-in EU VAT rates current (or maintain `tax.rate_overrides`); have the tax logic and legal texts (cancellation/withdrawal pages, invoice notes) reviewed.
- Backups: scheduled automatic customer backups and backup quotas by size are not implemented.- REST API: possible extensions – webhooks for API clients, profile changes, staff write access beyond services and tickets (e.g. manual payments), OAuth for third-party apps.
- Verify every integration (Proxmox incl. console, Hetzner Robot in order test mode, Plesk, INWX OTE, Pterodactyl, Nextcloud, TeamSpeak) and both payment providers against staging/sandbox systems; only tests against fakes exist.
- Hetzner: automatic disk wipe of returned pool servers and server market (auction) orders are not implemented.
- Proxmox: IPv6 address management (SLAAC only); LXC root password changes only by reinstall (no API).
- Additional registrars or hosting panels can be added behind `Domain\Registrar` / `Provisioner`.

## Known issues / blockers
- Real credentials for PayPal, Stripe, Proxmox, Pterodactyl, Nextcloud and TeamSpeak are not available in the development environment. All integrations are tested against mocked HTTP/socket responses only; see `INTEGRATIONS.md` for what has and has not been verified against live systems.
- Secrets committed to git history (AUDIT C-01/C-02) must be rotated by the repository owner; history rewriting is not performed by this session.
