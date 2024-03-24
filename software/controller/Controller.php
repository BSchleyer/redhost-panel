<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2024 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel (master)
 *  * @file        Controller.php
 *  * @author      BSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        24.3.2024
 *  * @time        13:15
 *
 */

global $url;

abstract class Controller {

    // set siteName
    public static function siteName() {
        return env('APP_NAME');
    }

    // set db
    public static function db() {
        $db = new PDO('mysql:host=' . env('DATABASE_HOST') . ';charset=utf8;dbname=' . env('DATABASE_NAME'), env('DATABASE_USERNAME'), env('DATABASE_PASSWORD'));
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $db;
    }

    // set url
    public static function url() {
        return env('URL');
    }

    // set style url for homepage
    public static function styleUrl() {
        return env('STYLE_URL');
    }

    // set style url for dashboard
    public static function styleUrlDash() {
        return env('STYLE_URL_DASH');
    }

    // set image url
    public static function imageUrl() {
        return env('IMAGE_URL');
    }

    // get cookie domain
    public static function cookieDomain() {
        return env('COOKIE_DOMAIN');
    }

    // set nl2br2
    public static function nl2br2($string) {
        $string = str_replace(array("\r\n", "\r", "\n"), "<br />", $string);

        return $string;
    }

    // set cookie
    public static function setcookie($name, $variable, $time = '777600', $path = '/', $domain, $secure = 0) {
        setcookie($name, $variable, time()+$time, $path, $domain, $secure);
    }

}