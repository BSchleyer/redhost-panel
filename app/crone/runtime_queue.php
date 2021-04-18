<?php
$currPage = 'system_runtime queue';
include BASE_PATH.'app/controller/PageController.php';

//Time now
$date = new DateTime(null, new DateTimeZone('Europe/Berlin'));
$dateTimeNow = $date->format('Y-m-d H:i:s');

//Time minus 3 days
$dateMinus = new DateTime(null, new DateTimeZone('Europe/Berlin'));
$dateMinus->modify('-3 day');
$dateTimeMinus3Days = $dateMinus->format('Y-m-d H:i:s');

//Time plus 3 days
$datePlus = new DateTime(null, new DateTimeZone('Europe/Berlin'));
$datePlus->modify('+3 day');
$dateTimePlus3Days = $datePlus->format('Y-m-d H:i:s');

$key = $helper->protect($_GET['key']);
if($key == env('CRONE_KEY')){

    /* ======================================================================================================================================== */
    $vmserversEmail = $db->prepare("SELECT * FROM `vm_servers` WHERE `deleted_at` IS NULL");
    $vmserversEmail->execute();
    if ($vmserversEmail->rowCount() != 0) {
        while ($row = $vmserversEmail->fetch(PDO::FETCH_ASSOC)) {

            if($user->getDataById($row['user_id'],'mail_runtime')) {
                $diffInDays = $site->getDiffInDays($row['expire_at']);
                if ($diffInDays != $row['days']) {

                    if ($diffInDays == 3) {
                        $product_name = 'vServer';
                        include BASE_PATH . 'app/notifications/mail_templates/product/runout.php';
                        $mail_state = sendMail($user->getDataById($row['user_id'], 'email'), $user->getDataById($row['user_id'], 'username'), $mailContent, $mailSubject);
                    }

                    $SQL = $db->prepare("UPDATE `vm_servers` SET `days` = :days WHERE `id` = :id");
                    $SQL->execute(array(":days" => $diffInDays, ":id" => $row['id']));
                }
            }

        }
    }

    $vmserversDB = $db->prepare("SELECT * FROM `vm_servers` WHERE `expire_at` < :dateTimeNow AND `state` = 'ACTIVE' AND `type` = 'LXC'");
    $vmserversDB->execute(array(":dateTimeNow" => $dateTimeNow));
    if ($vmserversDB->rowCount() != 0) {
        while ($row = $vmserversDB->fetch(PDO::FETCH_ASSOC)) {

            $SQL = $db->prepare("UPDATE `vm_servers` SET `state`='SUSPENDED' WHERE `id` = :id");
            $SQL->execute(array(":id" => $row['id']));

            try {
                $lxc->shutdown($row['node_id'], $row['id']);
            }catch (Exception $e){
                //echo $e->getMessage();
            }

            echo 'Suspended vServer #'.$row['id'];

        }
    }
    $vmserversSuspendedDB = $db->prepare("SELECT * FROM `vm_servers` WHERE `expire_at` < :dateTimeMinusDays AND `state` = 'SUSPENDED' AND `type` = 'LXC'");
    $vmserversSuspendedDB->execute(array(":dateTimeMinusDays" => $dateTimeMinus3Days));
    if ($vmserversSuspendedDB->rowCount() != 0) {
        while ($row = $vmserversSuspendedDB->fetch(PDO::FETCH_ASSOC)) {

            $SQL = $db->prepare("UPDATE `vm_servers` SET `state`='DELETED', `deleted_at` = :deleted_at WHERE `id` = :id");
            $SQL->execute(array(":deleted_at" => $dateTimeNow, ":id" => $row['id']));

            $SQL3 = $db->prepare("SELECT * FROM `ip_addresses` WHERE `service_id` = :service_id");
            $SQL3->execute(array(":service_id" => $row['id']));
            if ($SQL3->rowCount() != 0) {
                while ($row3 = $SQL3->fetch(PDO::FETCH_ASSOC)) {

                    $ip_name = str_replace(".", "-", $row3['ip']);
                    $rdns = $ip_name.'ipv4.sedv-route.customer.schleyer.network';
                    $venocix->setRDNS($row3['ip'],$rdns);

                    $SQL4 = $db->prepare("UPDATE `ip_addresses` SET `rdns` = :rdns WHERE `ip` = :ip");
                    $SQL4->execute(array(':rdns' => $rdns, ':ip' => $row3['ip']));

                }
            }
			
			$SQL2 = $db->prepare("UPDATE `ip_addresses` SET `service_id` = NULL, `service_type` = NULL WHERE `service_id` = :id");
            $SQL2->execute(array(":id" => $row['id']));

            try {
               echo $lxc->shutdown($row['node_id'], $row['id']);
            }catch (Exception $e){
                echo $e->getMessage();
            }

            sleep(1);

            try {
                echo $lxc->deleteServer($row['node_id'], $row['id']);
            }catch (Exception $e){
                echo $e->getMessage();
            }

            echo 'Deleted vServer #'.$row['id'];

        }
    }
	
	
    $vmserversDB2 = $db->prepare("SELECT * FROM `vm_servers` WHERE `expire_at` < :dateTimeNow AND `state` = 'ACTIVE' AND `type` = 'KVM'");
    $vmserversDB2->execute(array(":dateTimeNow" => $dateTimeNow));
    if ($vmserversDB2->rowCount() != 0) {
        while ($row = $vmserversDB2->fetch(PDO::FETCH_ASSOC)) {

            $SQL = $db->prepare("UPDATE `vm_servers` SET `state`='SUSPENDED' WHERE `id` = :id");
            $SQL->execute(array(":id" => $row['id']));

            try {
                $kvm->shutdown($row['node_id'], $row['id']);
            }catch (Exception $e){
                //echo $e->getMessage();
            }

            echo 'Suspended vServer #'.$row['id'];

        }
    }
    /* ======================================================================================================================================== */

    /* ======================================================================================================================================== */
    $webspaceEmail = $db->prepare("SELECT * FROM `webspace` WHERE `deleted_at` IS NULL");
    $webspaceEmail->execute();
    if ($webspaceEmail->rowCount() != 0) {
        while ($row = $webspaceEmail->fetch(PDO::FETCH_ASSOC)) {

            if($user->getDataById($row['user_id'],'mail_runtime')){
                $diffInDays = $site->getDiffInDays($row['expire_at']);
                if($diffInDays != $row['days']){

                    if($diffInDays == 3){
                        $product_name = 'Webspace';
                        include BASE_PATH.'app/notifications/mail_templates/product/runout.php';
                        $mail_state = sendMail($user->getDataById($row['user_id'],'email'), $user->getDataById($row['user_id'],'username'), $mailContent, $mailSubject);
                    }

                    $SQL = $db->prepare("UPDATE `webspace` SET `days` = :days WHERE `id` = :id");
                    $SQL->execute(array(":days" => $diffInDays, ":id" => $row['id']));
                }
            }

        }
    }

    $webspaceDB = $db->prepare("SELECT * FROM `webspace` WHERE `expire_at` < :dateTimeNow AND `state` = 'active'");
    $webspaceDB->execute(array(":dateTimeNow" => $dateTimeNow));
    if ($webspaceDB->rowCount() != 0) {
        while ($row = $webspaceDB->fetch(PDO::FETCH_ASSOC)) {

            $SQL = $db->prepare("UPDATE `webspace` SET `state`='suspended' WHERE `id` = :id");
            $SQL->execute(array(":id" => $row['id']));

            echo 'Suspended Webspace #'.$row['id'];

        }
    }

    $webspaceSuspendedDB = $db->prepare("SELECT * FROM `webspace` WHERE `expire_at` < :dateTimeMinusDays AND `state` = 'suspended'");
    $webspaceSuspendedDB->execute(array(":dateTimeMinusDays" => $dateTimeMinus3Days));
    if ($webspaceSuspendedDB->rowCount() != 0) {
        while ($row = $webspaceSuspendedDB->fetch(PDO::FETCH_ASSOC)) {

            $SQL = $db->prepare("UPDATE `webspace` SET `state`='deleted', `deleted_at` = :deleted_at WHERE `id` = :id");
            $SQL->execute(array(":deleted_at" => $dateTimeNow, ":id" => $row['id']));

            try {
                $plesk->delete($row['webspace_id']);
            } catch (Exception $e){

            }

            echo 'Deleted Webspace #'.$row['id'];

        }
    }
    /* ======================================================================================================================================== */

    /* ======================================================================================================================================== */
    $teamspeakEmail = $db->prepare("SELECT * FROM `teamspeaks` WHERE `deleted_at` IS NULL");
    $teamspeakEmail->execute();
    if ($teamspeakEmail->rowCount() != 0) {
        while ($row = $teamspeakEmail->fetch(PDO::FETCH_ASSOC)) {

            if($user->getDataById($row['user_id'],'mail_runtime')) {
                $diffInDays = $site->getDiffInDays($row['expire_at']);
                if ($diffInDays != $row['days']) {

                    if ($diffInDays == 3) {
                        $product_name = 'Teamspeak';
                        include BASE_PATH . 'app/notifications/mail_templates/product/runout.php';
                        $mail_state = sendMail($user->getDataById($row['user_id'], 'email'), $user->getDataById($row['user_id'], 'username'), $mailContent, $mailSubject);
                    }

                    $SQL = $db->prepare("UPDATE `teamspeaks` SET `days` = :days WHERE `id` = :id");
                    $SQL->execute(array(":days" => $diffInDays, ":id" => $row['id']));
                }
            }

        }
    }

    $teamspeakDB = $db->prepare("SELECT * FROM `teamspeaks` WHERE `expire_at` < :dateTimeNow AND `state` = 'ACTIVE'");
    $teamspeakDB->execute(array(":dateTimeNow" => $dateTimeNow));
    if ($teamspeakDB->rowCount() != 0) {
        while ($row = $teamspeakDB->fetch(PDO::FETCH_ASSOC)) {

            $SQL = $db->prepare("UPDATE `teamspeaks` SET `state`='SUSPENDED' WHERE `id` = :id");
            $SQL->execute(array(":id" => $row['id']));

            try {
                $ts3->stopServer($row['node_id'], $row['teamspeak_port'], $row['sid']);
                $discord->callWebhook('Teamspeak wurde automatisch gestoppt (SUSPEND)');
            } catch (Exception $e){

            }

            echo 'Suspended Teamspeak #'.$row['id'];
        }
    }

    $teamspeakSuspendedDB = $db->prepare("SELECT * FROM `teamspeaks` WHERE `expire_at` < :dateTimeMinusDays AND `state` = 'SUSPENDED'");
    $teamspeakSuspendedDB->execute(array(":dateTimeMinusDays" => $dateTimeMinus3Days));
    if ($teamspeakSuspendedDB->rowCount() != 0) {
        while ($row = $teamspeakSuspendedDB->fetch(PDO::FETCH_ASSOC)) {

            $SQL = $db->prepare("UPDATE `teamspeaks` SET `state`='DELETED', `deleted_at` = :deleted_at WHERE `id` = :id");
            $SQL->execute(array(":deleted_at" => $dateTimeNow, ":id" => $row['id']));

            try {
                $ts3->deleteServer($row['node_id'], $row['sid']);
                $discord->callWebhook('Teamspeak wurde automatisch gestoppt (DELETE)');
            } catch (Exception $e){

            }

            echo 'Deleted Teamspeak #'.$row['id'];

        }
    }
    /* ======================================================================================================================================== */

    /* ======================================================================================================================================== */
    $webspaceEmail = $db->prepare("SELECT * FROM `pterodactyl_servers` WHERE `deleted_at` IS NULL");
    $webspaceEmail->execute();
    if ($webspaceEmail->rowCount() != 0) {
        while ($row = $webspaceEmail->fetch(PDO::FETCH_ASSOC)) {

            if($user->getDataById($row['user_id'],'mail_runtime')){
                $diffInDays = $site->getDiffInDays($row['expire_at']);
                if($diffInDays != $row['days']){

                    if($diffInDays == 3){
                        $product_name = 'Gameserver';
                        include BASE_PATH.'app/notifications/mail_templates/product/runout.php';
                        $mail_state = sendMail($user->getDataById($row['user_id'],'email'), $user->getDataById($row['user_id'],'username'), $mailContent, $mailSubject);
                    }

                    $SQL = $db->prepare("UPDATE `pterodactyl_servers` SET `days` = :days WHERE `id` = :id");
                    $SQL->execute(array(":days" => $diffInDays, ":id" => $row['id']));
                }
            }

        }
    }

    $webspaceDB = $db->prepare("SELECT * FROM `pterodactyl_servers` WHERE `expire_at` < :dateTimeNow AND `state` = 'active'");
    $webspaceDB->execute(array(":dateTimeNow" => $dateTimeNow));
    if ($webspaceDB->rowCount() != 0) {
        while ($row = $webspaceDB->fetch(PDO::FETCH_ASSOC)) {

            $SQL = $db->prepare("UPDATE `pterodactyl_servers` SET `state`='suspended' WHERE `id` = :id");
            $SQL->execute(array(":id" => $row['id']));

            try {
                $pterodactyl->suspend($row['service_id']);
            } catch (Exception $e){

            }

            echo 'Suspended Gameserver #'.$row['id'];

        }
    }

    $webspaceSuspendedDB = $db->prepare("SELECT * FROM `pterodactyl_servers` WHERE `expire_at` < :dateTimeMinusDays AND `state` = 'suspended'");
    $webspaceSuspendedDB->execute(array(":dateTimeMinusDays" => $dateTimeMinus3Days));
    if ($webspaceSuspendedDB->rowCount() != 0) {
        while ($row = $webspaceSuspendedDB->fetch(PDO::FETCH_ASSOC)) {

            $SQL = $db->prepare("UPDATE `pterodactyl_servers` SET `state`='deleted', `deleted_at` = :deleted_at WHERE `id` = :id");
            $SQL->execute(array(":deleted_at" => $dateTimeNow, ":id" => $row['id']));

            try {
                $pterodactyl->delete($row['service_id']);
            } catch (Exception $e){

            }

            echo 'Deleted Gameserver #'.$row['id'];

        }
    }
    /* ======================================================================================================================================== */

    die('nothing todo');

} else {
    include BASE_PATH.'resources/sites/404.php';
}