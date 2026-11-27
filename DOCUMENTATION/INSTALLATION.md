# Installation

Nebrix runs as a PHP web application plus two CLI processes (queue worker and scheduler) on a MariaDB/MySQL database. You can install it with Docker Compose or directly on a server.

> Upgrading an existing RED-Host installation? Read [UPGRADE_GUIDE.md](UPGRADE_GUIDE.md) first.

## Requirements

| Component | Version |
|---|---|
| PHP | 8.3 or 8.4 (CLI and web) |
| PHP extensions | `pdo_mysql`, `sodium`, `curl`, `mbstring`, `openssl`, `json`, `fileinfo`, `dom` (PDF and e-invoices); recommended: `opcache`, `intl`, `gd` |
| Database | MariaDB 10.6+ (tested with 11.4) or MySQL 8.0+ |
| Web server | Apache 2.4 with `mod_rewrite` (configuration included) or nginx + PHP-FPM |
| Composer | 2.x |
| TLS | Required in production (sessions use `Secure`/`__Host-` cookies on HTTPS) |

The document root **must** be the `public/` directory. Never expose the project root.

## Option A – Docker Compose

```bash
git clone https://github.com/Shvquu/nebrix-panel.git
```

```bash
cd nebrix-panel && cp .env.example .env
```

1. Edit `.env`: set `APP_URL`, `DB_DATABASE`, `DB_USERNAME` and a strong `DB_PASSWORD`.
2. Build the image and generate an application key:

```bash
docker compose build
```

```bash
docker compose run --rm --no-deps app php bin/nebrix key:generate
```

   Copy the printed value into `APP_KEY` in `.env` and **store a backup of it outside the server**. Without it, stored credentials (SMTP, payment keys, integration tokens, 2FA secrets) cannot be decrypted.

3. Start the stack and run the migrations:

```bash
docker compose up -d
```

```bash
docker compose exec app php bin/nebrix migrate
```

   Until the migrations have run, `worker` and `scheduler` log "table … doesn't exist" and restart; that is expected and stops once the schema exists.

4. Create the first super administrator:

```bash
docker compose exec app php bin/nebrix user:create-admin --email=admin@example.com
```

   The command asks for a password (at least 10 characters, checked against the password policy).

5. Put a TLS-terminating reverse proxy (nginx, Caddy, Traefik, …) in front of port `APP_PORT` (default 8080) and add the proxy's address to `TRUSTED_PROXIES`.

The image is built from `.docker/Dockerfile`; `.dockerignore` keeps `.env`, `.git`, local `vendor/`, tests, logs and runtime data out of the build context, so no secrets end up in the image. The compose file starts five services: `app` (Apache + PHP 8.4, runs as `www-data` on port 8080), `worker` (`queue:work`), `scheduler` (`schedule:run` every 60 seconds), `console` (WebSocket proxy for server consoles on `127.0.0.1:8081`) and `db` (MariaDB, not published to the host). Uploaded branding files are kept in the `uploads` volume, issued invoice documents in `invoices` (mounted into app, worker and scheduler – include it in your backups and keep it for the statutory retention period), the database in `db-data`.

