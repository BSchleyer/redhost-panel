<?php

declare(strict_types=1);

/*
 * Test bootstrap.
 *
 * Database-backed tests (tests/Integration, tests/Feature) need a dedicated,
 * empty MySQL/MariaDB database configured with TEST_DB_* environment
 * variables. The schema is dropped and re-created at the start of every run,
 * so the database name must contain "test" as a safety guard.
 *
 *   TEST_DB_HOST=127.0.0.1 TEST_DB_PORT=3306 TEST_DB_DATABASE=nebrix_test
 *   TEST_DB_USERNAME=nebrix TEST_DB_PASSWORD=secret vendor/bin/phpunit
 */

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('UTC');
