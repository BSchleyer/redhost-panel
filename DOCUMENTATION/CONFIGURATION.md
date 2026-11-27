# Configuration

Configuration is split into two layers:

1. **Infrastructure settings** in `.env` – read at start-up, changed by the server operator.
2. **Business settings** in the database – changed in the admin panel (Admin → Settings, Payments, Mail templates, Integrations). Secret values are encrypted with `APP_KEY`.

## Environment variables (`.env`)

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `Nebrix` | Fallback name (the company name in the admin settings takes precedence in the UI). |
| `APP_ENV` | `production` | `production`, `staging` or `local`. |
| `APP_DEBUG` | `false` | Shows exception details only when `APP_ENV=local` **and** `APP_DEBUG=true`. Never enable in production. |
| `APP_URL` | – | Public base URL, e.g. `https://panel.example.com`. Used for links in e-mails, payment return URLs, Origin checks and the secure cookie default. |
| `APP_KEY` | – | 32-byte key (`base64:…`) generated with `php bin/nebrix key:generate`. Required. Back it up. |
| `APP_PREVIOUS_KEYS` | – | Comma-separated old keys that may still decrypt data after a rotation. |
| `APP_HSTS` | `true` | Sends `Strict-Transport-Security` on HTTPS requests. |
| `TRUSTED_PROXIES` | – | Comma-separated IPs/CIDRs of reverse proxies. Only these may set `X-Forwarded-For/Proto/Host`. |
| `APP_PORT` | `8080` | Host port used by `docker-compose.yml`. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SOCKET` | – | Database connection. `DB_SOCKET` takes precedence over host/port. |
| `SESSION_IDLE_MINUTES` | `60` | Sign-out after inactivity. |
| `SESSION_LIFETIME_HOURS` | `12` | Absolute session lifetime. |
| `SESSION_SECURE_COOKIE` | `true` if `APP_URL` is HTTPS | Marks the session cookie `Secure` (and uses the `__Host-` prefix). |
| `LOG_PATH` | `storage/logs/nebrix.log` | Log file, or `php://stderr` in containers. |
| `LOG_LEVEL` | `info` | `debug`, `info`, `notice`, `warning`, `error`. |
| `LOG_RETENTION_DAYS` | `30` | Rotated daily log files to keep. |
| `INTEGRATIONS_ALLOW_HTTP` | `false` | Allow plain-HTTP integration endpoints (lab setups only). |
| `INTEGRATIONS_ALLOW_LOOPBACK` | `false` | Allow integrations on `127.0.0.0/8`/`::1` (same host). |
| `INTEGRATIONS_TIMEOUT` | `20` | Timeout in seconds for integration API requests. |
| `CONSOLE_WS_URL` | `APP_URL` with `ws(s)://` + `/console/ws` | Public WebSocket URL of the console proxy. |
| `CONSOLE_LISTEN` | `0.0.0.0:8081` | Listen address of `console:proxy` (overridden by `--listen`). |
| `CONSOLE_MAX_CONNECTIONS` / `CONSOLE_MAX_LIFETIME` | `100` / `14400` | Concurrent console connections and maximum session length in seconds. |
| `CONSOLE_PORT` | `8081` | Host port of the console service in docker-compose (bound to 127.0.0.1). |
| `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD` | – | Optional, only for `legacy:import` when the RED-Host database is a separate database. |

### Key rotation

1. Generate a new key: `php bin/nebrix key:generate`.
2. Set `APP_PREVIOUS_KEYS` to the current key and `APP_KEY` to the new one. Restart web, worker and scheduler.
3. Values are decrypted with any configured key; new and updated values are encrypted with the new key. Re-save secrets in the admin panel (SMTP password, payment keys, integration tokens) to re-encrypt them, then remove the old key once nothing depends on it. Customer 2FA secrets and service credentials keep working as long as the old key stays in `APP_PREVIOUS_KEYS`.

## Admin settings (database)

Admin → Settings groups the following settings. All changes are audit-logged (keys only, never values).

### General
Company name, address, contact/support e-mail, phone, VAT ID, website, legal URLs (terms, privacy, imprint), default and available languages (English, German), default currency, time zone, date and time format.

### Authentication
| Setting | Default |
|---|---|
| Allow new customer registrations | on |
| Allow customer logins (staff can always sign in) | on |
| Require e-mail verification | on |
| Require acceptance of terms/privacy at registration | on |
| Require two-factor authentication for staff | off – **recommended: on** |
| Failed logins before lockout / lockout duration | 5 / 15 minutes |
| CAPTCHA on registration and password reset (hCaptcha or Cloudflare Turnstile, site key, secret key) | off |

With a CAPTCHA provider the widget script of the provider is loaded on the registration and password reset pages only (the Content Security Policy is extended for these pages). Tokens are verified server-side; if the provider cannot be reached the form is rejected.

### Billing
| Setting | Default |
|---|---|
| Invoice / order number prefix | `NX-` / `ORD-` |
| Invoice payment term | 7 days |
| Create renewal invoices … days before due date | 7 |
| Suspend unpaid services … days after due date | 1 |
| Terminate suspended services after … days | 0 (never automatically) |
| Supported currencies | EUR |

Tax rates are defined per product (percentage, applied on top of the net price) – see ADMIN_GUIDE.md.

### Branding
Logo and favicon upload (PNG, JPEG, WebP, ICO; max 1 MB), primary/secondary/accent colours, default theme (system/light/dark), login headline and subtitle, dashboard notice, e-mail header colour and footer. Colours are delivered as `/branding.css` so the strict Content Security Policy stays intact.

### Mail (SMTP)
Enable SMTP, host, port, encryption (STARTTLS / SMTPS / none), username, password (encrypted), sender address and name. While SMTP is disabled, e-mails are written to the application log. Use "Send test e-mail" after saving. E-mails are sent by the queue worker, so the worker must run.

E-mail texts are edited in Admin → Mail templates (subject, HTML and plain-text body with `{{ variable }}` placeholders; one version per template, preview and reset to default available).

### Payments
Configured in Admin → Payments: PayPal (mode sandbox/live, client ID, client secret, webhook ID), Stripe (mode test/live, publishable key, secret key, webhook signing secret) and account credit. See [PAYMENTS.md](PAYMENTS.md).

### System
| Setting | Default |
|---|---|
| Maintenance mode / message | off – customers see the message; staff with admin access can still sign in |
| Verbose (debug) logging | off |
| Integration log retention | 30 days |
| Login history retention | 180 days |
| Enable support tickets | on |

## Scheduler tasks

`php bin/nebrix schedule:run` (every minute) runs these tasks when due:

| Task | Interval | Action |
|---|---|---|
| `queue.release_stale` | 5 min | Returns jobs whose worker died to the queue. |
| `billing.renewal_invoices` | 15 min | Creates renewal invoices for services due within the configured notice period. |
| `billing.suspend_overdue` | 15 min | Suspends active services whose renewal invoice is unpaid after the grace period. |
| `billing.terminate_suspended` | 60 min | Terminates long-suspended services – only if "terminate after" is > 0. |
| `billing.cancel_stale_orders` | 60 min | Cancels unpaid orders/invoices of new orders after 14 days and releases stock. |
| `integrations.health` | 30 min | Runs the connection test of every enabled integration. |
| `maintenance.cleanup` | daily | Deletes expired sessions, tokens, rate-limit entries, old login history and integration logs according to the retention settings. |

Concurrent scheduler runs are prevented with a database lock.
