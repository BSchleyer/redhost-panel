# Architecture

Nebrix is a server-rendered PHP 8.3/8.4 application without a full-stack framework. It consists of a small explicit core (HTTP kernel, router, container, database layer), a service layer per business domain, and Twig templates. Long-running and remote work runs in a database-backed job queue processed by a CLI worker.

## Runtime components

| Component | Command / entry point | Purpose |
|---|---|---|
| Web application | `public/index.php` (Apache/PHP-FPM) | Customer panel, admin panel, REST API (`/api/v1`), payment webhooks |
| Worker | `bin/nebrix queue:work` | Provisioning, suspensions, terminations, e-mail delivery |
| Scheduler | `bin/nebrix schedule:run` (cron, every minute) | Renewals, overdue suspension, health checks, data retention |
| Console proxy (optional) | `bin/nebrix console:proxy` | WebSocket relay between noVNC in the browser and the Proxmox console |
| Database | MariaDB 10.6+/11.x or MySQL 8.0+ | All state, including sessions, jobs and rate limits |

No cache server or message broker is required. All three PHP processes share the same code base and database.

## Request lifecycle

```
public/index.php
 └─ Application::create()        env (.env), config/app.php, container (config/container.php + config/services.php), error handler
    └─ HttpKernel::handle()
       ├─ Router::match()                              404/405 handled later inside the pipeline
       ├─ global middleware
       │    StartSession → LoadAuthenticatedUser → SetLocale → MaintenanceMode → VerifyCsrfToken
       ├─ route middleware (auth, guest, verified, staff, permission:…)
       ├─ controller method (arguments resolved by name/type; int params validated)
       └─ SecurityHeaders::apply()                      CSP, frame protection, no-store, HSTS
```

Every middleware layer converts exceptions into responses (`HttpException` → error page, `ValidationException` → redirect back with flashed errors), so session persistence always runs and no stack trace reaches a client.

REST API routes are registered as **stateless** (`/api/v1`). For them the kernel uses a separate global stack without `StartSession`/`VerifyCsrfToken`:

```
PrepareApiRequest (API enabled? JSON body ≤ 1 MiB)
 └─ route middleware: api.auth (Bearer token, per-token rate limit) → api.idempotent (Idempotency-Key)
                      → api.scope:<scope> (token scope; admin scopes also re-check the staff permission)
    └─ Controller\Api\… (same repositories and services as the web controllers)
```

Errors on stateless routes (including unknown paths below `/api/`) are rendered as `{"error": {"code", "message"}}`.

## Source layout

```
bin/nebrix                  CLI entry point (commands registered in config/commands.php)
config/                     app.php (env-based config), container.php, services.php, routes.php,
                            settings.php (admin settings schema), mail_templates.php, commands.php
database/migrations/        forward-only migrations (nx_ tables)
public/                     document root: index.php, assets/, uploads/ (no PHP execution)
resources/views/            Twig templates (layouts, auth, account, customer, admin, errors)
resources/lang/             translation catalogs (English source strings → translation)
src/
  Admin/                    dashboard statistics
  Api/                      REST API: tokens (ApiTokenService), scopes, middleware, resource representations, OpenAPI document
  Audit/                    append-only audit log
  Auth/                     User model, AuthService, TwoFactorService, TokenService, LoginHistory
  Authorization/            Permission catalog, Gate (RBAC)
  Billing/                  Money, orders, invoices, sequences, credit ledger, OrderService, InvoiceService
  Catalog/                  product categories (provisioning schema), billing cycles
  Console/                  command runner and commands
  Controller/               Admin/, Customer/, Account/, Auth/, Api/ (REST API), webhooks, health, branding CSS
  Core/                     Application, Container, Config, Env, Clock, ErrorHandler, LoggerFactory
  Customer/                 administrative account operations
  Domain/                   registrar interface, registrant contacts, phone number formatting
  Database/                 Connection (PDO wrapper), Migrator
  Http/                     Request, Response, Router, Route, HttpKernel, Controller, middleware
  Integration/              registry, repository, manager, API client; clients for Proxmox, Hetzner Robot, Plesk,
                            INWX, Pterodactyl, Nextcloud, TeamSpeak
  Legacy/                   RED-Host importer
  Mail/                     Mailer (PHPMailer), MailTemplates, SendMailJob
  Network/                  IP pools (IPv4 address management)
  Payment/                  PaymentService, gateways (Stripe, PayPal, credit), webhook deduplication
  Provisioning/             Provisioner interface, provisioners (Proxmox, Hetzner, Plesk, domains, Pterodactyl,
                            Nextcloud, TeamSpeak), service actions, ProvisioningService, ServiceManager, jobs
  Queue/                    JobQueue, Worker
  RemoteConsole/            console sessions (single-use tokens) and the WebSocket proxy
  Repository/               users, roles, groups, products
  Scheduler/                periodic tasks
  Security/                 CSRF, password hashing/policy, encryption, rate limiting, URL/redirect guards, validator
  Service/                  Service model and repository
  Session/                  database-backed sessions
  Settings/                 runtime settings repository, branding uploads
  Support/                  support tickets
  View/                     Twig environment, extension (escaping helpers, money/date filters), translator
tests/                      Unit/, Integration/ (MariaDB), Feature/ (full HTTP requests), Support/ (fakes)
```