Reverse proxy: route everything to port 8080 and the console WebSocket path (`CONSOLE_WS_URL`, default `/console/ws`) to port 8081 with WebSocket upgrade (the proxy's address as seen by the containers – usually the Docker gateway – belongs in `TRUSTED_PROXIES`), e.g. nginx:

```nginx
location /console/ws { proxy_pass http://127.0.0.1:8081; proxy_http_version 1.1; proxy_set_header Upgrade $http_upgrade; proxy_set_header Connection "upgrade"; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_read_timeout 4h; access_log off; }
location / { proxy_pass http://127.0.0.1:8080; proxy_set_header Host $host; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_set_header X-Forwarded-Proto https; }
```

## Option B – Manual installation

```bash
git clone https://github.com/Shvquu/nebrix-panel.git /var/www/nebrix
```

```bash
cd /var/www/nebrix && composer install --no-dev --optimize-autoloader --classmap-authoritative
```

```bash
cp .env.example .env && chmod 640 .env
```

1. Create a database and a user with privileges on that database only:

```sql
CREATE DATABASE nebrix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nebrix'@'localhost' IDENTIFIED BY '<strong password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, LOCK TABLES ON nebrix.* TO 'nebrix'@'localhost';
```

   `DROP` is only needed if you want `migrate` to be able to run future migrations that drop indexes; the current migrations do not drop tables.

2. Fill in `.env` (see [CONFIGURATION.md](CONFIGURATION.md)) and generate the key with `php bin/nebrix key:generate`.
3. Run `php bin/nebrix migrate` and `php bin/nebrix user:create-admin --email=…`.
4. File permissions – the web server and CLI user (e.g. `www-data`) needs write access only to `storage/` (including the invoice archive `storage/invoices`, or the directory in `INVOICE_ARCHIVE_PATH`) and `public/uploads/`:

```bash
chown -R root:www-data /var/www/nebrix && chown -R www-data:www-data storage public/uploads && chmod 640 .env
```

5. Web server:
   * **Apache**: use `.docker/apache/nebrix.conf` as a template (document root `public/`, PHP disabled in `public/uploads`). `public/.htaccess` routes all requests to `index.php`.
   * **nginx**: route everything that is not a file to `index.php` and deny PHP in uploads:

```nginx
root /var/www/nebrix/public;
index index.php;
location / { try_files $uri /index.php$is_args$args; }
location ^~ /uploads/ { location ~ \.(php|phtml|phar|html?|svg)$ { deny all; } }
location = /index.php { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $realpath_root/index.php; fastcgi_pass unix:/run/php/php8.4-fpm.sock; }
location ~ \.php$ { deny all; }
location ~ /\. { deny all; }
```

6. Background processes – run the worker under a process supervisor and the scheduler from cron:

```ini
# /etc/systemd/system/nebrix-worker.service
[Unit]
Description=Nebrix queue worker
After=network.target mariadb.service

[Service]
User=www-data
WorkingDirectory=/var/www/nebrix
ExecStart=/usr/bin/php bin/nebrix queue:work --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
echo '* * * * * www-data cd /var/www/nebrix && php bin/nebrix schedule:run >> /dev/null 2>&1' > /etc/cron.d/nebrix
```

   The worker exits after `--max-time` seconds (default 3600) so that code updates and memory are refreshed; the supervisor restarts it.

7. Console proxy (only needed for Proxmox consoles) – a second unit with `ExecStart=/usr/bin/php bin/nebrix console:proxy --listen=127.0.0.1:8081`, and the reverse proxy forwards `/console/ws` to it (see the nginx example under Option A).

8. Optional hardening: `php bin/nebrix db:triggers --install` creates triggers that reject changes to payment events, audit log and credit ledger (needs the `TRIGGER` privilege).

## After the installation

1. Sign in at `APP_URL/login` with the administrator account and enable two-factor authentication (Account → Security).
2. Admin → Settings: company data, branding, SMTP (send a test e-mail), billing defaults, payment providers.
3. Admin → Integrations: add Proxmox / Pterodactyl / Nextcloud / TeamSpeak connections and run the connection test.
4. Admin → Products: create products and link them to an integration.
5. Configure the payment webhooks (see [PAYMENTS.md](PAYMENTS.md)).
6. Check `GET /health` (returns `{"status":"ok"}` when the database is reachable) from your monitoring.

## Verifying an installation

```bash
php bin/nebrix migrate:status
```

```bash
php bin/nebrix integrations:check
```

```bash
php bin/nebrix schedule:run --force
```

Admin → System shows the PHP version, required extensions, pending migrations, queue state and the last scheduler runs.
