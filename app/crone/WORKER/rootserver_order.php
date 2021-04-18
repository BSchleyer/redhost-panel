<?php

$username = $payload->data->username;
$mail = $payload->data->email;
$userid = $row['user_id'];
$db_price = $payload->data->price;
$runtime = $payload->data->runtime;

$rootpassword = $payload->data->rootpassword;
$hostname = $payload->data->hostname;
$serverOS = $payload->data->serverOS;

$cores = $payload->data->cores;
$memory = $payload->data->memory;
$disk = $payload->data->disk;
$ip_addrs = $payload->data->ip_addrs;
$addresses = $payload->data->addresses;

$node_id = $payload->data->node_id;
$disc_name = $payload->data->disc_name;
$api_name = $payload->data->api_name;
$traffic = $payload->data->traffic;
$pack_name = $payload->data->pack_name;

//dd($ip_addrs);

$serviceID = $site->getLastVMID();
//$task = $lxc->create($node_id, $serviceID, $serverOS, $cores, $memory, $rootpassword, $disk,'512', $ip_addrs, $addresses, $hostname, $disc_name);
//$SQL4 = $db->prepare("INSERT INTO `vm_tasks`(`service_id`, `task`) VALUES (:service_id, :task)");
//$SQL4->execute(array(":service_id" => $serviceID, ":task" => $task));

foreach ($ip_addrs as $key => $address) {
    $update = $db->prepare("UPDATE `ip_addresses` SET `service_id`=:service_id,`service_type`=:service_type WHERE `id`=:id");
    $update->execute(array(":service_id" => $serviceID, ":service_type" => 'VPS', ":id" => $address->id));
}

$date = new DateTime(null, new DateTimeZone('Europe/Berlin'));
$date->modify('+' . $runtime . ' day');
$expire_at = $date->format('Y-m-d H:i:s');

$SQLDB = $db;
$SQL5 = $SQLDB->prepare("INSERT INTO `vm_servers`(`user_id`, `hostname`, `password`, `template_id`, `node_id`, `cores`, `memory`, `disc`, `addresses`, `price`, `state`, `expire_at`, `disc_name`, `traffic`, `api_name`, `pack_name`, `type`) VALUES (:user_id,:hostname,:password,:template_id,:node_id,:cores,:memory,:disc,:addresses,:price,:state,:expire_at,:disc_name,:traffic,:api_name,:pack_name,:type)");
$SQL5->execute(array(":user_id" => $userid, ":hostname" => $hostname, ":password" => $rootpassword, ":template_id" => $serverOS, ":node_id" => $node_id, ":cores" => $cores, ":memory" => $memory, ":disc" => $disk, ":addresses" => $addresses, ":price" => $db_price, ":state" => 'PENDING', ":expire_at" => $expire_at, ":disc_name" => $disc_name, ":traffic" => $traffic, ":api_name" => $api_name, ":pack_name" => $pack_name, ":type" => 'KVM'));