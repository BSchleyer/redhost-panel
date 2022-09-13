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
 *  * @time        23:7
 *
 */

$currPage = 'system_DebugSystem_hidelayout';
include BASE_PATH . 'software/controller/PageController.php';

/*
$files = array(
    'avatarImg' => BASE_PATH . 'public/assets/images/logos/old/logo_black.png',
    'tmp_name' => 'logo_black',
    'name' => 'logo_black'
);

$path = $files['avatarImg'];
$type = pathinfo($path, PATHINFO_EXTENSION);
$data = file_get_contents($path);
$base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);


$SQL = Helper::db()->prepare("UPDATE `settings` SET `logo_black` = :avatar");
dd($SQL -> execute(array(":avatar" => $base64)));*/
$ip = isset($_SERVER['HTTP_CLIENT_IP'])
    ? $_SERVER['HTTP_CLIENT_IP']
    : (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? $_SERVER['HTTP_X_FORWARDED_FOR']
        : $_SERVER['REMOTE_ADDR']);

dd($ip);

dd('Hmm... Du betreibst scheinbar Spionage... - finde ich nich gut... / Ich glaube, wir müssen die NSA über deinem Besuch informieren...');
