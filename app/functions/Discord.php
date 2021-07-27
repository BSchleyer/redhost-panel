<?php

$discord = new Discord();
class Discord extends Controller
{

    public function callWebhook($message, $webhookurl = null)
    {
        if(is_null($webhookurl)){
            $webhookurl = env('DISCORD_WEBHOOK_URL');
        }
        $timestamp = date("c", strtotime("now"));
        $json_data = json_encode([
            "content" => $message,
            "username" => env('DISCORD_NAME'),
            "avatar_url" => env('DISCORD_AVATAR_URL'),
            "tts" => false,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $ch = curl_init( $webhookurl );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec( $ch );
        curl_close( $ch );
    }

    public function sendPN($message, $id){
        $SQL = self::db()->prepare("INSERT INTO `discord_queue`(`user_id`, `message`) VALUES (:id,:message)");
        $SQL->execute(array(":id" => $id, ":message" => $message));
    }

}