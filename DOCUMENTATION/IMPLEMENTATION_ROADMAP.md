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
| B | PHP 8.4 compatibility, Composer modernization | [ ] |
| C | Security remediation (core primitives) | [ ] |
| D | Database & core architecture (migrations, container, HTTP kernel) | [ ] |
| E | Authentication, 2FA, password reset, RBAC | [ ] |
| F | Administration panel | [ ] |
| G | Integration architecture + Proxmox VE | [ ] |
| H | Pterodactyl, Nextcloud, TeamSpeak 3 | [ ] |
| I | PayPal & Stripe | [ ] |
| J | Orders & automated provisioning | [ ] |
| K | Customer dashboard | [ ] |
| L | Tests, documentation, final security review | [ ] |

## Detailed plan

### Phase A – Audit
- [x] Inspect HEAD and full git history (legacy app at `26ccb14`)
- [x] `DOCUMENTATION/AUDIT.md`
- [x] This roadmap

### Phase B – PHP 8.4 & dependencies
- [ ] New `composer.json` (PSR-4 `Nebrix\`, PHP `~8.3.0 || ~8.4.0`, stable deps, dev tools), commit `composer.lock`
- [ ] Remove unused/abandoned packages (see AUDIT §6)
- [ ] Docker image on `php:8.4-apache`, non-root, hardened Apache vhost
- [ ] Remove broken skeleton files (`software/`, `debug/`, `system.php`) after replacement

### Phase C/D – Core
- [ ] Config/env loader, service container, error handler (no debug output in production)
- [ ] PDO connection (utf8mb4, strict mode, exceptions, no emulated prepares), transactions
- [ ] Migration runner + initial schema (`nx_` prefix, FKs, indexes)
- [ ] Request/Response, router, middleware pipeline
- [ ] Security headers + CSP, CSRF, session (DB, hashed IDs), rate limiter, encrypter, validator, URL & redirect guards
- [ ] Twig view layer with auto-escaping, layout, CSS/JS assets (no inline JS)
- [ ] CLI `bin/nebrix`

### Phase E – Auth & RBAC
- [ ] Register, e-mail verification, login, logout, throttling, login history
- [ ] Password reset (hashed, expiring, single-use tokens)
- [ ] TOTP 2FA with recovery codes
- [ ] Roles (Super Administrator, Administrator, Support, Finance, Customer), configurable permissions, gate + middleware
- [ ] Audit log

### Phase F – Admin panel
- [ ] Dashboard KPIs, health, notifications, job status
- [ ] General / branding / e-mail (SMTP + test) / system settings
- [ ] E-mail templates editor
- [ ] Customers, customer groups
- [ ] Products (all categories, billing cycles, tax, stock, provisioning)
- [ ] Staff, roles & permissions, audit log, login history

### Phase G/H – Integrations
- [ ] Integration registry, encrypted credentials, health checks, logs, enable/disable
- [ ] Proxmox VE (multi-cluster, nodes, VM + LXC lifecycle, tasks)
- [ ] Pterodactyl (Application API: nodes, nests, eggs, locations, users, servers)
- [ ] Nextcloud (OCS provisioning: users, quota, enable/disable)
- [ ] TeamSpeak 3 (ServerQuery: virtual servers, slots, ports, tokens)

### Phase I – Payments
- [ ] Gateway abstraction, settings (enable, sandbox/live, credentials)
- [ ] PayPal Orders v2: create, approve, capture, refund, webhook verification
- [ ] Stripe Checkout: session, webhooks (signature), refunds, status sync
- [ ] Idempotent payment recording, amount/currency validation, immutable payment events
- [ ] Account credit (legacy balance)

### Phase J – Orders & provisioning
- [ ] Cart/order/invoice flow
- [ ] DB job queue + worker + admin retry
- [ ] Idempotent provisioners per category
- [ ] Renewals, expiry, suspension, optional termination

### Phase K – Customer panel
- [ ] Dashboard, profile, security (password, 2FA, sessions)
- [ ] Services list + per-type management
- [ ] Orders, invoices, transactions, renewals, store
- [ ] Support tickets

### Phase L – QA & docs
- [ ] PHPUnit (unit, integration with MariaDB, HTTP feature tests)
- [ ] PHPStan, `php -l` on 8.4, `composer validate`
- [ ] All documentation files, README
- [ ] Final security review

## Session log

### Session 1 (2026-11-27)
- Completed Phase A.
- **Next step:** Phase B – rewrite `composer.json`, install dependencies, create the core skeleton.

## Known issues / blockers
- Real credentials for PayPal, Stripe, Proxmox, Pterodactyl, Nextcloud and TeamSpeak are not available in the development environment. All integrations are tested against mocked HTTP/socket responses only; see `INTEGRATIONS.md` for what has and has not been verified against live systems.
- Secrets committed to git history (AUDIT C-01/C-02) must be rotated by the repository owner; history rewriting is not performed by this session.
