<?php

$username = $payload->data->username;
$mail = $payload->data->email;
$userid = $row['user_id'];
$softwareSQL = ($payload->data->softwareSQL);
$serverInfos = ($payload->data->serverInfos);

//dd($softwareSQL->url);

$lxc->exec($serverInfos->node_id, $serverInfos->id,'wget '.$softwareSQL->url);
sleep(5);
$lxc->exec($serverInfos->node_id, $serverInfos->id,'chmod 777 '.$softwareSQL->file_name);
sleep(5);
$lxc->exec($serverInfos->node_id, $serverInfos->id,'nohup bash '.$softwareSQL->file_name.' >/dev/null 2>&1 &');
//$lxc->exec($serverInfos->node_id, $serverInfos->id,'apt-get install screen -y');
//$lxc->exec($serverInfos->node_id, $serverInfos->id,'rm '.$softwareSQL->file_name);