<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2024 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel (master)
 *  * @file        autoload.php
 *  * @author      BSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        24.3.2024
 *  * @time        13:15
 *
 */

include_once BASE_PATH . 'software/controller/Controller.php';

foreach (glob('../software/backend/*.php') as $item) {
    if($item != 'autoload.php') {
        include_once $item;
    }
}