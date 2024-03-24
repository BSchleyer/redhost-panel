<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2024 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel (master)
 *  * @file        Globals.php
 *  * @author      BSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        24.3.2024
 *  * @time        13:14
 *
 */


function env($key, $default = null)
{
    $var = getenv($key);
    if (isset($var)) {
        return $var;
    }

    return $default;
}

if (!function_exists('dd')) {
    function dd()
    {
        array_map(function ($x) {
            dump($x);
        }, func_get_args());
        die;
    }
}

if (!function_exists('description')) {
    function description($text, $default = _SEO_DESCRIPTION)
    {
        if(isset($text)) {
            return $text;
        }

        return $default;
    }
}

//TODO: Implement automatic rounded price calculation
if(!function_exists('priceCalc')) {
    function priceCalc($price) {
        $price = number_format($price, 2);

        if ($price % 10 > 5) {
            $newPrice = floor($price / 10) * 10 + 9.95;
        } else {
            $newPrice = floor($price / 10) * 10 + 5.95;
        }

        return (float) $newPrice;
    }
}

function format_number($number)
{
    return floor($number * 100) / 100;
}