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

### Security
- Removed committed secrets from the working tree (they remain in the history and must be rotated by the owner), insecure Docker defaults and phpMyAdmin.
- Fixed the legacy vulnerabilities documented in AUDIT.md (session token design, impersonation, SSH command execution, unverified PayPal IPN, double-spend race, CSRF, XSS, IDOR, open redirects, SSRF, unprotected cron endpoints, plaintext third-party credentials).
- Independent security review of the new code; all reported findings were fixed: TeamSpeak port reservation and safe adoption, revoked sessions cannot be resurrected, Proxmox guest ownership marker, staff role-escalation guard, atomic rate limiting, refund/chargeback reversal, refund idempotency keys, atomic TOTP/recovery code use, password confirmation for enabling 2FA, IPv4-embedding IPv6 ranges blocked, free renewal settlement, raw PayPal webhook verification.

### Removed
- Legacy RED-Host code paths: Venocix, Plesk and paysafecard/Boltlayer integrations, the PayPal IPN handler, HTTP cron endpoints, support-PIN login, the Discord bot API.
- Archived/unused dependencies (`paypal/rest-api-sdk-php`, duplicate Proxmox and TeamSpeak libraries).

### Known limitations
- Integrations and payment providers were tested against stateful fakes only; live/sandbox verification is pending.
- LXC root passwords can only be changed by reinstalling (no Proxmox API for it); IPv6 is assigned by SLAAC only.
- Hetzner: disks of terminated pool servers are not wiped automatically; server market (auction) orders are not supported.
- No public REST API (planned next).
