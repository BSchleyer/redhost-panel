# Upgrade Guide

This guide covers the migration from the RED-Host panel (the PHP 7 application in this repository's history) to Nebrix 2.0, and future Nebrix updates.

Nebrix 2.0 is a **rewrite**. The legacy application is not updated in place; Nebrix is installed next to it and takes over the customer accounts. Read [AUDIT.md](AUDIT.md) for the reasons.

## Before you start

1. **Rotate all credentials** that were committed to the repository history (see AUDIT.md, findings C-01 to C-03): database password, legacy API/cron keys, Discord webhook, Pterodactyl API key, hCaptcha keys, PayPal account settings. Assume they are known to third parties.
2. **Back up** the legacy database and files. Nebrix never writes to legacy tables, but backups are mandatory before any migration.
3. Plan a maintenance window: during the switch, customers should not place orders in the old panel.
4. Inventory the legacy services (LXC/KVM, TeamSpeak, game servers, webspaces) and the systems they run on.

## What is migrated automatically

`php bin/nebrix legacy:import` imports from the legacy `users` table:

| Legacy | Nebrix |
|---|---|
| e-mail (normalised to lower case), username | e-mail, username (invalid or duplicate usernames are dropped; customers sign in with their e-mail) |
| password (bcrypt) | kept; upgraded to Argon2id at the next successful login. Other hash formats are not imported (the account is reported). |
| state `active` / `pending` / `banned` | `active` / `pending` / `suspended` |
| role `customer` | Customer |
| role `support` / `admin` | Support / **Administrator** (never super administrator), imported **suspended** – review and reactivate each staff account in Admin → Staff, and enable 2FA. |
| first/last name, street + number, postcode, city, country (names such as "Deutschland" → `DE`) | profile and billing address |
| `amount` (prepaid balance) | credit balance, booked in the credit ledger ("Balance imported from RED-Host") |
| `legal_accepted` | acceptance date |

The import is **idempotent** (matched by `nx_users.legacy_id`) and can be re-run; already imported users are skipped and balances are not booked twice. Accounts whose e-mail already exists in Nebrix or whose e-mail is invalid are reported as conflicts and skipped.

### Services, tickets and history (`--only=services,tickets,history`)

| Legacy | Nebrix |
|---|---|
| `vm_servers` (LXC/KVM on `vm_host_nodes`) | LXC / VPS services on the mapped Proxmox integration; node name and VMID (= legacy ID) recorded; root password imported **encrypted** |
| `teamspeaks` | TeamSpeak services on the mapped integration (SID and port) |
| `pterodactyl_servers` | game server services (server ID and identifier) |
| `webspace` | webhosting services on the mapped Plesk integration (subscription ID, domain, FTP access encrypted) |
| state active / suspended (`DISABLED`, `SUSPENDED`) | `active` / `suspended`; deleted and pending services are skipped |
| `price`, `days`, `expire_at` | recurring amount (as gross, tax rate 0), billing cycle (30 → monthly, 90 → quarterly, 180 → every 6 months, 365 → annually, other runtimes → monthly with a warning), next due date |
| `tickets`, `ticket_message` | tickets with messages (HTML removed, staff replies flagged); category as subject prefix; priority mapped |
| `transactions` (successful), `user_transactions` | read-only history on the customer page (`nx_legacy_records`); balances are **not** booked again (the user import already took over the balance) |

Hosts must be mapped explicitly to integrations you created first; unmapped hosts and Venocix servers are reported and skipped. Nothing is changed on the remote systems. Imported services are marked `legacy` so that the provisioners accept the recorded remote IDs (see INTEGRATIONS.md). Create products afterwards for new orders – imported services have no product.

## What is not migrated

* **Invoices**: the legacy panel had none (credit-based billing); renewals of imported services are invoiced by Nebrix from their next due date.
* **Logs, news, cashbox clicks, command presets, software installers**: not taken over.
* **Sessions, support PINs, API keys, cron keys**: obsolete; customers sign in again.
* **Venocix and paysafecard/Boltlayer** integrations: not part of Nebrix 2.0.

## Migration steps

1. Install Nebrix (see [INSTALLATION.md](INSTALLATION.md)) with a new `APP_KEY`. You can use the same database server; Nebrix creates only `nx_` tables.
2. Configure settings, mail, payments, integrations and products.
3. Point the importer at the legacy database. Same database: nothing to configure. Separate database: set `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD` in `.env` (a read-only user is sufficient).
4. Dry run and review the report:

```bash
php bin/nebrix legacy:import --dry-run
```

5. Import the accounts:

```bash
php bin/nebrix legacy:import
```

6. Create the integrations (Proxmox per legacy node/cluster, TeamSpeak per legacy host, Pterodactyl, Plesk), note their IDs (Admin → Integrations) and import services, tickets and history – dry run first. Example: legacy Proxmox nodes 1 and 2 belong to integration 3, TeamSpeak host 1 to integration 5:

```bash
php bin/nebrix legacy:import --only=services,tickets,history --proxmox-nodes=1:3,2:3 --teamspeak-hosts=1:5 --pterodactyl=4 --plesk=6 --dry-run
```

```bash
php bin/nebrix legacy:import --only=services,tickets,history --proxmox-nodes=1:3,2:3 --teamspeak-hosts=1:5 --pterodactyl=4 --plesk=6
```

7. Review imported staff accounts in Admin → Staff, reactivate those that should keep access and remove the `LEGACY_DB_*` variables.
8. Check the imported services (Admin → Services, live status), then switch DNS/web server to Nebrix and put the legacy panel offline (do not leave it reachable – it contains the vulnerabilities listed in AUDIT.md).
9. Inform customers: they can sign in with their e-mail and existing password; 2FA can be enabled under Account → Security.

## Updating Nebrix

1. Read [CHANGELOG.md](CHANGELOG.md) for the target version.
2. Back up the database (and keep `APP_KEY`).
3. Enable maintenance mode (Admin → Settings → System) or stop the web container.
4. Update the code and dependencies:

```bash
git pull
```

```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

   Docker: rebuild the image (`docker compose build`) and recreate the containers.

5. Run the migrations:

```bash
php bin/nebrix migrate
```

6. Restart the worker (it also stops itself after `--max-time`) and disable maintenance mode.
7. Check Admin → System for pending migrations and Admin → Jobs for failed jobs.

Migrations are forward-only; to roll back, restore the backup taken in step 2 and the previous code version.

## PHP versions

Nebrix 2.0 supports PHP 8.3 and 8.4 (the Docker image uses 8.4). `composer.json` pins the platform to PHP 8.3 so the lock file installs on both versions.
