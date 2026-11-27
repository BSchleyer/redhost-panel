# API and Interfaces

This document describes the **REST API** (`/api/v1`), the other machine-facing endpoints, the command-line interface and the internal extension points. The web interface itself is server-rendered and protected by sessions and CSRF tokens.

## REST API (v1)

A token-authenticated JSON API for customers (their own account, orders, services, invoices and tickets) and for staff (customers, services, orders, invoices, tickets – limited by their role). The machine-readable description is served at **`GET /api/v1/openapi.json`** (OpenAPI 3.1).

### Enabling

The API is **off by default**. Enable it in Admin → Settings → System → *REST API* (`api.enabled`). There you can also restrict token use to staff (`api.customer_tokens`; customer tokens also stop working while *Allow customer logins* is off) and set the rate limit per user (`api.rate_limit`, requests per minute for all tokens of an account together, default 120). With the API disabled every `/api/v1` request answers `404 {"error": {"code": "api_disabled"}}`.

Behind Apache with PHP-FPM/CGI the `Authorization` header is passed on by the rule in `public/.htaccess`; with nginx + PHP-FPM it is passed automatically (`fastcgi_params`).

### Tokens

Users create personal access tokens under **Account → API tokens** (password confirmation required):

* Format `nbx_` + 48 hex characters (192 random bits). The token is shown **once**; only its SHA-256 hash and an 8-character prefix are stored.
* **Scopes** (permissions of the token) – see below. Admin scopes are only offered to staff and only for permissions their role has.
* **Lifetime** 7, 30, 90, 180 or 365 days (every token expires), optional **IP allowlist** (addresses or CIDR ranges), at most 20 active tokens per user.
* The owner receives an e-mail for every new token.
* Revocation by the owner (Account → API tokens) or by staff with `api_tokens.manage` (Admin → API tokens: single tokens or all tokens of a user; tokens of super administrators only by super administrators).
* **All tokens of an account are revoked** when its password is changed or reset, when staff use *Sign out everywhere* or reset its two-factor authentication, and when the account is closed (the token rows are then anonymised). Suspended accounts cannot use their tokens.

Send the token in every request:

```bash
curl -H "Authorization: Bearer nbx_…" https://panel.example.com/api/v1/me
```

Session cookies are ignored and no session or cookie is created; CSRF tokens are not needed (and not accepted as authentication).

### Scopes

| Scope | Allows |
|---|---|
| `account:read` | `GET /me` |
| `products:read` | `GET /products`, `GET /products/{id}` (products the customer can order, with group prices) |
| `orders:read` / `orders:create` | list/read own orders / place orders (`POST /orders`) |
| `services:read` | own services, live status, available actions |
| `services:manage` | run service actions (`POST /services/{id}/actions/{action}`) |
| `services:credentials` | access data of own services (audit-logged); also required for single sign-on actions (e.g. *Open Plesk*), which return a one-time login URL |
| `invoices:read` / `invoices:pay` | own invoices / start a payment |
| `tickets:read` / `tickets:write` | own tickets / open, reply, close |
| `admin:customers:read` | `GET /admin/customers…` – needs `customers.view` |
| `admin:products:read` | `GET /admin/products` – needs `products.view` |
| `admin:orders:read` | `GET /admin/orders…` – needs `orders.view` |
| `admin:invoices:read` | `GET /admin/invoices…` – needs `invoices.view` |
| `admin:services:read` / `admin:services:write` | list services / suspend, unsuspend, terminate, retry – need `services.view` / `services.view` + `services.manage` |
| `admin:tickets:read` / `admin:tickets:write` | read tickets / reply – need `tickets.view` / `tickets.view` + `tickets.reply` |

Admin scopes are checked against the staff role **on every request** (plus admin access and, if enforced, two-factor authentication): removing a permission from a role immediately disables the matching scope of existing tokens.

### Endpoints

All paths are relative to `/api/v1`. Customer endpoints only ever return the token owner's data (other IDs answer `404`).

