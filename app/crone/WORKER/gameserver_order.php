<?php

$username = $payload->data->username;
$mail = $payload->data->email;
$userid = $row['user_id'];
$db_price = $payload->data->price;
$runtime = $payload->data->runtime;
$memory = $payload->data->memory;
$disk = $payload->data->disk;
$cpu = $payload->data->cpu;

if(is_null($user->getDataByUsername($username,'pterodactyl_id'))){
    $password = $helper->generateRandomString(25,'0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ~!*?_#^/$%@');
    $pterodactyl_id = $pterodactyl->createUser($userid,'game'.$userid, $mail, $username,'none',$password);
    $id_data = ($pterodactyl_id);
    $id = $id_data->id;
    if(is_numeric($id)){
        $SQL = $db->prepare("UPDATE `users` SET `pterodactyl_id` = :pterodactyl_id, `pterodactyl_password` = :pterodactyl_password WHERE `id` = :user_id");
        $SQL->execute(array(":pterodactyl_id" => $id, ":pterodactyl_password" => $password, ":user_id" => $userid));
    } else {
        $update = $db->prepare("UPDATE `queue` SET `retries` = :retries, `error_log` = :error_log WHERE `id` = :id");
        $update->execute(array(":retries" => '255', ":error_log" => json_encode($pterodactyl_id), ":id" => $row['id']));
        die('error happend');
    }
}

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
$response = $pterodactyl->create('GameServer by RED-Host.EU', $user->getDataById($userid, 'pterodactyl_id'),5, $limits, $feature_limits);

if(!is_numeric($response->id)){
    $update = $db->prepare("UPDATE `queue` SET `retries` = :retries, `error_log` = :error_log WHERE `id` = :id");
    $update->execute(array(":retries" => '255', ":error_log" => json_encode($response), ":id" => $row['id']));
    die('error happend');
} else {

    $date = new DateTime(null, new DateTimeZone('Europe/Berlin'));
    $date->modify('+' . $runtime . ' day');
    $expire_at = $date->format('Y-m-d H:i:s');

    $response_data = ($response);
    $SQL = $db->prepare("INSERT INTO `pterodactyl_servers`(`user_id`, `service_id`, `uuid`, `identifier`, `memory`, `cpu`, `disk`, `allocation_id`, `price`, `state`, `expire_at`) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $SQL->execute(array($userid, $response_data->id, $response_data->uuid, $response_data->identifier, $memory, $cpu, $disk, $response_data->allocation, $db_price, 'active', $expire_at));

}