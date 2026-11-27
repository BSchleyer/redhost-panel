<?php

declare(strict_types=1);

/*
 * Nebrix front controller. The web server's document root must point to
 * this "public" directory; everything else (configuration, source, storage)
 * lives outside the document root.
 */

use Nebrix\Core\Application;
use Nebrix\Http\Request;

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$app = Application::create($basePath);
$request = Request::fromGlobals($app->config()->array('app.trusted_proxies'));
$app->handle($request)->send();