## Layers and responsibilities

* **Controllers** validate input with `Validator` (whitelist → no mass assignment), check permissions (`authorize()`), call services and render templates. They never contain SQL that depends on request data except through repositories.
* **Services** (`AuthService`, `OrderService`, `InvoiceService`, `PaymentService`, `ProvisioningService`, `ServiceManager`, `CustomerService`, `LegacyImporter`) contain business rules and transactions.
* **Repositories** encapsulate SQL. All queries use prepared statements; identifiers are validated; customer-facing lookups take the user ID (`findForUser`).
* **Integrations** wrap remote APIs (`ProxmoxClient`, `PterodactylClient`, `NextcloudClient`, `ServerQueryClient`); **provisioners** translate the service lifecycle into API calls.
* **Gateways** (`StripeGateway`, `PayPalGateway`, `CreditGateway`) translate provider flows into `PaymentService` calls.

## Dependency injection

`Nebrix\Core\Container` autowires class-typed constructor parameters and holds explicit factories for services that need configuration values (`config/container.php`, `config/services.php`). All services are shared per process.

## Key flows

### Order to running service

```
Customer → POST /store/{slug}
  OrderService::place()  (transaction)        price/tax/discount from product + group, stock reserved,
                                               order + order item + invoice created, free orders settled
Customer → POST /invoices/{id}/pay → provider checkout (Stripe Checkout / PayPal approval)
Provider → POST /webhooks/{stripe|paypal}  or  customer return URL (verified server-side)
  PaymentService::recordPayment() (transaction, invoice row locked)
     amount + currency must equal the invoice; reference recorded once
     InvoiceService::markPaid() → order paid → job order.fulfil (unique key)
Worker: order.fulfil   → one nx_services row per order item (unique order_item_id) → job service.provision
Worker: service.provision → Provisioner::provision()  (RetryLater while remote tasks run)
     → service active, credentials encrypted, e-mail "service provisioned"
```

### Renewal and expiry

```
Scheduler billing.renewal_invoices  (N days before next_due_at)   → renewal invoice + e-mail
Scheduler billing.suspend_overdue   (after grace period, unpaid)  → status suspended + job service.suspend
Payment of the renewal invoice      → next_due_at += cycle (from the old due date) → job service.unsuspend
Scheduler billing.terminate_suspended (opt-in, after X days)      → job service.terminate
```

## Design decisions

| Decision | Reason |
|---|---|
| No full-stack framework | The legacy code base had none; a small explicit core keeps the security-relevant behaviour (sessions, CSRF, escaping) visible and testable. |
| Twig with global auto-escaping | Eliminates the XSS class that affected the legacy views. |
| Database-backed sessions, queue, rate limiter | One dependency (MariaDB/MySQL) for small and medium deployments; all three are behind small interfaces and can be swapped later. |
| `nx_` table prefix | Lets the new schema live next to a RED-Host database for a non-destructive migration. |
| Integer minor units for money | No floating-point rounding. |
| Forward-only migrations | DDL is not transactional in MySQL/MariaDB; destructive changes must be explicit and reviewed. |
| Idempotent jobs with unique keys | Payment webhooks and workers may run more than once; provisioning must never create duplicates. |
