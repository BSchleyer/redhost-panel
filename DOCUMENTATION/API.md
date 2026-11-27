# API and Interfaces

Nebrix currently has **no public REST API** for customers or resellers. All customer and staff functions are available through the server-rendered web interface, which is protected by sessions and CSRF tokens. This document describes the machine-facing endpoints, the command-line interface and the internal extension points.

## HTTP endpoints for machines

| Method | Path | Authentication | Purpose |
|---|---|---|---|
| `GET` | `/health` | none | Liveness/readiness check. `200 {"status":"ok","database":"ok"}` or `503 {"status":"degraded",…}`. Contains no version or configuration details. |
| `POST` | `/webhooks/stripe` | Stripe signature (`Stripe-Signature`, signing secret) | Stripe events – see [PAYMENTS.md](PAYMENTS.md). |
| `POST` | `/webhooks/paypal` | PayPal transmission headers verified via PayPal's API and the configured webhook ID | PayPal events – see [PAYMENTS.md](PAYMENTS.md). |
| `GET` | `/branding.css` | none | Brand colours as CSS custom properties (cacheable). |
| `GET` (WebSocket) | `CONSOLE_WS_URL` (default `/console/ws?token=…`), served by `console:proxy` | single-use console token + `Origin` = `APP_URL` | Relay to the hypervisor's VNC WebSocket (see INTEGRATIONS.md). |

Webhook responses:

| Status | Meaning |
|---|---|
| `200 {"status":"processed"}` | Event verified and applied. |
| `200 {"status":"ignored"}` | Verified but not relevant (unknown type or no matching invoice/payment). |
| `200 {"status":"duplicate"}` | Event ID already processed. |
| `400` | Invalid payload or signature – not processed. |
| `503` | Verification temporarily impossible (PayPal unreachable) – the provider retries. |
| `500` | Processing failed; the event is stored with status `failed` and the provider retries. |

There are no other unauthenticated endpoints that change state, and there is no HTTP-triggered cron endpoint.

## Command-line interface

`php bin/nebrix <command>` (run as the web server user):

| Command | Description |
|---|---|
| `key:generate` | Prints a new `APP_KEY` (does not modify files). |
| `migrate [--pretend]` | Applies pending migrations (`--pretend` lists them only). |
| `migrate:status` | Shows applied and pending migrations. |
| `user:create-admin --email=… [--role=super_admin\|admin\|support\|finance] [--first-name=…] [--last-name=…]` | Creates a staff account; asks for the password interactively. |
| `integrations:check` | Runs the connection test of every enabled integration. |
| `queue:work [--once] [--max-jobs=N] [--max-time=SECONDS] [--sleep=3]` | Processes background jobs. |
| `schedule:run [--force]` | Runs due scheduler tasks (every minute from cron). |
| `legacy:import [--dry-run] [--only=users,services,tickets,history] [--proxmox-nodes=…] [--teamspeak-hosts=…] [--pterodactyl=…] [--plesk=…]` | Imports accounts, services, tickets and payment history from a RED-Host database (see [UPGRADE_GUIDE.md](UPGRADE_GUIDE.md)). |
| `console:proxy [--listen=127.0.0.1:8081]` | WebSocket proxy for server consoles (long-running). |
| `db:triggers [--install\|--remove]` | Shows, installs or removes the append-only triggers. |

Exit code `0` means success; any other value indicates an error (printed to stderr).

## Background jobs

Jobs are rows in `nx_jobs` with a type, a JSON payload and an optional unique key. Built-in types:

| Type | Payload | Dispatched by |
|---|---|---|
| `order.fulfil` | `order_id` | payment of an order invoice |
| `service.provision` | `service_id` | order fulfilment, staff "retry provisioning" |
| `service.suspend` / `service.unsuspend` | `service_id` | overdue scheduler, staff, refunds/disputes, renewal payment |
| `service.terminate` | `service_id` | staff, opt-in termination scheduler |
| `mail.send` | encrypted message | every outgoing e-mail |

Handlers implement `Nebrix\Queue\JobHandler` (optionally `FailureAwareJobHandler`) and are registered in `Nebrix\Provisioning\JobRegistry::handlers()`. A handler can throw `RetryLaterException` (wait without consuming an attempt) or `PermanentJobFailure` (no retry).

## Extension points

| Extension | Interface / location |
|---|---|
| Payment gateway | `Nebrix\Payment\PaymentGateway` – register in `GatewayRegistry`; record money only through `PaymentService` (`recordPayment`, `recordRefund`, `recordDispute`). |
| Provisioning module | `Nebrix\Provisioning\Provisioner` – register in `ProvisionerRegistry`; see [INTEGRATIONS.md](INTEGRATIONS.md#adding-a-new-integration-type). Actions are `ServiceAction` objects (label, validated fields, confirmation, mode `sync`, `reinstall` or `redirect`). |
| Console | `Nebrix\Provisioning\ConsoleProvider` (returns the upstream WebSocket target). |
| Order checks | `Nebrix\Provisioning\OrderPrecheck` (validate an order before it is placed, e.g. domain availability). |
| Domain registrar | `Nebrix\Domain\Registrar` (implemented by `Integration\Inwx\InwxClient`). |
| Integration type | `Nebrix\Integration\IntegrationType` (fields) + client based on `Integration\Http\ApiClient`. |
| Product category | `Nebrix\Catalog\ProductCategory` (provisioning fields and customer options with validation rules). |
| Settings | `config/settings.php` (typed, validated, optionally secret). |
| E-mail templates | `config/mail_templates.php` (defaults, variables). |
| Permissions | `Nebrix\Authorization\Permission` (catalog and default role assignments). |
| CLI command | `Nebrix\Console\Command` – register in `config/commands.php`. |
| Scheduler task | `Nebrix\Scheduler\Scheduler::tasks()`. |

## A future REST API

If a public API is added, it should use separate API tokens (hashed like other tokens, scoped to permissions), its own rate limits, JSON error responses without CSRF (token-authenticated) and the same service layer and authorization checks as the web interface – never a second implementation of business rules.
