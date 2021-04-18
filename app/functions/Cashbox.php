<?php

$cashbox = new Cashbox();
class Cashbox extends Controller
{

    public function click($box_id, $ip_addr)
    {
        $SQL = self::db()->prepare('SELECT * FROM `cashbox_clicks` WHERE `box_id` = :box_id AND `ip_addr` = :ip_addr');
        $SQL->execute(array(":box_id" => $box_id, ":ip_addr" => $ip_addr));
        if($SQL->rowCount() == 0){
            $insert = self::db()->prepare('INSERT INTO `cashbox_clicks`(`box_id`, `ip_addr`) VALUES (?, ?)');
            $insert->execute(array($box_id, $ip_addr));
        }
    }

    public function countClicks($box_id)
    {
        $SQL = self::db()->prepare('SELECT * FROM `cashbox_clicks` WHERE `box_id` = :box_id');
        $SQL->execute(array(":box_id" => $box_id));
        return $SQL->rowCount();
    }

}