# Database

Nebrix stores all state in one MariaDB/MySQL database (InnoDB, `utf8mb4_unicode_ci`, strict SQL mode). All tables use the prefix `nx_` so they can coexist with the tables of a RED-Host installation in the same database; the migrations never read, modify or drop tables without that prefix.

## Conventions

* Primary keys: `BIGINT UNSIGNED AUTO_INCREMENT` (`id`).
* Times: `DATETIME` in **UTC**; converted to the configured time zone only for display.
* Money: `BIGINT` in **minor units** (cents) plus an ISO-4217 `currency` column. Tax rates in basis points (`tax_rate_bp`, 1900 = 19 %).
* Secrets (`*_encrypted`, `credentials`, `secret` columns): libsodium ciphertext bound to the column/row context – unreadable without `APP_KEY`.
* Tokens (session IDs, password reset, e-mail verification, API tokens): only SHA-256 hashes are stored.
* JSON documents (`config`, `remote`, `payload`, `metadata`): `JSON`/`LONGTEXT` validated by the application.
* Foreign keys with `ON DELETE RESTRICT` for financial data and `SET NULL` for optional actor references.

## Migrations

```bash
php bin/nebrix migrate:status
```

```bash
php bin/nebrix migrate --pretend
```

```bash
php bin/nebrix migrate
```

* Files live in `database/migrations/` and are applied in file-name order. Applied migrations are recorded in `nx_migrations` (with a batch number).
* `migrate` takes a database lock (`GET_LOCK`), so two deployments cannot migrate concurrently.
* Migrations are **forward-only**: there are no down migrations, because DDL is not transactional in MySQL/MariaDB and a rollback could destroy data. Always take a backup before migrating (see below).
* Migrations use `CREATE TABLE IF NOT EXISTS` / column existence checks and can be re-run safely.

| Migration | Content |
|---|---|
| `000001_create_core_tables` | roles, role permissions (seeded with the default roles and permissions), customer groups, users, user tokens, sessions, rate limits, login history, audit log, settings, mail templates |
| `000002_create_catalog_and_billing_tables` | integrations, integration logs, products, orders, order items, services, invoices, invoice items, transactions, payment events, webhook events, credit ledger |
| `000003_create_jobs_and_support_tables` | jobs, scheduler runs, tickets, ticket messages |
| `000004_add_two_factor_replay_protection` | `nx_users.two_factor_last_slice` |
| `000005_create_sequences_table` | gap-free counters for invoice and order numbers |
| `000006_create_infrastructure_tables` | IP pools and addresses, console sessions, registrar contacts, legacy import mapping and history |

## Tables

### Identity and access
| Table | Purpose |
|---|---|
| `nx_roles` | Roles (`super_admin`, `admin`, `support`, `finance`, `customer`, custom staff roles). `is_system` roles cannot be deleted. |
| `nx_role_permissions` | Permission strings per role (catalog in `src/Authorization/Permission.php`). |
| `nx_customer_groups` | Discount (`discount_bp`, basis points) and the switches `can_order` / `can_manage_services`. |
| `nx_users` | Customers and staff. Unique `email`, `username`, `legacy_id`. Status `pending`, `active`, `suspended`, `closed`. Argon2id hash, encrypted 2FA secret, hashed recovery codes, credit balance (minor units), `auto_pay_credit` (renewals from credit, default on), VAT ID with VIES result (`vat_id_valid`: 1 valid, 0 invalid, `NULL` not checked; `vat_id_checked_at`, `vat_id_name`). |
| `nx_user_tokens` | Hashed single-use tokens (password reset, e-mail verification) with expiry. |
| `nx_sessions` | Server-side sessions (hashed ID, user, IP, user agent, payload, last activity). |
| `nx_rate_limits` | Throttle counters (hashed key, hits, reset time). |
| `nx_login_history` | Successful and failed sign-ins (pruned after the retention period). |
| `nx_audit_log` | Append-only record of administrative and security-relevant actions. |
| `nx_api_tokens` | REST API personal access tokens: owner, name, SHA-256 hash (unique) and 8-character prefix of the token, scopes (JSON), optional IP allowlist, expiry, last use, revocation time. |
| `nx_api_idempotency` | Stored first responses of `POST` requests with an `Idempotency-Key` per token (key and request as SHA-256); pruned after 24 hours. |
| `nx_settings` | Admin settings (`key` → value). Secret settings are stored encrypted with the context `setting:<key>`. |
| `nx_mail_templates` | Customised e-mail templates per language, primary key (`key`, `locale`) (defaults live in `config/mail_templates.php` and `config/mail_templates_de.php`). |

