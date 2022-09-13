<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2022 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel
 *  * @file        index.php
 *  * @author      BjörnSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        16.8.2022
 *  * @time        23:11
 *
 */

// error_reporting
error_reporting(E_ALL ^ E_WARNING && E_NOTICE);

/*
 * for debugging all errors
 *
error_reporting(-1); // reports all errors
ini_set("display_errors", "1"); // shows all errors
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error.log");
*/

include_once '../system.php';
include_once BASE_PATH . 'vendor/autoload.php';
include_once BASE_PATH . 'software/Kernel.php';
include_once BASE_PATH . 'software/backend/autoload.php';

// define system end
define('SYSTEM_END', round(microtime(true) - SYSTEM_START, 4));

// include router
include_once BASE_PATH . 'route/index.php';