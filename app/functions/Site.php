<?php

$site = new Site();
$db = $helper->db();

class Site extends Controller
{

    public function currentUrl()
    {
        $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        return $actual_link;
    }

    public function getProductPrice($product_id)
    {
        $SQL = self::db()->prepare('SELECT * FROM `product_prices` WHERE `name` = :name');
        $SQL->execute(array(":name" => $product_id));
        $response = $SQL->fetch(PDO::FETCH_ASSOC);

        return $response['price'];
    }

    public function getProductDiscount($product_id, $data)
    {
        $SQL = self::db()->prepare('SELECT * FROM `product_discounts` WHERE `product_id` = :product_id');
        $SQL->execute(array(":product_id" => $product_id));
        $response = $SQL->fetch(PDO::FETCH_ASSOC);

        return $response[$data];
    }

    public function isTS3PortAviable($node_id, $port){
        $SQL = self::db()->prepare("SELECT * FROM `teamspeaks` WHERE `node_id` = :node_id AND `teamspeak_port` = :port AND `deleted_at` IS NULL");
        $SQL->execute(array(":node_id" => $node_id, ":port" => $port));
        if($SQL->rowCount() == 0){
            return true;
        } else {
            return false;
        }
    }

    public function addTransaction($user_id, $gateway, $state, $amount, $desc, $tid){
        $SQL = self::db()->prepare("INSERT INTO `transactions`(`user_id`, `gateway`, `state`, `amount`, `desc`, `tid`) VALUES (:user_id,:gateway,:state,:amount,:desc,:tid)");
        $SQL->execute(array(':user_id' => $user_id, ':gateway' => $gateway, ':state' => $state, ':amount' => $amount, ':desc' => $desc, ':tid' => $tid));
    }

    public function getDiffInDays($dateTime){
        $datetime1 = new DateTime(null, new DateTimeZone('Europe/Berlin'));
        $datetime2 = new DateTime($dateTime, new DateTimeZone('Europe/Berlin'));
        $interval = $datetime1->diff($datetime2);
        return $interval->format('%a');
    }

    public function productOptionEntrieExist($option_id, $value)
    {

        $SQL = self::db()->prepare("SELECT * FROM `product_option_entries` WHERE `option_id` = :option_id AND `value` = :value");
        $SQL->execute(array(':option_id' => $option_id, ':value' => $value));
        if($SQL->rowCount() == 1){
            return true;
        } else {
            return false;
        }

    }

    public function getProductOptionEntrie($option_id, $value, $type)
    {

        $SQL = self::db()->prepare("SELECT * FROM `product_option_entries` WHERE `option_id` = :option_id AND `value` = :value");
        $SQL->execute(array(':option_id' => $option_id, ':value' => $value));
        $response = $SQL -> fetch(PDO::FETCH_ASSOC);
        return $response[$type];

    }

    public function validateRootserverOS($os_id)
    {

        $SQL = self::db()->prepare("SELECT * FROM `vm_server_os` WHERE `id` = :id");
        $SQL->execute(array(':id' => $os_id));
        if($SQL->rowCount() == 1){
            return true;
        } else {
            return false;
        }

    }

    public function getLastVMID()
    {
        $SQL = self::db()->prepare("SELECT * FROM `vm_servers` ORDER BY `id` DESC LIMIT 1;");
        $SQL->execute();
        if($SQL->rowCount() == 0){
            return '100';
        }
        $response = $SQL->fetch(PDO::FETCH_ASSOC);
        return $response['id']+1;
    }

    public function getAddressesFromServer($service_id)
    {
        $SQLSelectServers = self::db()->prepare("SELECT * FROM `ip_addresses` WHERE `service_id` = :service_id");
        $SQLSelectServers->execute(array(":service_id" => $service_id));
        if ($SQLSelectServers->rowCount() != 0) {
            while ($row = $SQLSelectServers->fetch(PDO::FETCH_ASSOC)) {
                $ips[] = $row;
            }
            return $ips;
        }
        return 'Keine IPs gefunden';
    }

    public function getMainAddressFromServer($service_id, $type)
    {
        $SQLSelectServers = self::db()->prepare("SELECT * FROM `ip_addresses` WHERE `service_id` = :service_id ORDER BY `id` DESC LIMIT 1;");
        $SQLSelectServers->execute(array(":service_id" => $service_id));
        if ($SQLSelectServers->rowCount() != 0) {
            while ($row = $SQLSelectServers->fetch(PDO::FETCH_ASSOC)) {
                return $row[$type];
            }
        }

        return null;
    }

    public function getIPAddrOwner($ip_addr){
        $SQL = self::db()->prepare("SELECT * FROM `ip_addresses` WHERE `ip` = :ip_addr");
        $SQL->execute(array(":ip_addr" => $ip_addr));
        $addrInfos = $SQL->fetch(PDO::FETCH_ASSOC);

        if($addrInfos['service_type'] == 'VPS'){
            $getIPInfos = self::db()->prepare("SELECT * FROM `vm_servers` WHERE `id` = :id");
            $getIPInfos->execute(array(":id" => $addrInfos['service_id']));
            $ipInfos = $getIPInfos->fetch(PDO::FETCH_ASSOC);

            return $ipInfos['user_id'];
        } else {
            return null;
        }
    }

    public function maintenance($message = null)
    {

        if(is_null($message)){

            $SQL = self::db()->prepare("UPDATE `settings` SET `maintenance` = NULL, `login` = '1', `register` = '1'");
            $SQL->execute();

            return true;
        }

        if(!empty($message)){

            $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `role` = :role");
            $SQL->execute(array(":role" => 'customer'));
            if ($SQL->rowCount() != 0) {
                while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {
                    User::generateSessionToken($row['username']);
                }
            }

            $SQL = self::db()->prepare("UPDATE `settings` SET `maintenance` = :maintenance, `login` = '0', `register` = '0'");
            $SQL->execute(array(":maintenance" => $message));

            return true;
        }

        return false;
    }

    public function getWelcomeText($time){
        if($time >= 5 && $time <= 10){
            return '🌄 Guten Morgen';
        } elseif($time >= 10 && $time <= 12) {
            return '☀️ Guten Vormittag';
        } elseif($time >= 12 && $time <= 16) {
            return '🌞 Guten Mittag';
        } elseif($time >= 16 && $time <= 23) {
            return '🌇 Guten Abend';
        } elseif($time >= 23 || $time >= 0 && $time <= 5) {
            return '☕ Howdy Nachteule #Nachtaktiv';
        } else {
            return 'Cloud not get time! Bitte melde das einem Supporter';
        }
    }

    public function validateCaptcha($h_captcha_response){
        $data = array(
            'secret' => env('H_CAPTCHA_SECRET_KEY'),
            'response' => $h_captcha_response
        );
        $verify = curl_init();
        curl_setopt($verify, CURLOPT_URL, "https://hcaptcha.com/siteverify");
        curl_setopt($verify, CURLOPT_POST, true);
        curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($verify);
        // var_dump($response);
        $responseData = json_decode($response);

//        return $responseData;

        if($responseData->success) {
            // your success code goes here
            return true;
        } else {
            // return error to user; they did not pass
            return false;
        }
    }

    public function generateCSRF($length = 28)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


}