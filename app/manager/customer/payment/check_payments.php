<?php
$SQL = $db->prepare("SELECT * FROM `transactions` WHERE `user_id` = :user_id AND `state` = 'pending' AND `gateway` = 'paysafecard'");
$SQL->execute(array(":user_id" => $userid));
if ($SQL->rowCount() != 0) {
    while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)) {

        $fields = array(
            'checkPayment' => NULL,
            'tid' => $row['tid'],
        );

        $headers = array();
        $headers[] = "X-Api-Key: ".env('BOLTLAYER_API_KEY');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"https://boltlayer.com/api/v1/payment/check");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close ($ch);

        $response = json_decode($output);
        $status = $response->data->state;

        if($status == 'SUCCESS'){

            //$discord->callWebhook('Soeben wurden '.$row['amount'].'€ aufgeladen von '.$user->getDataById($userid,'username').' (PaySafeCard)');

            $SQL = $db->prepare("UPDATE `transactions` SET `state` = 'success' WHERE `id` = :id");
            $SQL -> execute(array(":id" => $row['id']));

            $percent = $helper->getSetting('psc_fees');
            if($percent == 0){
                $userGet = $row['amount'];

            } else {
                $userGet = round($row['amount'] / 100 * (100 - $percent),2);
//                $user->addTransaction($row['user_id'], $userGet,$percent.'% Zahlungsgebühr für die Zahlung mit paysafecard');
            }

            $user->addMoney($userGet, $row['user_id']);
            echo sendSuccess('Vielen Dank! Wir haben deine Zahlung erhalten');

        }

    }
}