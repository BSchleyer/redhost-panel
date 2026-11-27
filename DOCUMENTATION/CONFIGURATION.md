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
| `CONSOLE_LISTEN` | `127.0.0.1:8081` | Listen address of `console:proxy` (overridden by `--listen`; docker-compose uses `--listen=0.0.0.0:8081` inside the container and publishes it on 127.0.0.1 only). |
| `CONSOLE_MAX_CONNECTIONS` / `CONSOLE_MAX_LIFETIME` | `100` / `14400` | Concurrent console connections and maximum session length in seconds. |
| `CONSOLE_MAX_PER_IP` / `CONSOLE_MAX_PER_USER` | `10` / `3` | Concurrent connections per client IP (uses `X-Forwarded-For` from `TRUSTED_PROXIES`) and open consoles per panel user; `0` = unlimited. |
| `CONSOLE_MEMORY_LIMIT` | `512M` | PHP memory limit of the long-running console proxy. |
| `CONSOLE_PORT` | `8081` | Host port of the console service in docker-compose (bound to 127.0.0.1). |
| `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD` | – | Optional, only for `legacy:import` when the RED-Host database is a separate database. |
| `INVOICE_ARCHIVE_PATH` | `storage/invoices` | Archive of issued invoice documents (PDF and XRechnung XML). Must be writable by web, worker and scheduler, persistent (docker-compose: volume `invoices`) and included in backups – see [DATABASE.md](DATABASE.md#invoice-archive). |

### Key rotation

1. Generate a new key: `php bin/nebrix key:generate`.
2. Set `APP_PREVIOUS_KEYS` to the current key and `APP_KEY` to the new one. Restart web, worker and scheduler.
3. Values are decrypted with any configured key; new and updated values are encrypted with the new key. Re-save secrets in the admin panel (SMTP password, payment keys, integration tokens) to re-encrypt them, then remove the old key once nothing depends on it. Customer 2FA secrets and service credentials keep working as long as the old key stays in `APP_PREVIOUS_KEYS`.

## Admin settings (database)

Admin → Settings groups the following settings. All changes are audit-logged (keys only, never values).

### General
Company name, address, contact/support e-mail, phone, VAT ID, website, legal URLs (terms, privacy, imprint), default and available languages (English, German), default currency, time zone, date and time format.

Language: signed-in users see their profile language; visitors who are not signed in see their browser's preferred language (`Accept-Language`) if it is one of the available languages, otherwise the default language. New accounts keep the language they registered in, and receipts for declarations of guests are sent in the language of the form. Texts stored on invoices (line descriptions) are written in the customer's language.

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
| Number prefix for cancellation and correction documents (`billing.credit_note_prefix`) | `RK-` – must not be empty or equal to the invoice prefix (both share one number column) |
| Pay renewal invoices automatically from account credit (`billing.auto_pay_credit`) | on – applies to customers whose profile option is on: new customers start with it on, customers who existed before the update with it off; never for cancelled or ended services. Needs the payment method "account credit" |
| Payment reminders, days relative to the due date (`billing.reminder_offsets`) | `-3,1` (3 days before and 1 day after); empty = no reminders |

The tax rate of a product is its domestic rate; which rate (or 0 %) an invoice really carries is decided by the tax settings below.

### Taxes (Settings → General)
| Setting | Default |
|---|---|
| Country of the business (`tax.home_country`) | `DE` |
| Small business, § 19 UStG (`tax.small_business`) + invoice note | off – invoices show 0 % VAT, category E and the note. **A sole proprietorship (Einzelunternehmen) is not automatically a small business**; switch this on only if your tax advisor confirms the § 19 UStG status. |
| Reverse charge for EU business customers (`tax.reverse_charge`) + invoice note | on – applies only to customers in another EU country whose VAT ID was confirmed by VIES; otherwise VAT is charged. |
| OSS (`tax.oss`) | off – when on, EU private customers pay the standard rate of their country (built-in table). Switch on once your EU-wide B2C distance sales exceed 10,000 EUR per year or you opted in to OSS. |
| Standard rate overrides (`tax.rate_overrides`) | empty – one line per country, e.g. `EE=2400` (basis points). The built-in rates were current in November 2026; correct any later change here. |
| No VAT outside the EU (`tax.non_eu_exempt`) + invoice note | off – when on, customers in third countries are invoiced without VAT (category O). Check registration duties in the customer's country. |

VAT IDs are checked against the EU VIES service when a customer saves the profile (or staff edits it) and re-checked by the scheduler (`tax.recheck_vat_ids`, hourly in small batches with a pause between requests; each VAT ID every 30 days). Reverse charge additionally needs a company name that matches the holder name VIES reports (where the member state discloses it). While VIES is unavailable, an earlier result for the same VAT ID is kept. Renewal invoices use the customer's current status. All tax decisions are a technical implementation – have them confirmed by a tax advisor before going live.

### Invoice details (Settings → General)
| Setting | Purpose |
|---|---|
| Seller street, postcode, city | Address on invoices (company name, e-mail, phone and VAT ID come from General). |
| VAT ID / tax number (`invoice.seller_vat_id`, `invoice.seller_tax_number`) | At least one is required on invoices (§ 14 UStG); small businesses without VAT ID use the tax number. |
| Contact person (`invoice.contact_name`) | Required for XRechnung (BR-DE-5) together with the contact e-mail and phone. |
| Account holder, IBAN, BIC | Bank transfer details on PDF and XRechnung (payment means 58). |
| Invoice footer | Commercial register, managing directors, court. |
| Attach e-invoices for business customers (`invoice.e_invoice_b2b`) | on – customers with a company or VAT ID also receive the XRechnung XML. |

Since 1 January 2025 German businesses must be able to receive e-invoices; issuing them for domestic B2B becomes mandatory step by step until 2028. The XML follows XRechnung 3.0 (UN/CEFACT CII).

### Branding
Logo and favicon upload (PNG, JPEG, WebP, ICO; max 1 MB), primary/secondary/accent colours, default theme (system/light/dark), login headline and subtitle, dashboard notice, e-mail header colour and footer. Colours are delivered as `/branding.css` so the strict Content Security Policy stays intact.

### Mail (SMTP)
Enable SMTP, host, port, encryption (STARTTLS / SMTPS / none), username, password (encrypted), sender address and name. While SMTP is disabled, e-mails are written to the application log. Use "Send test e-mail" after saving. E-mails are sent by the queue worker, so the worker must run.

E-mail texts are edited in Admin → Mail templates (subject, HTML and plain-text body with `{{ variable }}` placeholders; preview and reset to default available). Every template exists in English and German; customers receive the version in their account language (guests: the default language), other languages fall back to English.

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

### REST API
| Setting | Default |
|---|---|
| Enable the REST API (`api.enabled`) | **off** – `/api/v1` answers `404 api_disabled` |
| Customers may create API tokens (`api.customer_tokens`) | on – when off (or while customer logins are disabled), only staff tokens work |
| Requests per minute and user (`api.rate_limit`) | 120 (10–6000), shared by all tokens of an account |

See [API.md](API.md#rest-api-v1) for tokens, scopes and endpoints.

## Scheduler tasks

`php bin/nebrix schedule:run` (every minute) runs these tasks when due:

| Task | Interval | Action |
|---|---|---|
| `queue.release_stale` | 5 min | Returns jobs whose worker died to the queue. |
| `billing.renewal_invoices` | 15 min | Creates renewal invoices for services due within the configured notice period. |
| `billing.auto_pay_credit` | 15 min | Pays renewal invoices due within one day from account credit (customers with the option on; not for cancelled or ended services). |
| `billing.payment_reminders` | 60 min | Sends payment reminders according to `billing.reminder_offsets` (each offset at most once per invoice; not for cancelled or ended services). |
| `billing.suspend_overdue` | 15 min | Suspends active services whose renewal invoice is unpaid after the grace period. |
| `billing.terminate_suspended` | 60 min | Terminates long-suspended services – only if "terminate after" is > 0. |
| `services.cancellations` | 60 min | Terminates services whose cancellation date has been reached and whose declaration is still scheduled. |
| `billing.cancel_stale_orders` | 60 min | Cancels unpaid orders/invoices of new orders after 14 days and releases stock. |
| `tax.recheck_vat_ids` | 60 min | Re-checks up to 20 VAT IDs that are older than 30 days or were never checked successfully. |
| `integrations.health` | 30 min | Runs the connection test of every enabled integration. |
| `maintenance.cleanup` | daily | Deletes expired sessions, tokens, rate-limit entries, old login history and integration logs according to the retention settings. |

Concurrent scheduler runs are prevented with a database lock.
