<?php

function env($key, $default = null)
{
    $var = getenv($key);
    if(isset($var)){
        return $var;
    }

    return $default;
}

if (!function_exists('dd')) {
    function dd()
    {
        array_map(function($x) {
            dump($x);
        }, func_get_args());
        die;
    }
}

function format_number($number){
    return floor($number * 100) / 100;
}

function sendPush($title, $message, $priority = 0, $user_key = null, $url = null, $url_title = null)
{

    if($user_key == null){
        $user_key = env('PUSHOVER_USER_KEY');
    }

    if($priority == 2){
        curl_setopt_array($ch = curl_init(), array(
            CURLOPT_URL => "https://api.pushover.net/1/messages.json",
            CURLOPT_POSTFIELDS => array(
                "token" => env('PUSHOVER_APP_KEY'),
                "user" => $user_key,
                "title" => $title,
                "url" => $url,
                "url_title" => $url_title,
                "message" => $message,
                "priority" => $priority,
                "retry" => '60',
                "expire" => '3600',
            ),
            CURLOPT_SAFE_UPLOAD => true,
            CURLOPT_RETURNTRANSFER => true,
        ));
    } else {
        curl_setopt_array($ch = curl_init(), array(
            CURLOPT_URL => "https://api.pushover.net/1/messages.json",
            CURLOPT_POSTFIELDS => array(
                "token" => env('PUSHOVER_APP_KEY'),
                "user" => $user_key,
                "title" => $title,
                "url" => $url,
                "url_title" => $url_title,
                "message" => $message,
                "priority" => $priority,
            ),
            CURLOPT_SAFE_UPLOAD => true,
            CURLOPT_RETURNTRANSFER => true,
        ));
    }
    curl_exec($ch);
    curl_close($ch);
}