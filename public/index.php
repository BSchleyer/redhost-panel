<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2024 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel (master)
 *  * @file        index.php
 *  * @author      BSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        24.3.2024
 *  * @time        13:12
 *
 */

// error handling
#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(E_ALL);
error_reporting(E_ALL ^ E_WARNING && E_NOTICE);

// include system, kernel, autoload and other requirements
include_once '../system.php';
include_once BASE_PATH . 'vendor/autoload.php';
include_once BASE_PATH . 'software/Kernel.php';
include_once BASE_PATH . 'software/backend/autoload.php';

// define system end with start
define('SYSTEM_END', round(microtime(true) - SYSTEM_START, 4));

// include router
include_once BASE_PATH . 'router/app.php';