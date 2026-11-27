<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in development server only:
 *   php -S 127.0.0.1:8080 -t public bin/dev-router.php
 * Serves existing files from public/ directly and everything else through
 * the front controller. Never use the built-in server in production.
 */

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$file = realpath(__DIR__ . '/../public' . (is_string($path) ? $path : '/'));
$public = realpath(__DIR__ . '/../public');

if ($file !== false && $public !== false && str_starts_with($file, $public . DIRECTORY_SEPARATOR) && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}

require __DIR__ . '/../public/index.php';
