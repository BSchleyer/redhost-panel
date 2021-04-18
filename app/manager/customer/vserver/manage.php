<?php

$id = $helper->protect($_GET['id']);

$SQLGetServerInfos = $db->prepare("SELECT * FROM `vm_servers` WHERE `id` = :id");
$SQLGetServerInfos -> execute(array(":id" => $id));
$serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);

if(!($serverInfos['deleted_at'] == NULL)){
    header('Location: '.$helper->url().'order/vserver');
    die();
}

if(!is_null($serverInfos['locked'])){
    $_SESSION['product_locked_msg'] = $serverInfos['locked'];
    header('Location: '.env('URL').'manage/vserver');
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
        $user->addTransaction($userid, $price,'VPS #'.$id.' | Extra Traffic '.$traffic_amount.'GB');

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
    die(header('Location: '.$helper->url().'renew/vserver/'.$id));
} else {
    $suspended = false;
}

if($userid != $serverInfos['user_id']){
    die(header('Location: '.$helper->url().'manage/vserver'));
}

$status = $lxc->getStatus($serverInfos['node_id'], $serverInfos['id']);
$status = json_decode($status);
if($status->data->status == 'running'){
    $state = '<span class="badge badge-success">Online</span>';
    $serverStatus = 'ONLINE';
} else {
    $serverStatus = 'OFFLINE';
    $state = '<span class="badge badge-danger">Offline</span>';
}