| Method | Path | Scope | Notes |
|---|---|---|---|
| GET | `/me` | `account:read` | Account, credit balance, token details |
| GET | `/products`, `/products/{id}` | `products:read` | `?category=` |
| GET | `/orders`, `/orders/{id}` | `orders:read` | `?status=` |
| POST | `/orders` | `orders:create` | `{"product_id": 3, "accept_terms": true, "options": {"hostname": "web01"}}` → order + `invoice_id`; same checks, prices and limits (10/hour) as the store |
| GET | `/services`, `/services/{id}` | `services:read` | `?status=` |
| GET | `/services/{id}/status` | `services:read` | Live data from the remote system (`502 upstream_error` if unavailable); together with `/actions` at most 30 requests per minute and user |
| GET | `/services/{id}/actions` | `services:read` | Available actions with their input fields |
| POST | `/services/{id}/actions/{action}` | `services:manage` | Field values in the body; actions that the web interface confirms need `"confirm": true`. Queued actions (reinstall, Proxmox backup restore) answer `202`; single sign-on actions (incl. backup downloads) (mode `redirect`) also need `services:credentials` and return `redirect_url`. Limits: 10 actions per 5 minutes, 3 reinstalls per hour per service |
| GET | `/services/{id}/credentials` | `services:credentials` | Audit-logged |
| POST | `/services/{id}/cancel` | `services:manage` | Ordinary cancellation `{"end_date"?: "2027-03-31", "reason"?}` → `201` with `reference`, `received_at`, `ends_at` (end of the paid term or the later requested date); a receipt e-mail is sent. `409` if the service is not active/suspended or already cancelled |
| POST | `/services/{id}/cancel/withdraw` | `services:manage` | Takes the customer's own scheduled cancellation back before it takes effect (`409` for cancellations confirmed by staff) |
| GET | `/invoices`, `/invoices/{id}` | `invoices:read` | `?status=&type=`; detail includes `items` and `payment_methods` |
| GET | `/invoices/{id}/pdf`, `/invoices/{id}/xml` | `invoices:read` | The archived PDF or XRechnung XML (binary download with `Content-Disposition: attachment`); `503 document_unavailable` if it cannot be generated (e.g. seller address missing) |
| POST | `/invoices/{id}/pay` | `invoices:pay` | `{"gateway": "credit"}` pays from the credit balance; `stripe`/`paypal` return `checkout_url` for the customer. The invoice is only marked paid by the verified provider confirmation |
| GET | `/tickets`, `/tickets/{id}` | `tickets:read` | Detail includes messages (`author`: `customer`/`staff`) |
| POST | `/tickets` | `tickets:write` | `{"subject", "message", "service_id"?, "priority"?}` (5 per hour) |
| POST | `/tickets/{id}/replies`, `/tickets/{id}/close` | `tickets:write` | |
| GET | `/admin/customers`, `/admin/customers/{id}` | `admin:customers:read` | `?search=&status=&customer_group_id=`; staff accounts are not listed |
| GET | `/admin/products` | `admin:products:read` | `?category=&active=1` |
| GET | `/admin/orders`, `/admin/orders/{id}` | `admin:orders:read` | `?status=&search=&user_id=` |
| GET | `/admin/invoices`, `/admin/invoices/{id}` | `admin:invoices:read` | `?status=&type=&search=&user_id=` |
| GET | `/admin/services`, `/admin/services/{id}` | `admin:services:read` | `?status=&category=&search=&user_id=&integration_id=`; adds `integration_id`, `external_id`, `last_error` and `suspension_reason` |
| POST | `/admin/services/{id}/suspend` | `admin:services:write` | `{"reason"?, "notify"?: true}` → `202` |
| POST | `/admin/services/{id}/unsuspend`, `/retry` | `admin:services:write` | `202` |
| POST | `/admin/services/{id}/terminate` | `admin:services:write` | `{"confirm": "TERMINATE", "reason"?}` – deletes the remote resource |
| GET | `/admin/tickets`, `/admin/tickets/{id}` | `admin:tickets:read` | `?status=&search=&user_id=&assigned_to=` |
| POST | `/admin/tickets/{id}/replies` | `admin:tickets:write` | E-mails the customer |

### Conventions

* **Requests**: JSON bodies (`Content-Type: application/json`, a JSON object, max. 1 MiB) or form data; other content types answer `415`.
* **Responses**: `{"data": …}`; lists add `"meta": {"page", "per_page", "total", "last_page"}` (`?page=`, `?per_page=` up to 100).
* **Values**: amounts are integers in minor units (cents) together with `currency`; tax rates in basis points (`1900` = 19 %); timestamps ISO 8601 in UTC. Invoices include `tax_category` (`S` standard, `AE` reverse charge, `E` exempt/small business, `O` not taxable) and `tax_note`; correction documents have `type` `credit_note`, status `issued`, positive amounts (the type marks the credit) and `reference_invoice_id` pointing to the corrected invoice. Services include `cancel_at` (date the service ends after a cancellation, otherwise `null`). Secret order options (domain auth codes), provisioning settings and the remote state are never returned; customer endpoints also omit remote IDs and internal suspension notes.
* **Errors**: `{"error": {"code": "…", "message": "…"}}`, validation errors with `"fields"`, server errors with a `"reference"` for the log. Codes: `unauthenticated` (401), `insufficient_scope`/`forbidden`/`two_factor_required`/`email_unverified`/`api_access_disabled` (403), `not_found`/`api_disabled` (404), `method_not_allowed` (405), `conflict` (409), `validation_failed`/`unprocessable`/`confirmation_required`/`order_rejected`/`payment_method_unavailable`/`idempotency_key_reused` (422), `rate_limited` (429, with `Retry-After`), `upstream_error` (502), `unavailable` (503, maintenance – staff tokens keep working), `document_unavailable` (503).
* **Rate limits**: per user and minute, shared by all tokens of the account (`X-RateLimit-Limit`, `X-RateLimit-Remaining`); additionally the same per-user limits as the web interface (orders, tickets, payments, service actions). After 30 failed authentications per IP within 5 minutes, further failures answer `429` (valid tokens from the same address keep working).
* **Idempotency**: `POST` requests accept an `Idempotency-Key` header (8–128 characters). The first response is stored for 24 hours per token and returned again for retries with the same key and body (`Idempotent-Replayed: true`) – only after the scope and permission checks have passed again; the same key with a different body answers `422 idempotency_key_reused`, a request still running `409` (a request without a response after 5 minutes is treated as abandoned and may be retried). Responses containing one-time login URLs are never stored.
* **Audit**: write operations are recorded in the audit log like their web counterparts; service actions and admin service operations get an additional entry with `via: api` and the token ID; token creation and revocation are logged.

### Not available through the API

Token management itself, the graphical console (it needs a browser session), profile and password changes, two-factor settings, payment provider settings, integrations, refunds and manual payments. These remain in the web interface.

## Other HTTP endpoints for machines

| Method | Path | Authentication | Purpose |
|---|---|---|---|
| `GET` | `/api/v1`, `/api/v1/openapi.json` | none (API enabled) | API index and OpenAPI 3.1 document. |
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

Apart from the REST API (token-authenticated) there are no other endpoints for machines that change state, and there is no HTTP-triggered cron endpoint.

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
