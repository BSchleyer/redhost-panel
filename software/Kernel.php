<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2022 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel
 *  * @file        Kernel.php
 *  * @author      BjörnSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        16.8.2022
 *  * @time        23:8
 *
 */

// start session
ob_start();
session_start();

// load dotenv
$dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

// env function
function env($key, $default = null)
{
    $var = getenv($key);
    if (isset($var)) {
        return $var;
    }

    return $default;
}

// debug function dd()
if (!function_exists('dd')) {
    function dd()
    {
        array_map(function ($x) {
            dump($x);
        }, func_get_args());

        die;
    }
}

// format number
function format_number($number)
{
    return floor($number * 100) / 100;
}

// set debug mode
if (env('debug', 'false') == 'true') {
    $whoops = new \Whoops\Run();
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
    $whoops->register();
}

// require database connection with dotenv
$dotenv->required(['DATABASE_HOST', 'DATABASE_NAME', 'DATABASE_USERNAME', 'DATABASE_PASSWORD']);

// sentry settings
\Sentry\init(['dsn' => '' /* set api key here */]);