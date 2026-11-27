# Nebrix Panel – Codebase Audit

| | |
|---|---|
| Audit date | 2026-11-27 |
| Audited revision (HEAD) | `7eaa4ce` ("Initial framework parts") on `master` |
| Legacy reference revision | `26ccb14` (last commit that still contained the RED-Host application, 2022-05-21) |
| Auditor | Automated modernization session (Claude Code) |

> **Secrets policy for this document.** Several credentials were found in the repository and its history. Their *locations* are listed so they can be rotated; their *values* are intentionally not reproduced anywhere in this document, in commit messages, or in logs.

---

## 1. Executive summary

The repository is in two very different states:

1. **Current HEAD (`master`)** contains an unfinished 2024 restart of the project: 11 PHP files (front controller, kernel, a static `Controller` base class, alert helpers) plus Docker files. **It cannot boot**: `public/index.php` includes `router/app.php`, which does not exist, and `debug/index.php` references `software/controller/PageController.php`, which is empty. There is no router, no data model, no database schema, no views, and no business logic. `composer.json` declares 19 runtime packages that no code uses.
2. **The functional RED-Host application** (171 non-asset files, ~1.1 MB of PHP/HTML) only exists in git history. It was deleted by commit `35c2c9d` ("initial commit", 2022-09-13). It is a procedural, include-driven PHP 7 application (customer panel for TeamSpeak, LXC/KVM via Proxmox, Venocix KVM, Plesk webspace, Pterodactyl game servers, credit-balance billing via PayPal IPN and paysafecard/Boltlayer, support tickets, a small "team" back office).

Consequently there is **no working functionality on HEAD that could be broken** by introducing a new architecture, and the legacy implementation is **not suitable as a base** (see §7). The recommended and implemented strategy is to build a new modular core on top of the HEAD skeleton, keep the legacy database schema untouched, and provide a **read-only, idempotent legacy importer** for users and balances (§8).

**Overall risk rating of the legacy application: Critical.** It contains account-takeover paths, payment manipulation risks, stored XSS, missing CSRF protection on 38 of 42 forms, remote command execution into customer containers as `root`, and committed production secrets.

---

## 2. Architecture overview

### 2.1 Current HEAD (2024 skeleton)

```
public/index.php          front controller → includes ../system.php, vendor/autoload.php,
                          software/Kernel.php, software/backend/autoload.php, router/app.php (missing)
system.php                defines SYSTEM_START, BASE_PATH
software/Kernel.php       ob_start(), session_start(), Dotenv, Whoops when debug=true
software/Globals.php      env(), dd(), description(), priceCalc(), format_number()
software/backend/autoload.php   glob('../software/backend/*.php') include loop (directory is empty)
software/controller/Controller.php  static helpers: db() (new PDO per call), url(), setcookie()
software/controller/PageController.php  empty file
software/alerts/send.php  toastr/SweetAlert helpers that concatenate raw strings into <script>
debug/index.php           dumps a message via dd()
.docker/, docker-compose.yml   PHP 8.2 + Apache, MySQL 8, phpMyAdmin
```

### 2.2 Legacy RED-Host application (`26ccb14`)

```
public/index.php → router/app.php: switch($_GET['page']) { include resources/<area>/<page>.php }
public/.htaccess        rewrite rules mapping pretty URLs to ?page=…
public/paypal_ipn.php   PayPal IPN listener
app/Kernel.php          session_start(), Dotenv, Whoops
app/functions/*.php     "service" classes that are instantiated into globals at include time
                        ($user, $helper, $site, $lxc, $kvm, $ts3, $pterodactyl, $plesk, $venocix …)
app/controller/PageController.php  per-request auth check, layout includes, flash messages
app/manager/customer/** POST handlers included at the top of each view
app/crone/*.php         cron endpoints triggered over HTTP with ?key=CRONE_KEY
resources/**            views mixing SQL, business logic and HTML (Metronic theme)
admin_redhost_panel.sql schema dump (31 tables, utf8, no foreign keys)
```

