<?php

declare(strict_types=1);

use Nebrix\Core\Env;

/*
 * Infrastructure configuration read from the environment (.env).
 * Business settings (company, branding, payments, SMTP, …) are managed in
 * the admin panel and stored in the database – see config/settings.php.
 */

$basePath = dirname(__DIR__);
$appUrl = rtrim(Env::get('APP_URL', 'http://localhost:8080') ?? '', '/');
$env = Env::get('APP_ENV', 'production') ?? 'production';

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Nebrix'),
        'env' => $env,
        // Debug output is only ever shown when APP_ENV=local as well.
        'debug' => Env::bool('APP_DEBUG', false) && $env === 'local',
        'url' => $appUrl,
        'key' => Env::get('APP_KEY', ''),
        'previous_keys' => array_values(array_filter(array_map('trim', explode(',', Env::get('APP_PREVIOUS_KEYS', '') ?? '')))),
        'hsts' => Env::bool('APP_HSTS', true),
        'version' => '2.0.0-dev',
        'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', Env::get('TRUSTED_PROXIES', '') ?? '')))),
    ],

    'database' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::int('DB_PORT', 3306),
        'database' => Env::get('DB_DATABASE', 'nebrix'),
        'username' => Env::get('DB_USERNAME', 'nebrix'),
        'password' => Env::get('DB_PASSWORD', ''),
        'socket' => Env::get('DB_SOCKET', ''),
    ],

    'session' => [
        'idle_minutes' => Env::int('SESSION_IDLE_MINUTES', 60),
        'lifetime_hours' => Env::int('SESSION_LIFETIME_HOURS', 12),
        'secure_cookie' => Env::bool('SESSION_SECURE_COOKIE', str_starts_with($appUrl, 'https://')),
    ],

    'log' => [
        'path' => Env::get('LOG_PATH', $basePath . '/storage/logs/nebrix.log'),
        'level' => Env::get('LOG_LEVEL', 'info'),
        'retention_days' => Env::int('LOG_RETENTION_DAYS', 30),
    ],

    'console' => [
        // Public WebSocket URL of the console proxy (bin/nebrix console:proxy behind the reverse proxy).
        'ws_url' => Env::get('CONSOLE_WS_URL', (str_starts_with($appUrl, 'https://') ? 'wss://' . substr($appUrl, 8) : 'ws://' . substr($appUrl, 7)) . '/console/ws'),
        // Loopback by default; the reverse proxy forwards to it (Docker: --listen=0.0.0.0:8081 inside the container).
        'listen' => Env::get('CONSOLE_LISTEN', '127.0.0.1:8081'),
        'max_connections' => Env::int('CONSOLE_MAX_CONNECTIONS', 100),
        // Concurrent connections per client IP (X-Forwarded-For from TRUSTED_PROXIES) and open consoles per user; 0 = unlimited.
        'max_per_ip' => Env::int('CONSOLE_MAX_PER_IP', 10),
        'max_per_user' => Env::int('CONSOLE_MAX_PER_USER', 3),
        'max_lifetime' => Env::int('CONSOLE_MAX_LIFETIME', 14400),
        'memory_limit' => Env::get('CONSOLE_MEMORY_LIMIT', '512M'),
    ],

    'integrations' => [
        // Allow plain-HTTP integration endpoints (lab setups only).
        'allow_http' => Env::bool('INTEGRATIONS_ALLOW_HTTP', false),
        // Allow loopback addresses (integration running on the same host).
        'allow_loopback' => Env::bool('INTEGRATIONS_ALLOW_LOOPBACK', false),
        'timeout' => Env::int('INTEGRATIONS_TIMEOUT', 20),
    ],

    'paths' => [
        'base' => $basePath,
        'views' => $basePath . '/resources/views',
        'lang' => $basePath . '/resources/lang',
        'migrations' => $basePath . '/database/migrations',
        'cache' => $basePath . '/storage/cache',
        'public' => $basePath . '/public',
        'uploads' => $basePath . '/public/uploads',
        // Archive of issued invoice documents (PDF/XML) – keep for the statutory retention period and back it up.
        'invoices' => Env::get('INVOICE_ARCHIVE_PATH', $basePath . '/storage/invoices'),
    ],
];
