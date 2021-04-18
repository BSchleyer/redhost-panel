<?php

$id = $helper->protect($_GET['id']);

$SQLGetServerInfos = $db->prepare("SELECT * FROM `teamspeaks` WHERE `id` = :id");
$SQLGetServerInfos -> execute(array(":id" => $id));
$serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);

if(!($serverInfos['deleted_at'] == NULL)){
    header('Location: '.$helper->url().'order/teamspeak');
}

if(!is_null($serverInfos['locked'])){
    $_SESSION['product_locked_msg'] = $serverInfos['locked'];
    header('Location: '.env('URL').'manage/teamspeak');
    die();
}

include 'ts3admin.class.php';
include 'ts3_functions.php';

if($serverStatus == 'ONLINE'){
    $status_msg = '<span class="badge badge-success">Online</span>';
} else {
    $status_msg = '<span class="badge badge-danger">Offline</span>';
}

if($userid != $serverInfos['user_id']){
    die(header('Location: '.$helper->url().'order/teamspeak'));
}

if($serverStatus == 'ONLINE'){
    $connection_info = getInfos($ts3_query);
    $version = getVersion($ts3_query);
}

if($serverInfos['state'] == 'SUSPENDED'){
    $suspended = true;
} else {
    $suspended = false;

    if(isset($_POST['sendStop'])){
        $ts3->stopServer($serverInfos['node_id'],$serverInfos['teamspeak_port'],$serverInfos['sid']);
        $serverStatus = 'OFFLINE';

        $_SESSION['success_msg'] = 'Dein Server wurde gestoppt';
        header('Location: '.$helper->url().'manage/teamspeak/'.$id);
    }

    if(isset($_POST['sendStart'])){
        if($suspended){
            echo sendError('Bitte verlängere deinen Teamspeak');
        } else {
            $ts3->startServer($serverInfos['node_id'],$serverInfos['teamspeak_port'],$serverInfos['sid']);
            //$serverStatus = 'ONLINE';

            $_SESSION['success_msg'] = 'Dein Server wurde gestartet';
            header('Location: '.$helper->url().'manage/teamspeak/'.$id);
        }
    }

    if(isset($_POST['createToken'])){
        if(isset($_POST['group']) && !empty($_POST['group'])){
            createToken($ts3_query,$_POST['group'],$_POST['description'], $serverStatus);
            echo sendSuccess('Erfolgreich','Der Token wurde erfolgreich angelegt');
        }
    }

    if(isset($_POST['deleteToken']) && !empty($_POST['deleteToken'])){
        deleteToken($ts3_query, $_POST['deleteToken'], $serverStatus);
        echo sendSuccess('Erfolgreich','Der Token wurde gelöscht');
    }

    if(isset($_POST['sendReinstall'])){

        if($serverStatus == 'ONLINE'){
            $ts3->stopServer($serverInfos['node_id'],$serverInfos['teamspeak_port'],$serverInfos['sid']);
            sleep(1);
        }

        $serverStatus = 'OFFLINE';

        $ts3->deleteServer($serverInfos['node_id'], $serverInfos['sid']);

        sleep(2);

        $sid_converter = json_encode($ts3->createServer($serverInfos['node_id'], $serverInfos['slots'], $serverInfos['teamspeak_port']));
        $get_sid = json_decode($sid_converter);
        $sid = $get_sid->sid;

        $SQLGetServerInfos = $db->prepare("UPDATE `teamspeaks` SET `sid` = :sid WHERE `id` = :id");
        $SQLGetServerInfos -> execute(array(":sid" => $sid, ":id" => $id));

        $_SESSION['success_sweet_msg'] = 'Dein Teamspeak wurde neuinstalliert';
        header('Location: '.$helper->url().'manage/teamspeak/'.$id);
    }

    if(isset($_POST['createSnapshot'])){
        $snap = $ts3->snapshotCreate($serverInfos['node_id'], $serverInfos['teamspeak_port']);
        $SQL = $db->prepare("INSERT INTO `teamspeak_backups`(`user_id`, `teamspeak_id`, `files`, `desc`) VALUES (?,?,?,?)");
        $SQL -> execute(array($userid, $id, $snap, $_POST['desc']));
        echo sendSuccess('Backup wurde erstellt');
    }

    if(isset($_POST['restoreSnapshot'])){
        $error = null;
        if(empty($_POST['restoreSnapshot'])){
            $error = 'Backup enthält Fehler';
        }

        $SQLSnapShot = $db->prepare("SELECT * FROM `teamspeak_backups` WHERE `user_id` = :user_id AND `id` = :id");
        $SQLSnapShot->execute(array(":user_id" => $userid, ":id" => $_POST['restoreSnapshot']));
        $snapSQL = $SQLSnapShot -> fetch(PDO::FETCH_ASSOC);
        if($SQLSnapShot->rowCount() != 1){
            $error = 'Backup wurde nicht gefunden';
        }

        if(empty($error)){
            $ts3->snapshotRestore($serverInfos['node_id'], $serverInfos['teamspeak_port'], $snapSQL['files']);

            sleep(1);

            $ts3->changeSlots($serverInfos['node_id'], $serverInfos['teamspeak_port'], $serverInfos['slots']);

            //$SQL = $db->prepare("DELETE FROM `teamspeak_backups` WHERE `user_id` = :user_id AND `files` = :files");
            //$SQL -> execute(array(":user_id" => $userid, ":files" => $snapSQL['files']));
            $_SESSION['success_msg'] = 'Backup wurde wiederhergestellt';
            sleep(1);
            header('Location: '.$helper->url().'manage/teamspeak/'.$id);
        } else {
            echo sendError($error);
        }
    }

    if(isset($_POST['deleteSnapshot'])){
        $error = null;
        if(empty($_POST['deleteSnapshot'])){
            $error = 'Backup enthält fehler';
        }

        $SQLSnapShot = $db->prepare("SELECT * FROM `teamspeak_backups` WHERE `user_id` = :user_id AND `id` = :id");
        $SQLSnapShot->execute(array(":user_id" => $userid, ":id" => $_POST['deleteSnapshot']));
        if($SQLSnapShot->rowCount() != 1){
            $error = 'Backup wurde nicht gefunden';
        }

        if(empty($error)){
            $SQL = $db->prepare("DELETE FROM `teamspeak_backups` WHERE `user_id` = :user_id AND `id` = :id");
            $SQL -> execute(array(":user_id" => $userid, ":id" => $_POST['deleteSnapshot']));
            echo sendSuccess('Backup wurde gelöscht');
        } else {
            echo sendError($error);
        }
    }

}