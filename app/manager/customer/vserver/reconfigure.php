<?php

$id = $helper->protect($_GET['id']);

$SQLGetServerInfos = $db->prepare("SELECT * FROM `vm_servers` WHERE `id` = :id");
$SQLGetServerInfos -> execute(array(":id" => $id));
$serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);

if(!is_null($serverInfos['pack_name'])){
    header('Location: '.$helper->url().'manage/vserver/'.$id);
    die();
}

if(!($serverInfos['deleted_at'] == NULL)){
    header('Location: '.$helper->url().'order/vserver');
    die();
}

if($userid != $serverInfos['user_id']){
    die(header('Location: '.$helper->url().'manage/vserver'));
}

if($serverInfos['state'] == 'ACTIVE'){
    $state = 'Aktiv';
} elseif($serverInfos['state'] == 'SUSPENDED'){
    $state = 'Suspediert';
} elseif($serverInfos['state'] == 'DELETED'){
    $state = 'Gelöscht';
}

if(isset($_POST['cores'])){
    $error = null;

    if($site->getDiffInDays($serverInfos['expire_at']) < 5){
        $error = 'Bitte verlängere zuerst deinen Dienst';
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
    if($site->productOptionEntrieExist('1', $_POST['cores']) == false){
        $error = 'cores option entry does not exists';
    }
    if($site->productOptionEntrieExist('2', $_POST['memory']) == false){
        $error = 'memory option entry does not exists';
    }
    if($site->productOptionEntrieExist('3', $_POST['disk']) == false){
        $error = 'disk option entry does not exists';
    }
    if($site->productOptionEntrieExist('4', $_POST['addresses']) == false){
        $error = 'cores option entry does not exists';
    }
    $cores = $_POST['cores'];
    $memory = $_POST['memory'];
    $disk = $_POST['disk'];
    $addresses = $_POST['addresses'];

    if($disk < $serverInfos['disc']){
        $error = 'Ein Downgrade der Festplatte ist Technisch leider nicht möglich';
    }

    if($addresses < $serverInfos['addresses']){
        $error = 'Ein Downgrade der IP-Adressen ist leider nicht möglich';
    }

    /*
    * calculate the price to pay
    */
    $sum = $site->getProductOptionEntrie('1', $_POST['cores'],'price') - $site->getProductOptionEntrie('1', $serverInfos['cores'],'price')
        +$site->getProductOptionEntrie('2', $_POST['memory'],'price') - $site->getProductOptionEntrie('2', $serverInfos['memory'],'price')
        +$site->getProductOptionEntrie('3', $_POST['disk'],'price') - $site->getProductOptionEntrie('3', $serverInfos['disc'],'price')
        +$site->getProductOptionEntrie('4', $_POST['addresses'],'price') - $site->getProductOptionEntrie('4', $serverInfos['addresses'],'price');
    $price = $sum * $site->getDiffInDays($serverInfos['expire_at']) / 30;
    $price = round($price,2);

    /*
     * calculate price for next renewal
     */
    $price_renew = $site->getProductOptionEntrie('1', $_POST['cores'],'price')
        +$site->getProductOptionEntrie('2', $_POST['memory'],'price')
        +$site->getProductOptionEntrie('3', $_POST['disk'],'price')
        +$site->getProductOptionEntrie('4', $_POST['addresses'],'price');
    $price_renew = round($price_renew,2);


    if($amount < $price){
        $error = 'Du hast leider nicht genügend Guthaben';
    }

    $correctNetwork = false;
    if($addresses != $serverInfos['addresses']){
        $addressesCount = $addresses - $serverInfos['addresses'];

        $i = 1;
        $SQL = $db->prepare("SELECT * FROM `ip_addresses` WHERE `service_id` IS NULL");
        $SQL->execute();
        if ($SQL->rowCount() > $addresses) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {
                if($i <= $addressesCount){
                    $update = $db->prepare("UPDATE `ip_addresses` SET `service_id`=:service_id,`service_type`=:service_type WHERE `id`=:id");
                    $update->execute(array(":service_id" => $serverInfos['id'], ":service_type" => 'VPS', ":id" => $row['id']));
                    $i++;
                }
            }

            $correctNetwork = true;
        } else {
            $error = 'Es sind leider nicht mehr genügend IP-Adressen verfügbar';
        }
    }

    if(empty($error)){

        if($correctNetwork){
            $task = $lxc->correctNetwork($serverInfos['node_id'], $serverInfos['id']);
            $SQL = $db->prepare("INSERT INTO `vm_tasks`(`service_id`, `task`) VALUES (:service_id, :task)");
            $SQL->execute(array(":service_id" => $serverInfos['id'], ":task" => $task));
        }

        if($cores != $serverInfos['cores']){
            $task = $lxc->correctCores($serverInfos['node_id'], $serverInfos['id'], $cores);
            $SQL = $db->prepare("INSERT INTO `vm_tasks`(`service_id`, `task`) VALUES (:service_id, :task)");
            $SQL->execute(array(":service_id" => $serverInfos['id'], ":task" => $task));
        }

        if($memory != $serverInfos['memory']){
            $task = $lxc->correctMemory($serverInfos['node_id'], $serverInfos['id'], $memory);
            $SQL = $db->prepare("INSERT INTO `vm_tasks`(`service_id`, `task`) VALUES (:service_id, :task)");
            $SQL->execute(array(":service_id" => $serverInfos['id'], ":task" => $task));
        }

        if($disk != $serverInfos['disc']){
            $task = $lxc->correctDisk($serverInfos['node_id'], $serverInfos['id'], $disk);
            $SQL = $db->prepare("INSERT INTO `vm_tasks`(`service_id`, `task`) VALUES (:service_id, :task)");
            $SQL->execute(array(":service_id" => $serverInfos['id'], ":task" => $task));
        }

        $SQL = $db->prepare("UPDATE `vm_servers` SET `cores`=:cores,`memory`=:memory,`disc`=:disc,`addresses`=:addresses,`price`=:price WHERE `id` = :id");
        $SQL->execute(array(":cores" => $cores, ":memory" => $memory, ":disc" => $disk, ":addresses" => $addresses, ":price" => $price_renew, ":id" => $serverInfos['id']));

        if($price > 0){
            $user->removeMoney($price, $userid);
            $user->addTransaction($userid,'-'.$price,'vServer upgrade');
            echo sendSuccess('Dein vServer wurde geupgraded');
        } else {
            $price = str_replace('-','', $price);
            $user->addMoney($price, $userid);
            $user->addTransaction($userid, $price,'vServer downgrade');
            echo sendSuccess('Dein vServer wurde downgraded');
        }

        header('refresh:3;url='.$helper->url().'manage/vserver/'.$serverInfos['id']);
    } else {
        echo sendError($error);
    }

}