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

    if(empty($error)){

        $serviceID = $site->getLastVMID();

        $task = $venocix->createVM($cores, $memory, $disk, $addresses, $serverOS);
        $SQL = $db->prepare("INSERT INTO `vm_tasks`(`service_id`, `task`) VALUES (:service_id, :task)");
        $SQL->execute(array(":service_id" => $serviceID, ":task" => json_encode($task)));

        $rootpassword = null;
        $hostname = null;
        $job_id = $task->result->jobId;

        $date = new DateTime(null, new DateTimeZone('Europe/Berlin'));
        $date->modify('+' . $runtime . ' day');
        $expire_at = $date->format('Y-m-d H:i:s');

        $SQLDB = $db;
        $SQL = $SQLDB->prepare("INSERT INTO `vm_servers`(`user_id`, `hostname`, `password`, `template_id`, `node_id`, `cores`, `memory`, `disc`, `addresses`, `price`, `state`, `expire_at`, `disc_name`, `traffic`, `api_name`, `pack_name`, `job_id`, `type`) VALUES (:user_id,:hostname,:password,:template_id,:node_id,:cores,:memory,:disc,:addresses,:price,:state,:expire_at,:disc_name,:traffic,:api_name,:pack_name,:job_id,:type)");
        $SQL->execute(array(":user_id" => $userid, ":hostname" => $hostname, ":password" => $rootpassword, ":template_id" => $serverOS, ":node_id" => '99999', ":cores" => $cores, ":memory" => $memory, ":disc" => $disk, ":addresses" => $addresses, ":price" => $db_price, ":state" => 'ACTIVE', ":expire_at" => $expire_at, ":disc_name" => 'local', ":traffic" => '10240', ":api_name" => 'VENOCIX', ":pack_name" => null, ":job_id" => $job_id, ":type" => 'KVM'));

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