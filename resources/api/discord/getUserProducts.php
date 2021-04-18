<?php
array_push($success, 'Kundenprodukte wurden geladen');
$state = 'success';

$support_pin = $helper->protect($_POST['support_pin']);

$SQL = $db->prepare("SELECT * FROM `users` WHERE `s_pin` = :s_pin");
$SQL->execute(array(":s_pin" => $support_pin));
$userInfos = $SQL->fetch(PDO::FETCH_ASSOC);

$res->data->productCount = $user->serviceCount($userInfos['id']);