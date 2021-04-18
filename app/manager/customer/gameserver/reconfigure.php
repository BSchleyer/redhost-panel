<?php

$id = $helper->protect($_GET['id']);

$SQLGetServerInfos = $db->prepare("SELECT * FROM `pterodactyl_servers` WHERE `id` = :id");
$SQLGetServerInfos -> execute(array(":id" => $id));
$serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);

if(!($serverInfos['deleted_at'] == NULL)){
    header('Location: '.$helper->url().'order/gameserver');
}

if($userid != $serverInfos['user_id']){
    die(header('Location: '.$helper->url().'dashboard'));
}

if($serverInfos['state'] == 'active'){
    $state = '<span class="badge badge-success">Aktiv</span>';
} elseif($serverInfos['state'] == 'suspended') {
    $state = '<span class="badge badge-warning">Gesperrt</span>';
}else {
    $state = '<span class="badge badge-danger">Unbekannt</span>';
}

if(isset($_POST['reconfigure'])){
    $error = null;

    if($site->getDiffInDays($serverInfos['expire_at']) < 5){
        $error = 'Bitte verlängere zuerst deinen Dienst';
    }

    if(empty($_POST['cpu'])){
        $error = 'cpu not found';
    }
    if(empty($_POST['memory'])){
        $error = 'memory not found';
    }
    if(empty($_POST['disk'])){
        $error = 'disk not found';
    }
    if($site->productOptionEntrieExist('9', $_POST['cpu']) == false){
        $error = 'cpu option entry does not exists';
    }
    if($site->productOptionEntrieExist('10', $_POST['memory']) == false){
        $error = 'memory option entry does not exists';
    }
    if($site->productOptionEntrieExist('11', $_POST['disk']) == false){
        $error = 'disk option entry does not exists';
    }
    $cpu = $_POST['cpu'];
    $memory = $_POST['memory'];
    $disk = $_POST['disk'];

    if($disk < $serverInfos['disk']){
        $error = 'Ein Downgrade der Festplatte ist Technisch leider nicht möglich';
    }

    /*
    * calculate the price to pay
    */
    $cpu_price = $site->getProductOptionEntrie('9', $cpu,'price') - $site->getProductOptionEntrie('9', $serverInfos['cpu'],'price');
    $memory_price = $site->getProductOptionEntrie('10', $memory,'price') - $site->getProductOptionEntrie('10', $serverInfos['memory'],'price');
    $disk_price = $site->getProductOptionEntrie('11', $disk,'price') - $site->getProductOptionEntrie('11', $serverInfos['disk'],'price');

    $sum = $cpu_price + $memory_price+ $disk_price;

    $price = $sum * $site->getDiffInDays($serverInfos['expire_at']) / 30;
    $price = round($price,2);

    /*
     * calculate price for next renewal
     */
    $cpu_renew = $site->getProductOptionEntrie('9', $_POST['cpu'],'price');
    $memory_renew = $site->getProductOptionEntrie('10', $_POST['memory'],'price');
    $disk_renew = $site->getProductOptionEntrie('11', $_POST['disk'],'price');
    $price_renew = round($cpu_renew + $memory_renew + $disk_renew,2);

    if($amount < $price){
        $error = 'Du hast leider nicht genügend Guthaben';
    }

    if(empty($error)){
        $limits = [
            'memory' => $memory,
            'swap' => 0,
            'disk' => $disk,
            'io' => 500,
            'cpu' => $cpu * 100
        ];

        $feature_limits = [
            'databases' => 1,
            'allocations' => 1
        ];

        $response = $pterodactyl->updateServerBuild($serverInfos['service_id'], $limits, $feature_limits, $serverInfos['allocation_id']);
        if(!is_numeric($response->id)){
            $error = json_encode($response);
        }
    }

    if(empty($error)){

        $SQL = $db->prepare("UPDATE `pterodactyl_servers` SET `memory`=:memory, `cpu`=:cpu, `disk`=:disk, `price`=:price WHERE `id` = :id");
        $SQL->execute(array(":memory" => $memory, ":cpu" => $cpu, ":disk" => $disk, ":price" => $price_renew, ":id" => $serverInfos['id']));

        if($price > 0){
            $user->removeMoney($price, $userid);
            $user->addTransaction($userid,'-'.$price,'Gameserver upgrade');
            echo sendSuccess('Dein Server wurde geupgraded');
        } else {
            $price = str_replace('-','', $price);
            $user->addMoney($price, $userid);
            $user->addTransaction($userid, $price,'Gameserver downgrade');
            echo sendSuccess('Dein Server wurde downgraded');
        }

        header('refresh:3;url='.$helper->url().'manage/gameserver/'.$serverInfos['id']);
    } else {
        echo sendError($error);
    }

}