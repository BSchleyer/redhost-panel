<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2022 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel
 *  * @file        Site.php
 *  * @author      BjörnSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        16.8.2022
 *  * @time        23:9
 *
 */

$site = new Site();
// set db function
$db = $helper->db();
class Site extends Controller {


    public function currentUrl() {
        $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

        return $actual_link;
    }

    public function getWelcomeText($time) {
        if($time >= 5 && $time <= 9){
            return '🌄 Guten Morgen';
        } elseif($time >= 9 && $time <= 12) {
            return '☀ Guten Vormittag';
        } elseif($time >= 12 && $time <= 16) {
            return '🌞 Guten Tag';
        } elseif($time >= 16 && $time <= 23) {
            return '🌇 Guten Abend';
        } elseif($time >= 23 || $time >= 0 && $time <= 5) {
            return '☕ Angenehme Nacht (#nachtaktiv)';
        } else {
            return 'Cloud not get time! Bitte melde das einem Supporter';
        }
    }

    public function validateCaptcha($h_captcha_response) {

        $data = array(
            'secret' => env('H_CAPTCHA_SECRET_KEY'),
            'response' => $h_captcha_response
        );

        $verify = curl_init();
        curl_setopt($verify, CURLOPT_URL, "https://hcaptcha.com/siteverify");
        curl_setopt($verify, CURLOPT_POST, true);
        curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($verify);
        // var_dump($response);
        $responseData = json_decode($response);

//        return $responseData;

        if($responseData->success) {
            // your success code goes here
            return true;
        } else {
            // return error to user; they did not pass
            return false;
        }
    }

    public function generateCSRF($length = 28) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

}