<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2022 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel
 *  * @file        autoload.php
 *  * @author      BjörnSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        16.8.2022
 *  * @time        23:8
 *
 */

include_once BASE_PATH . 'software/controller/Controller.php';

// search to backend file
foreach (glob('../software/backend/*.php') as $filename) {
    if ($filename != 'autoload.php') {
        include_once $filename;
    }
}