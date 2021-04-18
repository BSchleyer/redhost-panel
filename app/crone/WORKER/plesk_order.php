<?php

$username = $payload->data->username;
$mail = $payload->data->email;
$userid = $row['user_id'];
$db_price = $payload->data->price;
$runtime = $payload->data->runtime;
$planName = $payload->data->planName;
$domainName = $payload->data->domainName;

if(is_null($user->getDataById($userid,'plesk_uid'))) {
    $password = $helper->generateRandomString(25, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ~!*?_#^/$%@');
    $plesk_uid = $plesk->createUser($username, $username, $password, $mail);
    if (is_numeric($plesk_uid)) {

        $update = $db->prepare("UPDATE `users` SET `plesk_uid` = :plesk_uid, `plesk_password` = :plesk_password WHERE `id` = :user_id");
        $update->execute(array(":plesk_uid" => $plesk_uid, ":plesk_password" => $password, ":user_id" => $userid));

    } else {

        $update = $db->prepare("UPDATE `queue` SET `retries` = :retries, `error_log` = :error_log WHERE `id` = :id");
        $update->execute(array(":retries" => '255', ":error_log" => $plesk_uid, ":id" => $row['id']));
        die('error happend');

    }
} else {
    $plesk_uid = $user->getDataById($userid,'plesk_uid');
}


$date = new DateTime(null, new DateTimeZone('Europe/Berlin'));
$date->modify('+'.$runtime.' day');
$new_date = $date->format('Y-m-d H:i:s');


$password = $helper->generateRandomString(25,'0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ~!*?_#^/$%@');
//$domainName = 'web'.$plesk->getLast().rand(0,9).'-'.rand(0,9).'.'.$plesk->getHost()['domainName'];
$ftp_username = strtolower('ftp_'.$username.rand(0,9).'_'.$plesk->getLast());
$webspaceId = $plesk->create($domainName, $plesk->getHost()['ip'], $plesk_uid, $ftp_username, $password, $planName);


if(is_numeric($webspaceId)){
    $insert = $db->prepare("INSERT INTO `webspace`(`plan_id`, `user_id`, `ftp_name`, `ftp_password`, `domainName`, `webspace_id`, `state`, `expire_at`, `price`) VALUES (?,?,?,?,?,?,?,?,?)");
    $insert->execute(array($planName, $userid, $ftp_username, $password, $domainName, $webspaceId, 'active', $new_date, $db_price));
} else {
    $update = $db->prepare("UPDATE `queue` SET `retries` = :retries, `error_log` = :error_log WHERE `id` = :id");
    $update->execute(array(":retries" => '255', ":error_log" => $webspaceId, ":id" => $row['id']));
    die('error happend');
}