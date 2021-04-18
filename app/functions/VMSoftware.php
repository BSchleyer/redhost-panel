<?php

$vmsoftware = new VMSoftware();
class VMSoftware extends Controller
{

    public function addInstall($vm_id, $type)
    {
        $SQL = self::db()->prepare("INSERT INTO `vm_software_tasks`(`vm_id`, `type`) VALUES (?, ?)");
        $SQL->execute(array($vm_id, $type));
    }

    public function getOpenInstalls($vm_id)
    {
        $datePlus = new DateTime(null, new DateTimeZone('Europe/Berlin'));
        $datePlus->modify('-3 minutes');
        $dateMinute = $datePlus->format('Y-m-d H:i:s');

        $SQL = self::db()->prepare("SELECT * FROM `vm_software_tasks` WHERE `created_at` > :dateMinute AND `vm_id` = :vm_id");
        $SQL->execute(array(":dateMinute" => $dateMinute, ":vm_id" => $vm_id));
        if($SQL->rowCount() != 0){
            return true;
        } else {
            return false;
        }
    }

}