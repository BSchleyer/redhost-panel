<?php

if(!($serverInfos['deleted_at'] == NULL)){
    header('Location: '.$helper->url().'order/rootserver');
    die();
}

if(!is_null($serverInfos['locked'])){
    $_SESSION['product_locked_msg'] = $serverInfos['locked'];
    header('Location: '.env('URL').'manage/rootserver');
    die();
}

if(is_null($serverInfos['traffic'])){
    $available_traffic = $helper->getSetting('default_traffic_limit');
} else {
    $available_traffic = $serverInfos['traffic'];
}

if(isset($_POST['buyTraffic'])){
    $error = null;

    $traffic_valid = false;
    $traffic_amount = $_POST['traffic_amount'];
    if($traffic_amount == '512' || $traffic_amount == '1024'){
        $traffic_valid = true;
    }
    if($traffic_valid == false){
        $error = 'Diese möglichkeit existiert nicht';
    }


    if($traffic_amount == '512'){
        $price = '7.00';
    }
    if($traffic_amount == '1024'){
        $price = '14.00';
    }

    if($price > $amount){
        $error = 'Du hast nicht genügent Guthaben';
    }

    if(empty($error)){

        $user->removeMoney($price, $userid);
        $user->addTransaction($userid, $price,'KVM #'.$id.' | Extra Traffic '.$traffic_amount.'GB');

        $update = $db->prepare("UPDATE `vm_servers` SET `traffic` = :traffic WHERE `id` = :id");
        $update->execute(array(":traffic" => $available_traffic+$traffic_amount, ":id" => $id));

        $_SESSION['success_msg'] = 'Vielen Dank. Dein Server wird in kürze wieder freigeschaltet!';
        header('Location: '.$site->currentUrl());
        die();

    } else{
        echo sendError($error);
    }
}

if($serverInfos['state'] == 'SUSPENDED'){
    $suspended = true;
    die(header('Location: '.$helper->url().'renew/rootserver/'.$id));
} else {
    $suspended = false;
}

if($userid != $serverInfos['user_id']){
    die(header('Location: '.$helper->url().'manage/rootserver'));
}

$status = $kvm->getStatus($serverInfos['node_id'], $serverInfos['id']);
$status = json_decode($status);
if($status->data->status == 'running'){
    $state = '<span class="badge badge-success">Online</span>';
    $serverStatus = 'ONLINE';
} else {
    $serverStatus = 'OFFLINE';
    $state = '<span class="badge badge-danger">Offline</span>';
}

if($available_traffic > $serverInfos['curr_traffic']) {

    if (isset($_POST['sendStop'])) {
        $error = null;

        if ($status->data->status == 'stopped') {
            $error = 'Dein Server ist bereits gestoppt';
        }

        if (empty($error)) {

            $serverStatus = 'OFFLINE';
            $kvm->stopServer($serverInfos['node_id'], $serverInfos['id']);
            echo sendSweetSuccess('Dein Server wird nun gestoppt');

        } else {
            echo sendError($error);
        }
    }

    if (isset($_POST['sendStart'])) {
        $error = null;

        if ($status->data->status == 'running') {
            $error = 'Dein Server ist bereits gestartet';
        }

        if (empty($error)) {

            $serverStatus = 'ONLINE';
            $kvm->startServer($serverInfos['node_id'], $serverInfos['id']);
            echo sendSweetSuccess('Dein Server wird nun gestartet');

        } else {
            echo sendError($error);
        }
    }

    if (isset($_POST['sendRestart'])) {
        $error = null;

        if ($status->data->status == 'stopped') {
            $error = 'Dein Server ist bereits gestoppt';
        }

        if (empty($error)) {

            $serverStatus = 'ONLINE';
            $kvm->stopServer($serverInfos['node_id'], $serverInfos['id']);
            sleep(3);
            $kvm->startServer($serverInfos['node_id'], $serverInfos['id']);
            echo sendSweetSuccess('Dein Server wurde nun neugestartet');

        } else {
            echo sendError($error);
        }
    }

    if (isset($_POST['setNewRootpassword'])) {
        $error = null;

        if (empty($error)) {

            $discord->callWebhook('@everyone bei dem Rootserver #'.$id.' soll das Rootpasswort geändert werden!','https://discordapp.com/api/webhooks/773552489013182495/hldzSH6vih95w6DpfLDAvEfoMNzfTmHHalbvO_Ele3hSUbG0fdyYsOa6XhdpxlsIWx4K');

            echo sendSuccess('Eine Rootpasswort Änderung wurde angefragt');
        } else {
            echo sendError($error);
        }
    }
}

if($serverStatus == 'ONLINE'){
    $state = '<span class="badge badge-success">Online</span>';
}

if($serverStatus == 'OFFLINE'){
    $state = '<span class="badge badge-danger">Offline</span>';
}

if(isset($_POST['saveRDNS'])){
    $error = null;

    if(empty($_POST['ip_addr'])){
        $error = 'Es wurde keine IP gefunden';
    }

    if(!filter_var($_POST['ip_addr'], FILTER_VALIDATE_IP)) {
        $error = 'Es wurde keine gültige IP gefunden';
    }

    if(empty($_POST['rdns'])){
        $error = 'Es wurde kein rDNS eintrag gefunden';
    }

    if(!filter_var($_POST['rdns'], FILTER_VALIDATE_DOMAIN)) {
        $error = 'rDNS Eintgrag ist ungültig';
    }

    $SQL = $db->prepare("SELECT * FROM `ip_addresses` WHERE `service_id` = :service_id AND `ip` = :ip");
    $SQL->execute(array(':service_id' => $id, ':ip' => $_POST['ip_addr']));
    if($SQL->rowCount() != 1){
        $error = 'Keine Rechte auf diese IP Adresse gefunden';
    }

    if(empty($error)){

        $SQL = $db->prepare("UPDATE `ip_addresses` SET `rdns` = :rdns WHERE `service_id` = :service_id AND `ip` = :ip");
        $SQL->execute(array(':rdns' => $_POST['rdns'], ':service_id' => $id, ':ip' => $_POST['ip_addr']));

        $venocix->setRDNS($_POST['ip_addr'], $_POST['rdns']);

        echo sendSuccess('rDNS wurde gespeichert');

    } else {
        echo sendError($error);
    }
}