if($available_traffic > $serverInfos['curr_traffic']) {

    if(isset($_POST['createPreset'])){
        $error = null;

        if(empty($_POST['icon'])){
            $error = 'Bitte wähle ein Icon aus';
        }

        if(empty($_POST['desc'])){
            $error = 'Bitte gebe eine Beschreibung an';
        }

        if(empty($_POST['command'])){
            $error = 'Bitte gebe einen Befehl an';
        }

        if(empty($error)){
            $SQL = $db->prepare("INSERT INTO `vm_server_command_presets`(`server_id`, `desc`, `command`, `icon`) VALUES (?,?,?,?)");
            $SQL->execute(array($id, $_POST['desc'], $_POST['command'], $_POST['icon']));

            echo sendSuccess('Command Preset wird hinzugefügt');
        } else {
            echo sendError($error);
        }
    }

    if(isset($_POST['deletePreset'])){
        $error = null;

        if(empty($_POST['preset'])){
            $error = 'Bitte wähle ein Preset aus';
        }

        $SQL = $db->prepare("SELECT * FROM `vm_server_command_presets` WHERE `id` = :id AND `server_id` = :server_id");
        $SQL->execute(array(":id" => $_POST['preset'], ":server_id" => $id));
        if($SQL->rowCount() != 1){
            $error = 'Preset nicht gefunden';
        }

        if(empty($error)){
            $SQL = $db->prepare("DELETE FROM `vm_server_command_presets` WHERE `id` = :id");
            $SQL->execute(array(":id" => $_POST['preset']));

            echo sendSuccess('Command Preset wurde gelöscht');
        } else {
            echo sendError($error);
        }
    }

    if(isset($_POST['execPreset'])){
        $error = null;

        if(empty($_POST['preset'])){
            $error = 'Bitte wähle ein Preset aus';
        }

        if ($status->data->status != 'running') {
            $error = 'Bitte starte deinen Server zuerst';
        }

        $SQL2 = $db->prepare("SELECT * FROM `vm_server_command_presets` WHERE `id` = :id AND `server_id` = :server_id");
        $SQL2->execute(array(":id" => $_POST['preset'], ":server_id" => $id));
        $presetSQL = $SQL2->fetch(PDO::FETCH_ASSOC);
        if($SQL2->rowCount() != 1){
            $error = 'Preset nicht gefunden';
        }

        if(empty($error)){

            $lxc->exec($serverInfos['node_id'], $serverInfos['id'], 'nohup '.$presetSQL['command'].' >/dev/null 2>&1 &');

            echo sendSuccess('Command Preset wurde ausgeführt');
        } else {
            echo sendError($error);
        }
    }

    if (isset($_POST['sendStop'])) {
        $error = null;

        if ($vmsoftware->getOpenInstalls($serverInfos['id'])) {
            $error = 'Es läuft noch eine Installation';
        }

        if ($status->data->status == 'stopped') {
            $error = 'Dein Server ist bereits gestoppt';
        }

        if (empty($error)) {

            $serverStatus = 'OFFLINE';
            $lxc->stopServer($serverInfos['node_id'], $serverInfos['id']);
            echo sendSweetSuccess('Dein Server wird nun gestoppt');

        } else {
            echo sendError($error);
        }
    }

    if (isset($_POST['sendStart'])) {
        $error = null;

        if ($vmsoftware->getOpenInstalls($serverInfos['id'])) {
            $error = 'Es läuft noch eine Installation';
        }

        if ($status->data->status == 'running') {
            $error = 'Dein Server ist bereits gestartet';
        }

        if (empty($error)) {

            $serverStatus = 'ONLINE';
            $lxc->startServer($serverInfos['node_id'], $serverInfos['id']);
            echo sendSweetSuccess('Dein Server wird nun gestartet');

        } else {
            echo sendError($error);
        }
    }

    if (isset($_POST['sendRestart'])) {
        $error = null;

        if ($vmsoftware->getOpenInstalls($serverInfos['id'])) {
            $error = 'Es läuft noch eine Installation';
        }

        if ($status->data->status == 'stopped') {
            $error = 'Dein Server ist bereits gestoppt';
        }

        if (empty($error)) {

            $serverStatus = 'ONLINE';
            $lxc->stopServer($serverInfos['node_id'], $serverInfos['id']);
            sleep(3);
            $lxc->startServer($serverInfos['node_id'], $serverInfos['id']);
            echo sendSweetSuccess('Dein Server wurde nun neugestartet');

        } else {
            echo sendError($error);
        }
    }

    if (isset($_POST['reinstallServer'])) {
        $error = null;

        if (empty($_POST['serverOS'])) {
            $error = 'Betriebsystem fehlerhaft';
        }

        if ($vmsoftware->getOpenInstalls($serverInfos['id'])) {
            $error = 'Es läuft noch eine Installation';
        }

        if (empty($error)) {
            /*
             * delete server
             */
            $lxc->stopServer($serverInfos['node_id'], $serverInfos['id']);
            sleep(3);
            $lxc->deleteServer($serverInfos['node_id'], $serverInfos['id']);
            sleep(3);

            $SQL = $db->prepare("SELECT * FROM `vm_server_os` WHERE `id` = :id");
            $SQL->execute(array(":id" => $_POST['serverOS']));
            $response = $SQL->fetch(PDO::FETCH_ASSOC);
            $serverOS = $response['template'];

            $ip_addrs = [];

            $SQL = $db->prepare("SELECT * FROM `ip_addresses` WHERE `service_id` = :service_id");
            $SQL->execute(array(":service_id" => $serverInfos['id']));
            if ($SQL->rowCount() != 0) {
                while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {
                    $ip_addrs[] = $row;
                }
            }

            if (is_null($serverInfos['disc_name'])) {
                $disc_name = 'local';
            } else {
                $disc_name = $serverInfos['disc_name'];
            }

            $debug = $lxc->recreate($serverInfos['node_id'], $serverInfos['id'], $serverOS, $serverInfos['cores'], $serverInfos['memory'], $serverInfos['password'], $serverInfos['disc'], '512', $ip_addrs, $serverInfos['addresses'], $serverInfos['hostname'], $disc_name, $serverInfos['network']);
            $SQL = $db->prepare("INSERT INTO `vm_tasks`(`service_id`, `task`) VALUES (:service_id,:debug)");
            $SQL->execute(array(":service_id" => $serverInfos['id'], ":debug" => $debug));

            $update = $db->prepare("UPDATE `vm_servers` SET `template_id` = :template_id WHERE `id` = :id");
            $update->execute(array(":template_id" => $serverOS, ":id" => $serverInfos['id']));

            $serverStatus = 'OFFLINE';

            echo sendSweetSuccess('Server wurde neuinstalliert');
        } else {
            echo sendError($error);
        }

    }

    $root_password = $serverInfos['password'];
    if (isset($_POST['setNewRootpassword'])) {
        $error = null;

        if ($serverStatus == 'OFFLINE') {
            $error = 'Dein Server muss gestartet sein';
        }

//        if (empty($_POST['root_password'])) {
//            $error = 'Du musst ein Rootpasswort angeben';
//        }
//        $root_password = $_POST['root_password'];
        $root_password = $helper->generateRandomString('25');

        if (empty($error)) {

            $test = $lxc->exec($serverInfos['node_id'], $serverInfos['id'], 'wget https://cdn.sylvan.ooo/sh/changePassword.sh');
            $lxc->exec($serverInfos['node_id'], $serverInfos['id'], 'chmod 777 changePassword.sh');
            $lxc->exec($serverInfos['node_id'], $serverInfos['id'], 'sh changePassword.sh ' . $root_password);
            $lxc->exec($serverInfos['node_id'], $serverInfos['id'], 'rm changePassword.sh');

            $update = $db->prepare("UPDATE `vm_servers` SET `password` = :password WHERE `id` = :id");
            $update->execute(array(":password" => $root_password, ":id" => $serverInfos['id']));

            echo sendSuccess($test);
            //echo sendSuccess('Dein Rootpasswort wurde geändert');
        } else {
            echo sendError($error);
        }
    }

    if (isset($_POST['installSoftware'])) {
        $error = null;

        if ($serverStatus == 'OFFLINE') {
            $error = 'Dein Server muss gestartet sein';
        }

        if (empty($_POST['software'])) {
            $error = 'Du musst eine Software auswählen';
        }

        if ($vmsoftware->getOpenInstalls($serverInfos['id'])) {
            $error = 'Es läuft noch eine Installation';
        }

        $softwareQuery = $db->prepare("SELECT * FROM `vm_software` WHERE `id` = :id");
        $softwareQuery->execute(array(":id" => $_POST['software']));
        if ($softwareQuery->rowCount() != 1) {
            $error = 'Software wurde nicht gefunden';
        }
        $softwareSQL = $softwareQuery->fetch(PDO::FETCH_ASSOC);

        if (empty($error)) {

            $vmsoftware->addInstall($serverInfos['id'], $softwareSQL['name']);

            $queue = [
                "action" => "INSTALLER",
                "data" => [
                    "username" => $username,
                    "email" => $mail,
                    "softwareSQL" => $softwareSQL,
                    "serverInfos" => $serverInfos,
                ]
            ];
            $queue = json_encode($queue);
            $insert = $db->prepare("INSERT INTO `queue`(`user_id`, `payload`) VALUES (?,?)");
            $insert->execute(array($userid, $queue));

//        $lxc->exec($serverInfos['node_id'], $serverInfos['id'],'wget '.$softwareSQL['url']);
//        $lxc->exec($serverInfos['node_id'], $serverInfos['id'],'chmod 777 '.$softwareSQL['file_name']);
//        $lxc->exec($serverInfos['node_id'], $serverInfos['id'],'sh '.$softwareSQL['file_name']);
//        $lxc->exec($serverInfos['node_id'], $serverInfos['id'],'rm '.$softwareSQL['file_name']);

            echo sendSuccess('Die Software wird nun installiert');
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