Request flow: Apache rewrite → `index.php` → global objects created by `glob()` include → `router/app.php` includes a view → the view includes `PageController.php` (authentication + layout) and a "manager" file (form handling) → HTML output.

There is no separation between controllers, models, views, services or persistence; every view queries the database directly and most logic is executed via `include` side effects on global variables.

---

## 3. Technology stack

| Area | HEAD | Legacy | Notes |
|---|---|---|---|
| PHP | `>=8.0` declared, Docker image `php:8.2-apache` | PHP 7.x (implicit) | Neither declares or tests PHP 8.4 |
| Web server | Apache + mod_rewrite (`.htaccess` is empty on HEAD) | Apache + `.htaccess` routing | |
| Database | MySQL 8 (docker-compose) | MySQL/MariaDB, `utf8` (3-byte) charset, MyISAM-era dump style | No migrations, no foreign keys |
| Dependency mgmt | Composer, lock file git-ignored | Composer | Non-reproducible builds |
| Templates | none | Raw PHP + Metronic admin theme (commercial; assets deleted from HEAD) | |
| Auth | none | Custom: random 30-char token in `users.session_token`, sent in a plain cookie | |
| Payments | none (Stripe/PayPal/Mollie SDKs declared but unused) | PayPal **IPN** (legacy, deprecated by PayPal), Boltlayer/paysafecard | Prepaid credit balance model |
| Integrations | none (SDKs declared but unused) | Proxmox (username/password), Venocix, Plesk, Pterodactyl (hcgcloud SDK), TeamSpeak (`ts3admin.class.php` + planetteamspeak framework) | |
| Background work | none | HTTP-triggered "cron" URLs, `queue` table without locking | |
| Tests / CI | none | none | |

---

## 4. Security findings

Severity uses the following scale: **Critical** – direct compromise of accounts, money, infrastructure or secrets; **High** – exploitable with modest preconditions, significant impact; **Medium** – defense-in-depth gap or limited impact; **Low** – hardening / hygiene.

Each finding states where it is located (H = HEAD, L = legacy `26ccb14`) and how the new implementation addresses it (see `SECURITY.md` for details).

### 4.1 Critical

