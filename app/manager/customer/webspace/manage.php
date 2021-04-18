<?php

$id = $helper->protect($_GET['id']);

$SQLGetServerInfos = $db->prepare("SELECT * FROM `webspace` WHERE `id` = :id");
$SQLGetServerInfos -> execute(array(":id" => $id));
$serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);

if(!is_null($serverInfos['locked'])){
    $_SESSION['product_locked_msg'] = $serverInfos['locked'];
    header('Location: '.env('URL').'manage/webspace');
    die();
}

$SQL = $db->prepare("SELECT * FROM `webspace_host` WHERE `id` = :id");
$SQL -> execute(array(":id" => 1));
$webhostInfos = $SQL -> fetch(PDO::FETCH_ASSOC);

if(!($serverInfos['deleted_at'] == NULL)){
    header('Location: '.$helper->url().'webspace/order');
}

if($serverInfos['state'] == 'suspended'){
    $suspended = true;
} else {
    $suspended = false;
}

if($userid != $serverInfos['user_id']){
    die(header('Location: '.$helper->url().'manage/webspace'));
}

if(isset($_POST['login'])){
    echo '<script type="text/javascript" language="Javascript">window.open("'.$plesk->generateSession($username.$userid, $user->getIP()).'");</script>';
}