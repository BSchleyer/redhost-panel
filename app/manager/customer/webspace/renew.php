<?php

$id = $helper->protect($_GET['id']);

$SQLGetServerInfos = $db->prepare("SELECT * FROM `webspace` WHERE `id` = :id");
$SQLGetServerInfos -> execute(array(":id" => $id));
$serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);

$SQL = $db->prepare("SELECT * FROM `webspace_packs` WHERE `plesk_id` = :id");
$SQL->execute(array(":id" => $serverInfos['plan_id']));
$packInfos = $SQL->fetch(PDO::FETCH_ASSOC);

if(!($serverInfos['deleted_at'] == NULL)){
    header('Location: '.$helper->url().'order/webspace');
}

if($userid != $serverInfos['user_id']){
    die(header('Location: '.$helper->url().'dashboard'));
}

if(isset($_POST['renew'])){

    $error = null;

    if(empty($_POST['duration'])){
        $error = 'Bitte wähle eine Laufzeit aus';
    }

    $price = $serverInfos['price'] * ($_POST['duration'] / 30);
    if($voucherState){
        $discount_cal = round($voucherResponse['value']) * $price / 100;
        $price = number_format($price - $discount_cal,2);
    }

    if($amount < $price){
        $error = 'Du hast leider nicht genügend Guthaben';
    }

    if($validate->duration($_POST['duration']) != true){
        $error = 'Bitte gebe eine gültige Laufzeit an';
    }

    if($price == 0){
        $error = 'Ungültige Anfrage bitte versuche es erneut (1001)';
    }

    if(empty($error)){

        $date = new DateTime($serverInfos['expire_at'], new DateTimeZone('Europe/Berlin'));
        $date->modify('+' . $_POST['duration'] . ' day');
        $expire_at = $date->format('Y-m-d H:i:s');

        $SQLGetServerInfos = $db->prepare("UPDATE `webspace` SET `expire_at` = :expire_at, `state` = 'active' WHERE `id` = :id");
        $SQLGetServerInfos -> execute(array(":expire_at" => $expire_at, ":id" => $id));

        $user->removeMoney($price, $userid);
        $user->addTransaction($userid,'-'.$price,'Webspace Verlängerung');

        echo sendSuccess('Dein Webspace wurde verlängert');

        header('refresh:3;url='.$helper->url().'manage/webspace/'.$serverInfos['id']);

    } else {
        echo sendError($error);
    }

}