| ID | Finding | Location | Remediation in new code |
|---|---|---|---|
| C-01 | **Production secrets committed to git history.** A full `.env` with values for `DB_HOST/DB_NAME/DB_USER/DB_PASS`, `CRONE_KEY`, `GLOBAL_API_KEY`, `DISCORD_WEBHOOK_URL`, `PTERODACTYL_BASE_URL`, `PTERODACTYL_API_KEY` and `PAYPAL_EMAIL` was committed in `fa2ebdb`, `b659218`, `9132ce4`, `f9180bd`, `9646f17` and `d803647`. Anyone with read access to the repository history can retrieve them. | L (history) | `.env` is git-ignored, `.env.example` only contains placeholders. **The repository owner must rotate every listed credential**; rewriting public history (e.g. `git filter-repo`) is optional and must be decided by the owner because it rewrites published commits. |
| C-02 | **hCaptcha secret key and site key in `.env.example`** on HEAD (real-looking values, not placeholders). | H `.env.example` | Replaced with empty placeholders; owner must revoke the key pair at hCaptcha. |
| C-03 | **Hard-coded database passwords** (`MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, also used in the healthcheck command) and `MYSQL_ALLOW_EMPTY_PASSWORD=1` in `docker-compose.yml`; MySQL and phpMyAdmin are published on all interfaces (`3306`, `81`). | H `docker-compose.yml` | Compose reads credentials from `.env`, database port is not published, phpMyAdmin removed. |
| C-04 | **Account takeover via support-PIN impersonation.** `team/user/{base64(s_pin)}` copies the *victim's* `session_token` into the staff member's cookie; the 5-digit numeric PIN is brute-forceable (100 000 values, no rate limit) via the PIN login form and via `api/v1/discord/setDiscordID`. There is no audit log and no permission beyond "is support". | L `resources/team/user/*.php`, `resources/team/login_back.php`, `resources/api/discord/setDiscordID.php` | Impersonation not implemented (not required); staff manage customers through dedicated, permission-checked and audit-logged admin actions. No PIN-based authentication. |
| C-05 | **Session token is a bearer secret stored in plaintext in `users.session_token`**, generated with `rand()` (not a CSPRNG), sent in a cookie without `Secure`/`HttpOnly`/`SameSite`, valid for 1–10 days, and *one token per user* (a new login replaces it). Leaking the DB leaks all sessions. Maintenance mode "logs everyone out" by rotating these tokens. | L `User::generateSessionToken`, `Helper::generateRandomString` | Server-side sessions in `nx_sessions`, IDs generated by PHP's CSPRNG, stored **hashed** (SHA-256), regenerated on login/privilege change, idle + absolute timeouts, `Secure; HttpOnly; SameSite=Lax` cookies, revocation on password change. |
| C-06 | **Remote command execution as root into customer containers.** The panel stores Proxmox node *root passwords* (`vm_host_nodes.root_password`) and executes shell commands over SSH (`LXC::exec`) built by string concatenation, including `wget` of a script from a third-party CDN that is then executed with a customer-supplied password as argument (`sh changePassword.sh <password>`), and arbitrary "command presets"/"software installers" from the DB. Command injection via the root password field and supply-chain compromise via the external script are both possible. | L `app/functions/LXC.php::exec`, `app/manager/customer/vserver/manage.php`, `app/crone/WORKER/installer.php` | Removed. Proxmox is accessed exclusively through its HTTPS API with scoped **API tokens**; no SSH, no root passwords, no shell command construction. The initial root password is set at creation through the API (LXC `password`, KVM cloud-init `cipassword`) and stored encrypted; a customer-facing password reset, reinstall or console flow is **not implemented yet** (see IMPLEMENTATION_ROADMAP.md). |
| C-07 | **Payment manipulation risk in PayPal IPN handler.** The credited amount is taken from `mc_gross` in the IPN without comparing it to an amount the server expected; `receiver_email`, `payment_status` and `mc_currency` are not checked, so a verified IPN for *any* payment (e.g. to another merchant, pending/refunded, other currency) credits the account. The user is identified by `custom = session_token`, i.e. the session secret is sent to PayPal and stored in PayPal's records. | L `public/paypal_ipn.php`, `app/manager/customer/payment/init.php` | PayPal Orders v2: the server creates the order with the expected amount/currency, captures server-side, validates status, amount and currency against the invoice, deduplicates by capture ID (unique index) and verifies webhooks via PayPal's `verify-webhook-signature` API. The invoice ID (not a secret) is used as `custom_id`/`invoice_id`. |
| C-08 | **Race conditions and missing transactions on balance changes.** `addMoney`/`removeMoney` do read-modify-write without a transaction or row lock; balance checks happen before deduction in a separate request step. Concurrent requests allow double spending and negative balances; failure in the middle of an order leaves money removed without a service. | L `User::addMoney/removeMoney`, all `*/order.php`, `*/renew.php` managers | All money movements run in DB transactions with `SELECT … FOR UPDATE`; amounts are integer minor units; ledger rows are append-only. |

### 4.2 High

| ID | Finding | Location | Remediation |
|---|---|---|---|
| H-01 | **No CSRF protection**: 38 of 42 forms have no token; `Site::generateCSRF()` uses `rand()` and is never verified. All state-changing customer and team actions (orders, renewals, deletions, maintenance mode, user edits) are forgeable. | L `resources/**` | Synchronizer token (`random_bytes(32)`) verified with `hash_equals` on every non-GET request by middleware; `SameSite=Lax` cookies as second layer. |
| H-02 | **Reflected XSS**: `$_POST['email']`, `$_POST['username']`, `$_POST['voucher']`, `$_GET['key']` echoed unescaped into attributes. The login flow additionally echoes **the plaintext password** into a hidden form field. | L `resources/auth/login.php`, `register.php`, `forgot_password.php`, `*/renew.php`, `app/manager/customer/auth/login.php` | Twig with auto-escaping enabled globally; no reflection of passwords. |
| H-03 | **Stored XSS / script injection in alert helpers**: `sendError()`/`sendSuccess()` etc. concatenate messages into inline `<script>` strings without JS escaping; several messages contain user-controlled data. Present on HEAD as well. | H `software/alerts/send.php`, L `app/notifications/sendAlert.php` | Flash messages rendered as escaped HTML; strict CSP forbids inline scripts. |
| H-04 | **IDOR / broken access control**: `ajax/getload/{id}` returns VM status for any VM ID without authentication (`system_` pages skip login); `vserver`/`rootserver` managers process `buyTraffic` **before** the ownership check, so a user can act on another customer's server. | L `app/ajax/get_load.php`, `app/manager/customer/vserver/manage.php`, `rootserver/manage.php` | Every customer service lookup is scoped by `user_id` in the query (`findForUser`), and authorization happens before any action. |
| H-05 | **Weak password reset**: tokens generated with `rand()`, stored in plaintext, **no expiry**, keyed by username-or-e-mail text; reset form leaks whether an account exists; tokens are not invalidated on use for other pending requests. | L `app/manager/customer/auth/forgot_password.php` | 256-bit tokens, stored as SHA-256 hash, 60-minute expiry, single use, all other tokens and sessions revoked on reset, uniform response (no account enumeration), rate-limited. |
| H-06 | **No brute-force protection**: the login "captcha after a failed attempt" is controlled by a client cookie (`7apwy35m2budptd7`) the attacker simply omits; no rate limit anywhere (login, register, reset, support PIN, API). | L `app/manager/customer/auth/login.php` | Server-side rate limiter (per IP and per account), generic error messages, login history. |
| H-07 | **Cron / API endpoints authenticated by static keys in URLs** (`/crone/worker_queue/{CRONE_KEY}`) compared with `==`; keys end up in web-server logs; the Discord API sends `Access-Control-Allow-Origin: *`. | L `app/crone/*.php`, `resources/api/*` | Background work runs from the CLI (`bin/nebrix queue:work`, `schedule:run`) – not reachable over HTTP. |
| H-08 | **Plaintext third-party credentials in the database**: Proxmox user/root passwords (`vm_host_nodes`), TeamSpeak query passwords (`teamspeak_hosts`), Pterodactyl and Plesk customer passwords (`users.pterodactyl_password`, `plesk_password`). | L schema | Secrets are encrypted at rest with libsodium (XChaCha20-Poly1305) using `APP_KEY`; they are never rendered back to the browser after saving. |
| H-09 | **Error/debug exposure**: Whoops pretty page handler enabled when `debug=true` (the example config ships `debug=true`); `dd()` of mail errors during registration; `error_reporting(E_ALL ^ E_WARNING && E_NOTICE)` (operator precedence bug → effectively `E_ALL ^ 1`). | H+L `Kernel.php`, `public/index.php` | Central error handler: generic error page in production, details only in logs; debug forced off unless `APP_ENV=local`. |
| H-10 | **Insecure IP handling**: `getIP()` trusts `X-Forwarded-For`/`Client-IP` headers from any client; sessions are "bound" to this spoofable value. | L `User::getIP` | Client IP taken from `REMOTE_ADDR`; forwarded headers only honoured for explicitly configured trusted proxies. |

### 4.3 Medium

| ID | Finding | Location | Remediation |
|---|---|---|---|
| M-01 | Passwords hashed with bcrypt cost 10; no rehash path; no minimum password policy. | L `User::create` | Argon2id (`PASSWORD_ARGON2ID`) with automatic rehash-on-login of legacy bcrypt hashes; minimum length 10, a small deny-list of common passwords and a check against the user's own e-mail/name (no external breach-database lookup). |
| M-02 | No security headers (CSP, HSTS, X-Frame-Options, Referrer-Policy, …). Clickjacking possible. | H+L | `SecurityHeaders` middleware with strict CSP (no inline script), `frame-ancestors 'none'`, HSTS on HTTPS. |
| M-03 | Open redirect potential: `header('Location: '.$site->currentUrl())` builds URLs from `Host` header (host header injection). | L multiple managers | Redirects only to relative paths or the configured `APP_URL` host. |
| M-04 | SSRF: admin-controlled integration hostnames are used without validation; responses echoed back. | L Proxmox/Venocix | Integration URLs validated (scheme, no credentials, cloud metadata ranges blocked), redirects disabled, timeouts enforced. |
| M-05 | Missing input validation for numeric/domain/order parameters (e.g. `traffic_amount`, ports, slot counts trusted from POST). | L managers | Central `Validator` with typed rules; product configuration only taken from server-side product definitions. |
| M-06 | E-mail verification codes generated with `rand()`, never expire. | L `register.php` | Hashed, expiring single-use tokens. |
| M-07 | New PDO connection on every `db()` call (dozens per request), charset `utf8` (3-byte, cannot store all Unicode). | H+L `Controller::db()` | One shared connection per request, `utf8mb4`, emulated prepares disabled, strict SQL mode. |
| M-08 | Personal data handling: DSGVO export page renders all data but deletion is manual; plaintext login IP/user-agent history is unbounded. | L `dsgvo.php`, `login_logs` | Login history retention setting; account deletion = anonymisation flow for admins. |
| M-09 | Queue worker has no locking: two concurrent cron calls process the same job; failures set `retries=255` and stop silently. | L `work_queue.php` | Jobs reserved atomically (`SELECT … FOR UPDATE SKIP LOCKED` inside a transaction), retries with backoff, failed jobs visible and retryable in the admin panel. |

### 4.4 Low

| ID | Finding | Location |
|---|---|---|
| L-01 | `debug/index.php` lives outside `public/` but is still routed in the legacy app (`/DEBUG`). | H+L |
| L-02 | Composer lock file is git-ignored → non-reproducible deployments. | H `.gitignore` |
| L-03 | `minimum-stability: dev` in `composer.json`. | H |
| L-04 | `robots.txt` allows indexing of the customer panel. | H |
| L-05 | Apache config enables `Options Indexes`/`All` and an unrelated `ProxyPassMatch` rule. | H `.docker/apache/demo.apache.conf` |
| L-06 | Docker image installs `mysqli` although only PDO is used; runs as root. | H `.docker/Dockerfile` |

---

## 5. PHP 8.4 compatibility problems

| Location | Problem under PHP 8.x / 8.4 |
|---|---|
| H `Controller::setcookie($name, $variable, $time = '777600', $path = '/', $domain, $secure = 0)` | Optional parameter before a required one – deprecated since PHP 8.0, the defaults of `$time`/`$path` are ignored. |
| H+L `env()` uses `getenv()` + `isset()` | `getenv()` returns `false`, never `null`, so the default is never used; with phpdotenv v5 values are not put into `getenv()` by default. |
| H `Globals::priceCalc()` | `%` on a formatted *string* (`number_format`) → implicit float-to-int conversion deprecation in 8.1+, wrong results for values ≥ 1000 (thousands separator). |
| H `description($text, $default = _SEO_DESCRIPTION)`, alert helpers using `_ALERT_*` | Undefined constants are a fatal `Error` since PHP 8.0. |
| L `new DateTime(null, …)` | Passing `null` to non-nullable internal parameters is deprecated since 8.1. |
| L `$res->metadata->clientRequestId = null` on `stdClass` | Creating default object from empty value is an `Error` since 8.0. |
| L `error_reporting('E_STRICT')` | String argument; `E_STRICT` is deprecated in PHP 8.4. |
| L widespread `$_GET['x']`, `$_COOKIE['x']` without `isset` | Warnings ("Undefined array key") since 8.0, hidden only by the error_reporting bug. |
| L `ts3admin.class.php` (182 KB) | 46 uses of `utf8_encode`/`utf8_decode` (deprecated 8.2) and other legacy constructs. |
| L `curl_close()` | No-op since 8.0 and deprecated in 8.5 – harmless but obsolete. |
| L `phpseclib ^2.0`, `guzzlehttp/guzzle ^6.3`, `vlucas/phpdotenv ^4.1`, `symfony/var-dumper ^5.0`, `paypal/rest-api-sdk-php` | Not maintained for PHP 8.4 (see §6). |
| All | No `declare(strict_types=1)`, no parameter/return/property types. |

---

## 6. Dependency review (`composer.json` on HEAD)

| Package | Constraint | Status | Decision |
|---|---|---|---|
| `guzzlehttp/guzzle` | `^6.3` | EOL, PHP 8.4 deprecations | **Upgrade to `^7`** (HTTP client for all REST integrations) |
| `vlucas/phpdotenv` | `^4.1` | Superseded | **Upgrade to `^5`** |
| `phpmailer/phpmailer` | `^6.1` | Maintained | **Keep, `^6.9`** |
| `stripe/stripe-php` | `^12.2` | Outdated major | **Upgrade to current major** (official SDK, required) |
| `robthree/twofactorauth` | `^1.8` | Outdated major | **Upgrade to `^3`** (TOTP) + `bacon/bacon-qr-code` for local QR rendering |
| `paypal/rest-api-sdk-php` | `^1.6` | **Archived/deprecated by PayPal** (v1 Payments API) | **Remove**; use PayPal REST v2 (Orders, Payments, Webhooks) over Guzzle |
| `planetteamspeak/ts3-php-framework` | `^1.1` | Unmaintained 1.x line, legacy constructs | **Remove**; small, fully tested ServerQuery client with strict escaping |
| `lumaserv/proxmoxve`, `corsinvest/cv4pve-api-php` | `^4.0`, `^8.0` | Two overlapping clients, password/ticket auth | **Remove**; Proxmox REST client with API-token auth over Guzzle |
| `hcgcloud/pterodactyl-sdk` | `^1.0` | Requires Guzzle 6, unmaintained | **Remove**; Application API client over Guzzle |
| `plesk/api-php-lib` | `^1.0` | Plesk not in target scope | **Remove** (documented as possible future integration) |
| `mollie/mollie-api-php` | `^2.0` | Not in target scope | **Remove** |
| `phpseclib/phpseclib` | `^2.0` | Only used for root SSH exec (C-06) | **Remove** |
| `sentry/sdk` | `3.1.1` (pinned) | Outdated, unused | **Remove** (optional future observability) |
| `filp/whoops`, `symfony/var-dumper` | runtime | Debug tools in production deps | **Move to `require-dev`** |
| – | – | No template engine (XSS root cause) | **Add `twig/twig ^3`** (auto-escaping) |
| – | – | No test tooling | **Add `phpunit/phpunit`, `phpstan/phpstan`** (dev) |

`minimum-stability` is set to `stable` and `composer.lock` is committed.

---

## 7. Technical debt and why the legacy architecture is not reused

1. **No layering.** SQL, business rules and HTML are interleaved in ~120 view files; managers communicate through global variables created by include side effects. There is no seam to add CSRF, authorization or validation centrally – every one of the 42 forms would have to be fixed by hand, and every future change would repeat that risk.
2. **Global mutable state.** `$user`, `$helper`, `$db`, `$userid`, `$amount` … are created by `glob()`-ordered includes; behaviour depends on file-system ordering.
3. **Duplicate implementations.** Three copies of the mail templates (`mail_templates`, `mail_templates_12`, `mail_templates_oldd`), duplicate legal pages (`agb.php`/`agb1.php` …), two TeamSpeak libraries, two Proxmox libraries, LXC/KVM classes that are 80 % copy-paste, per-product order/renew/reconfigure managers with the same money logic copied five times.
4. **Database design.** No foreign keys, no `utf8mb4`, money as `decimal` mixed with strings, `enum` states with inconsistent casing (`ACTIVE` vs `active`), a single-row `settings` table, VM IDs derived from `MAX(id)+1` (race), product prices referenced by free-text names, soft deletes without indexes.
5. **Billing model.** Prepaid credit with read-modify-write updates and no ledger; no invoices, no order entity, no tax handling, no idempotency.
6. **HTTP-triggered cron** with secret keys in URLs and no locking.
7. **Vendor-specific integrations** (Venocix, Plesk, Boltlayer) that are outside the Nebrix scope and whose APIs have changed since 2022.
8. **Commercial theme** (Metronic) whose assets were already removed from HEAD and whose licence does not transfer to a fork.

Because HEAD contains no working functionality, a modular rewrite on the HEAD skeleton does not break anything that currently runs. Legacy behaviour that *is* worth preserving is carried over explicitly:

| Legacy capability | Preserved as |
|---|---|
| Customer accounts (username, e-mail, bcrypt password, address, state, role) | `nx_users` + read-only `legacy:import` command; bcrypt hashes keep working and are upgraded to Argon2id on next login |
| Prepaid credit balance | `credit_balance` on the customer + "account credit" payment method (ledger-backed) |
| TeamSpeak, LXC/KVM (Proxmox), game servers (Pterodactyl) | Rebuilt integrations with the same product types |
| Expiry → suspension → deletion lifecycle (`runtime_queue.php`) | Scheduler with configurable grace periods; automatic termination is opt-in |
| Support tickets | Ticket module in customer and admin panel |
| Login/registration toggles, maintenance mode, legal acceptance | General settings |
| Login logs | Login history |

---

## 8. Recommended architecture (implemented)

See `ARCHITECTURE.md` for the detailed description. In short:

* **PHP 8.3/8.4, strict types**, PSR-4 autoloading (`Nebrix\` → `src/`), no full-stack framework; a small explicit service container.
* **Layers:** `Http` (router, middleware, controllers) → `Service` layer (auth, billing, payments, provisioning) → `Repository` layer (PDO, prepared statements only) → MariaDB/MySQL.
* **Views:** Twig with global auto-escaping; strict Content Security Policy, no inline JavaScript.
* **Security primitives:** CSRF middleware, Argon2id, DB-backed hashed sessions, rate limiter, libsodium secret encryption, URL/SSRF guard, redirect guard, validator, RBAC gate, append-only audit log.
* **Integrations:** one service class per provider implementing a common interface (test connection, health, logging, enable/disable), credentials encrypted, multiple instances per provider.
* **Payments:** gateway interface; PayPal Orders v2 and Stripe Checkout; webhook verification; idempotent `PaymentService` with amount/currency validation; immutable payment event log.
* **Provisioning:** DB-backed job queue processed by a CLI worker; idempotent provisioners keyed by order item / service; admin retry.
* **Database:** versioned migrations (`bin/nebrix migrate`), `nx_` table prefix so the new schema can live next to an existing RED-Host database without collisions, foreign keys, `utf8mb4`, money in integer minor units.

---

## 9. Prioritized implementation roadmap

The detailed, continuously updated plan with status lives in [`IMPLEMENTATION_ROADMAP.md`](IMPLEMENTATION_ROADMAP.md). Priority order:

1. **P0 – Remove committed secrets, unsafe defaults** (C-01…C-03) and document credential rotation.
2. **P0 – New core with security primitives** (C-05, H-01…H-03, H-09, H-10, M-02, M-07).
3. **P0 – Authentication & RBAC** (C-04, H-05, H-06, M-01, M-06).
4. **P1 – Admin panel and settings** (replaces hard-coded `.env` business settings).
5. **P1 – Integrations** with encrypted credentials and SSRF guard (C-06, H-08, M-04).
6. **P1 – Payments** with verified webhooks and idempotency (C-07, C-08).
7. **P1 – Orders, provisioning queue, renewals** (H-07, M-09).
8. **P2 – Customer panel, tickets, legacy importer.**
9. **P2 – Tests, static analysis, documentation, final review.**
