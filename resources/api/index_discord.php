<?php

error_reporting('E_STRICT');
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=utf-8');

$currPage = 'system_GlobalAPI';
include BASE_PATH.'app/controller/PageController.php';

$authToken = $_SERVER['HTTP_X_AUTH_TOKEN'];
/* ---------------------------------------------------- */
$warning = array();
$error = array();
$success = array();
$required = array();
/* ---------------------------------------------------- */

$res = new stdClass();
$res->metadata->clientRequestId = null;
$res->metadata->serverRequestId = null;
$res->metadata->serverRequestTime = $date;

if($api->validateKey($authToken)){

    if($_GET['action'] == 'getUserData'){
        include BASE_PATH.'resources/api/discord/getData.php';
    } elseif($_GET['action'] == 'setDiscordID'){
        include BASE_PATH.'resources/api/discord/setDiscordID.php';
    } elseif($_GET['action'] == 'getUserProducts'){
        include BASE_PATH.'resources/api/discord/getUserProducts.php';
    } else {
        array_push($error, 'Aktion wurde nicht übergeben');
        $state = 'error';
        $required = 'action';
    }

} else {
    array_push($error, 'Keine Berechtigung');
    $state = 'error';
}

if(empty($res->data)){
    $res->data = array();
}

if(!empty($required)){
    $res->data->required = $required;
}

$res->state = $state;
$res->message->warning = $warning;
$res->message->error = $error;
$res->message->success = $success;
if(!empty($debug)){
    $res->message->debug = $debug;
}
$res = json_encode($res);
die($res);