### Catalog, orders and billing
| Table | Purpose |
|---|---|
| `nx_integrations` | Remote systems (type, name, encrypted credentials, health status). |
| `nx_integration_logs` | Request log per integration (method, path, status, duration; no secrets). |
| `nx_products` | Products with category, price and setup fee, billing cycle, tax rate, stock (`NULL` = unlimited), visibility, optional customer-group restriction, integration and provisioning configuration (JSON). |
| `nx_orders`, `nx_order_items` | Orders with price snapshot (the product can change later without affecting existing orders). |
| `nx_services` | One row per provisioned item: status (`pending`, `provisioning`, `active`, `suspended`, `terminated`, `cancelled`, `failed`), billing cycle, `next_due_at`, `cancel_at` (end date after a cancellation – no renewals after it), remote identifiers (`external_id`, `remote` JSON), encrypted `credentials`. `order_item_id` is unique → one service per order item even if fulfilment runs twice. |
| `nx_invoices`, `nx_invoice_items` | Invoices of type `order`, `renewal` or `credit` (top-up) with status `unpaid`, `paid`, `cancelled` or `refunded`, and correction documents of type `credit_note` (status `issued`, `reference_invoice_id` → corrected invoice). Tax treatment per invoice (`tax_category` S/AE/E/O, `tax_note`, `tax_country`), billing snapshot, reminder tracking (`reminders_sent` = number of reminder levels sent, `last_reminder_at`) and the archive record (`archived_at`, `pdf_sha256`, `xml_sha256`). Partial refunds are tracked through the refund transactions. |
| `nx_cancellations` | Cancellations and withdrawals (`kind` ordinary/extraordinary/withdrawal) with reference, receipt time, the data entered (name, e-mail, customer reference, contract, reason, requested date), IP, linked user/service when known, status (`received`, `scheduled`, `completed`, `rejected`, `withdrawn`), effective date and staff processing. |
| `nx_transactions` | Payments and refunds. Unique `(gateway, type, reference)` → a provider payment/refund is recorded only once. |
| `nx_payment_events` | Immutable history of payment processing (e.g. `payment.completed`, `payment.failed`, `payment.amount_mismatch`, `payment.overpayment`, `payment.duplicate_ignored`, `payment.unknown_invoice`, `refund.completed`, `refund.invalid_amount`) with the raw provider data. |
| `nx_webhook_events` | Received webhooks; unique `(gateway, event_id)` for deduplication; processing status. |
| `nx_credit_ledger` | Every change of a customer's credit balance with the resulting balance, reason, reference and actor. |
| `nx_sequences` | Counters for invoice/order numbers (incremented inside the transaction with a row lock). |

### Infrastructure
| Table | Purpose |
|---|---|
| `nx_ip_pools`, `nx_ip_addresses` | IPv4 pools and their addresses (`free`, `assigned`, `reserved`); unique `(pool_id, address)`. |
| `nx_console_sessions` | Single-use console tokens (SHA-256 hash), encrypted upstream target, expiry, use time; pruned after a day. |
| `nx_registrar_contacts` | Registrant contacts created at the registrar per customer and contact data hash. |
| `nx_legacy_map` | Imported RED-Host records → Nebrix entities (idempotent imports). |
| `nx_legacy_records` | Read-only payment and balance history from RED-Host. |

### Background processing and support
| Table | Purpose |
|---|---|
| `nx_jobs` | Job queue: type, JSON payload (e-mail contents are encrypted inside the payload and removed after delivery), status, attempts, `available_at`, `reserved_at`, `last_error`; unique `unique_key` for idempotent dispatch. |
| `nx_scheduler_runs` | Last run time, duration and result per scheduler task. |
| `nx_tickets`, `nx_ticket_messages` | Support tickets (optionally linked to one of the customer's services) and their messages (`is_staff` marks staff replies). |
| `nx_migrations` | Applied migrations. |

## Data retention

The daily `maintenance.cleanup` task deletes expired sessions, used/expired tokens, expired rate-limit counters, login history older than the configured retention (default 180 days), integration logs older than the configured retention (default 30 days), completed jobs older than 30 days, console sessions older than a day and API idempotency records older than 24 hours. Revoked and expired API tokens are kept (they are listed for the audit trail). Financial records (invoices, transactions, payment events, credit ledger) and the audit log are never deleted by the application.

Customer accounts are never hard-deleted because invoices and payments reference them. For erasure requests use Admin → Customers → *Close and anonymize* (permission `customers.delete`), which closes the account, ends its sessions and replaces personal data with placeholders while keeping the financial records required by statutory retention periods.

## Invoice archive

Issued invoice documents are generated once and stored unchanged in `storage/invoices/<year>/<number>-<id>.pdf` and `.xml` (`INVOICE_ARCHIVE_PATH`, Docker volume `invoices`). The SHA-256 of each file is stored on the invoice; a file whose hash no longer matches is refused instead of being served or silently regenerated. Under German law (§ 14b UStG, § 147 AO) invoices must be kept for 8 years (accounting records 10 years) in an unalterable form – keep the archive on durable storage, include it in every backup and do not delete or edit files in it.

## Backups and restore

Back up the database **and** `APP_KEY` (separately) – encrypted columns are useless without the key. Also back up `public/uploads/` (logo/favicon) and the invoice archive (`storage/invoices`, see above).

```bash
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 nebrix | gzip > nebrix-$(date +%F).sql.gz
```

```bash
gunzip -c nebrix-2026-11-27.sql.gz | mysql nebrix
```

Test restores regularly in a non-production environment.

## Optional hardening: append-only triggers

The application has no code path that updates or deletes payment events, audit log entries or credit ledger rows. If your database user may create triggers, enforce this in the database as well:

```bash
php bin/nebrix db:triggers --install
```

`db:triggers` shows the state, `--remove` removes them (e.g. before restoring a backup). The command creates these statements (shown for two tables):

```sql
CREATE TRIGGER nx_payment_events_no_update BEFORE UPDATE ON nx_payment_events FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'nx_payment_events is append-only';
CREATE TRIGGER nx_payment_events_no_delete BEFORE DELETE ON nx_payment_events FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'nx_payment_events is append-only';
CREATE TRIGGER nx_audit_log_no_update BEFORE UPDATE ON nx_audit_log FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'nx_audit_log is append-only';
CREATE TRIGGER nx_audit_log_no_delete BEFORE DELETE ON nx_audit_log FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'nx_audit_log is append-only';
```

Additionally the web application's database user can be restricted to `SELECT, INSERT, UPDATE, DELETE` while migrations run with a separate user that has DDL privileges.
