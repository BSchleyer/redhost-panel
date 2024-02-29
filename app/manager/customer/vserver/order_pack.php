<?php

$order_success = false;

if(isset($_POST['order'])){

    $error = null;

    if(!$user->sessionExists($_COOKIE['session_token'])){
        $error = 'Bitte logge dich erst ein';
    }

    if($helper->getSetting('vps') == 0){
        $error = 'Die Bestellung ist derzeit deaktiviert';
    }

    if(!isset($_POST['wiederruf'])){
        $error = 'Du musst Unsere Wiederrufsbestimmungen akzeptieren';
    }

    if(!isset($_POST['agb'])){
        $error = 'Du musst Unsere AGB und Datenschutzbestimmungen akzeptieren';
    }

    if(empty($_POST['serverOS'])){
        $error = 'serverOS not found';
    }

    if(empty($_POST['pack_id'])){
        $error = 'pack_id not found';
    }

    $SQL2 = $db->prepare("SELECT * FROM `vm_server_packs` WHERE `id` = :id");
    $SQL2->execute(array(":id" => $_POST['pack_id']));
    if($SQL2->rowCount() != 1){
        $error = 'Paket wurde nicht gefunden';
    }
    $packData = $SQL2->fetch(PDO::FETCH_ASSOC);

    $cores = $packData['cores'];
    $memory = $packData['memory'];
    $disk = $packData['disk'];
    $addresses = 1;
    $runtime = 30;
    $rootpassword = $helper->generateRandomString('20');
    $hostname = 'vps'.$helper->generateRandomString(5,'1234567890').'.'.env('FRONT_URL');

    if($site->validateRootserverOS($_POST['serverOS']) == false){
        $error = 'serverOS does not exists';
    }
    $SQL = $db->prepare("SELECT * FROM `vm_server_os` WHERE `id` = :id");
    $SQL->execute(array(":id" => $_POST['serverOS']));
    $response = $SQL->fetch(PDO::FETCH_ASSOC);
    $serverOS = $response['template'];

    $price = $packData['price'];

    if($amount < $price){
        $error = 'Du hast leider nicht genügend Guthaben';
        $_SESSION['error_msg'] = 'Du hast leider nicht genügend Guthaben';
        header('Location: '.env('URL').'accounting/charge');
        die();
    }

    if($price == 0){
        $error = 'Ungültige Anfrage bitte versuche es erneut (1001) ';
    }

    $SQL3 = $db->prepare("SELECT * FROM `vm_host_nodes` WHERE `active` = 'yes' ORDER BY `id` ASC LIMIT 1;");
    $SQL3->execute();
    if ($SQL3->rowCount() != 0) {
        while ($row3 = $SQL3->fetch(PDO::FETCH_ASSOC)) {

            $node_id = $row3['id'];
            $disc_name = $row3['disc_name'];
            $api_name = $row3['api_name'];

        }
    } else {
        $error = 'Es wurde keine freie Node gefunden';
    }

    if(empty($error)){
        $i = 0;
        $SQL = $db->prepare("SELECT * FROM `ip_addresses` WHERE `service_id` IS NULL AND `node_id` = :node_id");
        $SQL->execute(array(":node_id" => $node_id));
        if ($SQL->rowCount() >= $addresses) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {
                if($i+1 <= $addresses){
                    $ip_addrs[] = $row;
                    $i++;
                }
            }
        } else {
            $error = 'Es sind leider nicht mehr genügend IP-Adressen verfügbar';
            //$discord->callWebhook('@everyone es sind leider nicht mehr genügend IP frei! (vServer Pakete)','https://discordapp.com/api/webhooks/772415342458306582/MOiksRME8Luys_Tid8-hr5ZHmezK0VZSIB_Wj-UwcuyzLgdL2ypvU3M03cCp_uKwGod7');
        }
    }


    if(empty($error)){

        $discord->callWebhook('Soeben wurde ein neuer vServer bestellt von '.$username);

        $queue = [
            "action" => "VSERVER_ORDER",
            "data" => [
                "username" => $username,
                "email" => $mail,
                "price" => $price,
                "runtime" => $runtime,
                "rootpassword" => $rootpassword,
                "hostname" => $hostname,
                "serverOS" => $serverOS,
                "cores" => $cores,
                "memory" => $memory,
                "disk" => $disk,
                "ip_addrs" => $ip_addrs,
                "addresses" => $addresses,
                "node_id" => $node_id,
                "disc_name" => $disc_name,
                "api_name" => $api_name,
                "traffic" => $helper->getSetting('default_traffic_limit'),
                "pack_name" => 'pack_'.$_POST['pack_id'],
				"network" => '12',
            ]
        ];
        $queue = json_encode($queue);
        $insert = $db->prepare("INSERT INTO `queue`(`user_id`, `payload`) VALUES (?,?)");
        $insert->execute(array($userid, $queue));

        $user->removeMoney($price, $userid);
        $user->addTransaction($userid,'-'.$price,'vServer(Paket) Bestellung');

        if($user->getDataById($userid,'mail_order')){
            include BASE_PATH.'app/notifications/mail_templates/product/order.php';
            $mail_state = sendMail($mail, $username, $mailContent, $mailSubject);
        }

        $_SESSION['success_msg'] = 'Vielen Dank! Dein Produkt wird gleich eingerichtet';
        header('Location: '.env('URL').'manage/vserver');

    } else {
        $_SESSION['error_msg'] = $error;
        header('Location: '.env('URL').'order/vserver');
    }

}
