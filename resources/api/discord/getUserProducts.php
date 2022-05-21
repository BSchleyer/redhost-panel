<?php

$post = json_decode(file_get_contents("php://input"),true);
$discord_id = $post['discord_id'];

$SQL = $db->prepare("SELECT * FROM `users` WHERE `discord_id` = :discord_id");
$SQL->execute(array(":discord_id" => $discord_id));
if($SQL->rowCount() == 1) {
    $row = $SQL->fetch(PDO::FETCH_ASSOC);

    array_push($success, 'Produkte wurden geladen');
    $state = 'success';

    $user->renewSupportPin($row['id']);

	  $res->data->productCount = $user->activeProducts($userInfos['id']);
    $res->data->discord_id_used = $discord_id;
    $res->data->username = $row['username'];
} else {
    array_push($error, 'Kein Benutzer gefunden.');
    $state = 'error';
	
    $res->data->discord_id_used = $discord_id;
}
