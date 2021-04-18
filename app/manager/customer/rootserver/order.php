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

    if(empty($_POST['duration'])){
        $error = 'duration not found';
    }
    $runtime = $_POST['duration'];
    if($validate->duration($runtime) != true){
        $error = 'Bitte gebe eine gültige Laufzeit an';
    }

    if(empty($_POST['cores'])){
        $error = 'cores not found';
    }
    if(empty($_POST['memory'])){
        $error = 'memory not found';
    }
    if(empty($_POST['disk'])){
        $error = 'disk not found';
    }
    if(empty($_POST['addresses'])){
        $error = 'addresses not found';
    }
    if(empty($_POST['duration'])){
        $error = 'runtime not found';
    }
    if(empty($_POST['serverOS'])){
        $error = 'serverOS not found';
    }
    if($site->productOptionEntrieExist('5', $_POST['cores']) == false){
        $error = 'cores option entry does not exists';
    }
    if($site->productOptionEntrieExist('6', $_POST['memory']) == false){
        $error = 'memory option entry does not exists';
    }
    if($site->productOptionEntrieExist('7', $_POST['disk']) == false){
        $error = 'disk option entry does not exists';
    }
    if($site->productOptionEntrieExist('8', $_POST['addresses']) == false){
        $error = 'addresses option entry does not exists';
    }

    $cores = $_POST['cores'];
    $memory = $_POST['memory'];
    $disk = $_POST['disk'];
    $addresses = $_POST['addresses'];
    $runtime = $_POST['duration'];
    $rootpassword = $helper->generateRandomString('20');
    $hostname = 'vm'.$helper->generateRandomString(5,'1234567890').'.'.env('APP_DOMAIN');

    if($site->validateRootserverOS($_POST['serverOS']) == false){
        $error = 'serverOS does not exists';
    }
    $SQL = $db->prepare("SELECT * FROM `vm_server_os` WHERE `id` = :id");
    $SQL->execute(array(":id" => $_POST['serverOS']));
    $response = $SQL->fetch(PDO::FETCH_ASSOC);
    $serverOS = $response['template'];

    $db_price = $site->getProductOptionEntrie('5', $cores,'price')
        +$site->getProductOptionEntrie('6', $memory,'price')
        +$site->getProductOptionEntrie('7', $disk,'price')
        +$site->getProductOptionEntrie('8', $addresses,'price');
    $price = $db_price * $validate->getIntervalFactor($runtime);
    $price = number_format($price,2);

    if($amount < $price){
        $error = 'Du hast leider nicht genügend Guthaben';
        $_SESSION['error_msg'] = 'Du hast leider nicht genügend Guthaben';
        header('Location: '.env('URL').'accounting/charge');
        die();
    }

    if($price == 0){
        $error = 'Ungültige Anfrage bitte versuche es erneut (1001)';
    }

    $SQL3 = $db->prepare("SELECT * FROM `vm_host_nodes` WHERE `active` = 'yes' AND `type` = 'KVM' ORDER BY `id` ASC LIMIT 1;");
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
            //$discord->callWebhook('@everyone es sind leider nicht mehr genügend IP frei! (Rootserver)','https://discordapp.com/api/webhooks/773552489013182495/hldzSH6vih95w6DpfLDAvEfoMNzfTmHHalbvO_Ele3hSUbG0fdyYsOa6XhdpxlsIWx4K');
        }
    }


    if(empty($error)){

        $discord->callWebhook($username.' wartet auf die Freischaltung von seinem Rootserver');

        $queue = [
            "action" => "ROOTSERVER_ORDER",
            "data" => [
                "username" => $username,
                "email" => $mail,
                "price" => $db_price,
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
                "pack_name" => null,
            ]
        ];
        $queue = json_encode($queue);
        $insert = $db->prepare("INSERT INTO `queue`(`user_id`, `payload`) VALUES (?,?)");
        $insert->execute(array($userid, $queue));

        $user->removeMoney($price, $userid);
        $user->addTransaction($userid,'-'.$price,'Rootserver Bestellung');

        if($user->getDataById($userid,'mail_order')){
            include BASE_PATH.'app/notifications/mail_templates/product/order.php';
            $mail_state = sendMail($mail, $username, $mailContent, $mailSubject);
        }

        $_SESSION['success_msg'] = 'Vielen Dank! Dein Produkt wird gleich eingerichtet';
        header('Location: '.env('URL').'manage/rootserver');

    } else {
        $_SESSION['error_msg'] = $error;
        header('Location: '.env('URL').'order/rootserver');
    }

}