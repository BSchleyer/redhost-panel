<?php

$id = $helper->protect($_GET['id']);

$SQLGetServerInfos = $db->prepare("SELECT * FROM `teamspeaks` WHERE `id` = :id");
$SQLGetServerInfos -> execute(array(":id" => $id));
$serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);

if(!($serverInfos['deleted_at'] == NULL)){
    header('Location: '.$helper->url().'order/teamspeak');
    die();
}

if($userid != $serverInfos['user_id']){
    die(header('Location: '.$helper->url().'manage/teamspeak'));
}

if(isset($_POST['reconfigure'])){

    $error = null;

    if(empty($_POST['slots'])){
        $error = 'Bitte wähle Slots aus';
    }

    if(!is_numeric($_POST['slots'])){
        $error = 'Bitte wähle Slots aus (Zahl)';
    }

    if($_POST['slots'] > 1000){
        $error = 'Bitte wähle weniger als 1000 Slots';
    }

    if($_POST['slots'] < 5){
        $error = 'Bitte wähle mehr als 5 Slots';
    }

    $sum = ($_POST['slots'] * $site->getProductPrice('TEAMSPEAK')) - ($serverInfos['slots'] * $site->getProductPrice('TEAMSPEAK'));
    $price = round($sum * $site->getDiffInDays($serverInfos['expire_at']) / 30,2);

    if($site->getDiffInDays($serverInfos['expire_at']) < 5){
        $error = 'Bitte verlängere zuerst deinen Dienst';
    }

    if($amount < $price){
        $error = 'Du hast leider nicht genügend Guthaben';
    }

    if($_POST['slots'] == $serverInfos['slots']){
        $error = 'Bitte verändere die Slotanzahl';
    }

    try{
        $SQL = $db->prepare("SELECT * FROM `teamspeak_hosts` WHERE `id` = :id");
        $SQL -> execute(array(":id" => $serverInfos['node_id']));
        $nodeInfos = $SQL->fetch(PDO::FETCH_ASSOC);
        $uri = "serverquery://".$nodeInfos['login_name'].":".$nodeInfos['login_passwort']."@".$nodeInfos['login_ip'].":".$nodeInfos['login_port']."/?server_port=".$serverInfos['teamspeak_port'];
        $ts3_query = TeamSpeak3::factory($uri);

        $serverStatus = 'ONLINE';
    }catch(TeamSpeak3_Exception $e){
        $serverStatus = 'OFFLINE';
    }

    if($serverStatus == 'OFFLINE'){
        $error = 'Dein Server muss gestartet sein';
    }

    if(empty($error)){

        $SQLGetServerInfos = $db->prepare("UPDATE `teamspeaks` SET `slots` = :slots WHERE `id` = :id");
        $SQLGetServerInfos -> execute(array(":slots" => $_POST['slots'], ":id" => $id));

        $ts3->changeSlots($serverInfos['node_id'], $serverInfos['teamspeak_port'], $_POST['slots']);

        if($price > 0){
            $user->removeMoney($price, $userid);
            $user->addTransaction($userid,'-'.$price,'Teamspeak Server upgrade');
            echo sendSuccess('Dein Server wurde geupgraded');
        } else {
            $price = str_replace('-','', $price);
            $user->addMoney($price, $userid);
            $user->addTransaction($userid, $price,'Teamspeak Server downgrade');
            echo sendSuccess('Dein Server wurde downgraded');
        }

        header('refresh:3;url='.$helper->url().'manage/teamspeak/'.$serverInfos['id']);

    } else {
        echo sendError($error);
    }

}