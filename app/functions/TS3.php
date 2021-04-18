<?php

$ts3 = new TS3();

class TS3 extends Controller {

    function getStatus($node_id, $ts_port){

        try{
            $SQL = self::db()->prepare("SELECT * FROM `teamspeak_hosts` WHERE `id` = :id");
            $SQL -> execute(array(":id" => $node_id));
            $nodeInfos = $SQL -> fetch(PDO::FETCH_ASSOC);

            $uri = "serverquery://".$nodeInfos['login_name'].":".$nodeInfos['login_passwort']."@".$nodeInfos['login_ip'].":".$nodeInfos['login_port']."/?server_port=".$ts_port;
            $ts3_VirtualServer = TeamSpeak3::factory($uri);

            return 'ONLINE';

            $ts3_VirtualServer->serverDeselect();

        }catch(TeamSpeak3_Exception $e){
            return 'OFFLINE';
        }

    }

    function startServer($node_id, $ts_port, $sid){

        if($this->getStatus($node_id, $ts_port) == 'OFFLINE'){

            $SQL = self::db()->prepare("SELECT * FROM `teamspeak_hosts` WHERE `id` = :id");
            $SQL -> execute(array(":id" => $node_id));
            $nodeInfos = $SQL -> fetch(PDO::FETCH_ASSOC);

            $ts3_ServerInstance = TeamSpeak3::factory("serverquery://".$nodeInfos['login_name'].":".$nodeInfos['login_passwort']."@".$nodeInfos['login_ip'].":".$nodeInfos['login_port']."/");

            $ts3_ServerInstance->serverStart($sid);

        }

    }

    function stopServer($node_id, $ts_port, $sid){

        if($this->getStatus($node_id, $ts_port) == 'ONLINE'){

            sleep(2);

            $SQL = self::db()->prepare("SELECT * FROM `teamspeak_hosts` WHERE `id` = :id");
            $SQL -> execute(array(":id" => $node_id));
            $nodeInfos = $SQL -> fetch(PDO::FETCH_ASSOC);

            $ts3_ServerInstance = TeamSpeak3::factory("serverquery://".$nodeInfos['login_name'].":".$nodeInfos['login_passwort']."@".$nodeInfos['login_ip'].":".$nodeInfos['login_port']."/");
            $ts3_ServerInstance->serverStop($sid);

        }

    }

    function createServer($node_id, $max_slots, $server_port){

        $SQL = self::db()->prepare("SELECT * FROM `teamspeak_hosts` WHERE `id` = :id");
        $SQL -> execute(array(":id" => $node_id));
        $nodeInfos = $SQL -> fetch(PDO::FETCH_ASSOC);

        $ts3_ServerInstance = TeamSpeak3::factory("serverquery://".$nodeInfos['login_name'].":".$nodeInfos['login_passwort']."@".$nodeInfos['login_ip'].":".$nodeInfos['login_port']."/");

        $new_sid = $ts3_ServerInstance->serverCreate(array(
            "virtualserver_name"                    => "Teamspeak Hosted by ".Helper::siteName(),
            "virtualserver_maxclients"              => $max_slots,
            "virtualserver_port"                    => $server_port,
            "virtualserver_hostbutton_url"          => env('TEAMSPEAK_TOOLTIP_LINK'),       // Hostbutton Link
            "virtualserver_hostbutton_gfx_url"      => env('TEAMSPEAK_TOOLTIP_IMG'),        // Hostbutton Image
            "virtualserver_hostbutton_tooltip"      => env('TEAMSPEAK_TOOLTIP_HOVER'),      // Hostbutton Tooltip (Hover)
            "virtualserver_hostbanner_gfx_url"      => env('TEAMSPEAK_BANNER_IMG'),         // Banner Image
            "virtualserver_hostbanner_url"          => env('TEAMSPEAK_BANNER_LINK'),        // Banner Link
            "virtualserver_hostbanner_gfx_interval" => env('TEAMSPEAK_BANNER_INTERVAL'),    // Banner Refresh Interval
            //"virtualserver_hostbanner_mode"         => env('TEAMSPEAK_BANNER_MODE'),        // Banner Refresh Interval
        ));

        return $new_sid;

    }

    function deleteServer($node_id, $sid){

        $SQL = self::db()->prepare("SELECT * FROM `teamspeak_hosts` WHERE `id` = :id");
        $SQL -> execute(array(":id" => $node_id));
        $nodeInfos = $SQL -> fetch(PDO::FETCH_ASSOC);

        $ts3_ServerInstance = TeamSpeak3::factory("serverquery://".$nodeInfos['login_name'].":".$nodeInfos['login_passwort']."@".$nodeInfos['login_ip'].":".$nodeInfos['login_port']."/");

        $ts3_ServerInstance->serverDelete($sid);

    }

    public function changeSlots($node_id, $ts_port, $slots)
    {

        $SQL = self::db()->prepare("SELECT * FROM `teamspeak_hosts` WHERE `id` = :id");
        $SQL -> execute(array(":id" => $node_id));
        $nodeInfos = $SQL -> fetch(PDO::FETCH_ASSOC);

        $uri = "serverquery://".$nodeInfos['login_name'].":".$nodeInfos['login_passwort']."@".$nodeInfos['login_ip'].":".$nodeInfos['login_port']."/?server_port=".$ts_port;
        $ts3_VirtualServer = TeamSpeak3::factory($uri);

        $ts3_VirtualServer->modify([
            'virtualserver_maxclients' => $slots,
        ]);

    }

    public function snapshotCreate($node_id, $ts_port)
    {
        $SQL = self::db()->prepare("SELECT * FROM `teamspeak_hosts` WHERE `id` = :id");
        $SQL -> execute(array(":id" => $node_id));
        $nodeInfos = $SQL -> fetch(PDO::FETCH_ASSOC);

        $uri = "serverquery://".$nodeInfos['login_name'].":".$nodeInfos['login_passwort']."@".$nodeInfos['login_ip'].":".$nodeInfos['login_port']."/?server_port=".$ts_port;
        $ts3_VirtualServer = TeamSpeak3::factory($uri);

        return $ts3_VirtualServer->snapshotCreate(TeamSpeak3::SNAPSHOT_BASE64);
    }

    public function snapshotRestore($node_id, $ts_port, $files)
    {
        $SQL = self::db()->prepare("SELECT * FROM `teamspeak_hosts` WHERE `id` = :id");
        $SQL -> execute(array(":id" => $node_id));
        $nodeInfos = $SQL -> fetch(PDO::FETCH_ASSOC);

        $uri = "serverquery://".$nodeInfos['login_name'].":".$nodeInfos['login_passwort']."@".$nodeInfos['login_ip'].":".$nodeInfos['login_port']."/?server_port=".$ts_port;
        $ts3_VirtualServer = TeamSpeak3::factory($uri);

        return $ts3_VirtualServer->snapshotDeploy($files, TeamSpeak3::SNAPSHOT_BASE64);
    }

}