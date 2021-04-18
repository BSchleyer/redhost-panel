<?php

if(isset($_POST['order'])){

    $error = null;

    if(!$user->sessionExists($_COOKIE['session_token'])){
        $error = 'Bitte logge dich erst ein';
    }

    if($helper->getSetting('teamspeak') == 0){
        $error = 'Die Bestellung ist derzeit deaktiviert';
    }

    if(!isset($_POST['wiederruf'])){
        $error = 'Du musst Unsere Wiederrufsbestimmungen akzeptieren';
    }

    if(!isset($_POST['agb'])){
        $error = 'Du musst Unsere AGB und Datenschutzbestimmungen akzeptieren';
    }

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

    if(empty($_POST['duration'])){
        $error = 'Bitte wähle eine Laufzeit aus';
    }

    if($validate->duration($_POST['duration']) != true){
        $error = 'Bitte gebe eine gültige Laufzeit an';
    }

    $price_1 = ($_POST['slots'] * $site->getProductPrice('TEAMSPEAK'));
    $price = $price_1 * ($_POST['duration'] / 30);
    $db_price = $site->getProductPrice('TEAMSPEAK');

//    $error = $price;

    $price = number_format($price,2);

    if(empty($error) && $price == 0){
        $error = 'Ungültige Anfrage bitte versuche es erneut (1001)';
    }

    if($amount < $price){
        $error = 'Du hast leider nicht genügend Guthaben';
    }

    $node_id = '2'; //Node ID for new ordered TS Servers
    $port = rand(9000, 12000);
    if(!$site->isTS3PortAviable($node_id, $port)){
        $error = 'Bitte versuche es erneut';
    }

    if(empty($error)){

        //$discord->callWebhook('Soeben wurde ein neuer Teamspeak Server bestellt von '.$username);

        $getNodeInfos = $db->prepare("SELECT * FROM `teamspeak_hosts` WHERE `id` = :id");
        $getNodeInfos->execute(array(":id" => $node_id));
        $nodeInfos = $getNodeInfos->fetch(PDO::FETCH_ASSOC);

        $sid_converter = json_encode($ts3->createServer($node_id, $_POST['slots'], $port));
        $get_sid = json_decode($sid_converter);
        $sid = $get_sid->sid;

        $date = new DateTime(null, new DateTimeZone('Europe/Berlin'));
        $date->modify('+' . $_POST['duration'] . ' day');
        $new_date = $date->format('Y-m-d H:i:s');

        $SQLInsertBot = $db->prepare("INSERT INTO `teamspeaks`(`slots`, `user_id`, `node_id`, `teamspeak_ip`, `teamspeak_port`, `sid`, `expire_at`, `price`) VALUES (:slots,:user_id,:node_id,:teamspeak_ip,:teamspeak_port,:sid,:expire_at,:price)");
        $SQLInsertBot->execute(array(":slots" => $_POST['slots'], ":user_id" => $userid, ":node_id" => $node_id, ":teamspeak_ip" => $nodeInfos['login_ip'], ":teamspeak_port" => $port, ":sid" => $sid, ":expire_at" => $new_date, ":price" => $db_price));

        $user->removeMoney($price, $userid);
        $user->addTransaction($userid,'-'.$price,'Teamspeak Server bestellung');

        if($user->getDataById($userid,'mail_order')){
            include BASE_PATH.'app/notifications/mail_templates/product/order.php';
            $mail_state = sendMail($mail, $username, $mailContent, $mailSubject);
        }

        $_SESSION['success_msg'] = 'Vielen Dank! Dein Server wird nun eingerichtet';
        header('Location: '.$helper->url().'manage/teamspeak');
        die();
    } else {
        $_SESSION['error_msg'] = $error;
        header('Location: '.env('URL').'order/teamspeak');
    }

}