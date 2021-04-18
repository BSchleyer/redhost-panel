<?php
$currPage = 'system_worker queue';
include BASE_PATH.'app/controller/PageController.php';

$server_id = $helper->protect($_GET['id']);

if(isset($_GET['id'])){
    $SQLGetServerInfos = $db->prepare("SELECT * FROM `vm_servers` WHERE `id` = :id");
    $SQLGetServerInfos -> execute(array(":id" => $server_id));
    $serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);

    if(is_null($serverInfos['venocix_id'])){
        if($serverInfos['type'] == 'LXC'){
            echo $lxc->getStatus($serverInfos['node_id'], $serverInfos['id']);
        } else {
            echo $kvm->getStatus($serverInfos['node_id'], $serverInfos['id']);
        }
    } else {
        $venocix_id = json_decode($serverInfos['venocix_id']);
        $vm_id = $venocix_id->result->output->vmid;
        echo json_encode($venocix->currentVMStatus($vm_id));
    }

} else {
    echo 'ERROR';
}