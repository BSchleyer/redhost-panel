<?php

$post = json_decode(file_get_contents("php://input"), true);
$support_pin = $post['support_pin'];
$discord_id = $post['discord_id'];

$SQL = $db->prepare("SELECT * FROM `users` WHERE `s_pin` = :support_pin");
$SQL->execute(array(":support_pin" => $support_pin));
if($SQL->rowCount() == 1) {
    $row = $SQL->fetch(PDO::FETCH_ASSOC);

    array_push($success, 'Discord ID wurde gesetzt');
    $state = 'success';

    $SQL2 = $db->prepare("UPDATE `users` SET `discord_id` = :discord_id WHERE `s_pin` = :s_pin");
    $SQL2->execute(array(":discord_id" => $discord_id, ":s_pin" => $support_pin));

    $user->renewSupportPin($row['id']);

	  $res->data->productCount = $user->serviceCount($row['id']);
    $res->data->support_pin_used = $support_pin;
    $res->data->discord_id_used = $discord_id;
    $res->data->username = $row['username'];
} else {
    array_push($error, 'Kein Benutzer gefunden.');
    $state = 'error';

    $res->data->support_pin_used = $support_pin;
    $res->data->discord_id_used = $discord_id;
}
