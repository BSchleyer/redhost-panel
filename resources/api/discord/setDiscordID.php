<?php
array_push($success, 'Discord ID wurde gesetzt');
$state = 'success';

$support_pin = $helper->protect($_POST['support_pin']);

$SQL = $db->prepare("UPDATE `users` SET `discord_id` = :discord_id WHERE `s_pin` = :s_pin");
$SQL->execute(array(":discord_id" => $_POST['discord_id'], ":s_pin" => $support_pin));

$res->data->discord_id = $_POST['discord_id'];