<?php

if(isset($_POST['startPayment'])){

    $error = null;

    if(empty($_POST['amount'])){
        $error = 'Bitte gebe einen Betrag ein';
    }

    if(!is_numeric($_POST['amount'])){
        $error = 'Bitte gebe einen Betrag ein (Zahl)';
    }

    $payment_method = $_POST['payment_method'];
    if(empty($payment_method)){
        $error = 'Bitte wähle eine Zahlungsmethode';
    }

    if($_POST['amount'] < 1){
        $error = 'Bitte gebe einen Betrag über 1.00€ ein';
    }

    if($_POST['amount'] > 500){
        $error = 'Bitte gebe einen Betrag unter 500.00€ ein';
    }

    if(empty($error)){

        if($payment_method == 'paypal'){

            require BASE_PATH.'app/manager/customer/payment/functions.php';

            $paypalUrl = $enableSandbox ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';

            $itemName = 'Guthabenaufladung | kd'.$userid;
            $itemAmount = $_POST['amount'];

            $data = [];

            $data['business'] = $paypalConfig['email'];

            $data['return'] = stripslashes($paypalConfig['return_url']);
            $data['cancel_return'] = stripslashes($paypalConfig['cancel_url']);
            $data['notify_url'] = stripslashes($paypalConfig['notify_url']);

            $data['item_name'] = $itemName;
            $data['amount'] = $itemAmount;
            $data['currency_code'] = 'EUR';
            $data['custom'] = $_COOKIE['session_token'];

            $queryString = http_build_query($data);
            header('location:' . $paypalUrl . '?cmd=_xclick&' . $queryString);
            die();

        } elseif($payment_method == 'paypalDonate'){

            require BASE_PATH.'app/manager/customer/payment/functions.php';

            $paypalUrl = $enableSandbox ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';

            $itemName = 'Guthabenaufladung | kd'.$userid;
            $itemAmount = $_POST['amount'];

            $data = [];

            $data['business'] = $paypalConfig['email'];

            $data['return'] = stripslashes($paypalConfig['return_url']);
            $data['cancel_return'] = stripslashes($paypalConfig['cancel_url']);
            $data['notify_url'] = stripslashes($paypalConfig['notify_url']);

            $data['item_name'] = $itemName;
            $data['amount'] = $itemAmount;
            $data['currency_code'] = 'EUR';
            $data['custom'] = $userData['session_token'];

            $queryString = http_build_query($data);
            header('location:' . $paypalUrl . '?cmd=_xclick&' . $queryString);
            die();

        } elseif($payment_method == 'paysafecard'){

            $fields = array(
                'startPayment' => NULL,
                'payment_method' => $_POST['payment_method'],
                'payment_type' => 'json',
                'amount' => $_POST['amount'],
                'success_url' => $helper->url().'accounting/charge',
                'failure_url' => $helper->url().'accounting/charge',
            );

            $headers = array();
            $headers[] = "X-Api-Key: ".env('BOLTLAYER_API_KEY');

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL,"https://boltlayer.com/api/v1/payment/create");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $output = curl_exec($ch);
            curl_close ($ch);

            $response = json_decode($output);
            $tid = $response->data->tid;

            $site->addTransaction($userid, $_POST['payment_method'],'pending', $_POST['amount'],'Guthabenaufladung mit '.$_POST['payment_method'], $tid);
            header('Location: '.$response->data->paymentlink);

//            if(!empty($_POST['psc_code'])){
//                $site->addTransaction($userid, $payment_method,'pending', $_POST['amount'],'Guthabenaufladung mit '.$_POST['payment_method'], $_POST['psc_code']);
//                echo sendInfo('Vielen Dank! Wir prüfen deine Zahlung schnellstmöglich');
//            } else {
//                echo sendError('Bitte gebe einen PSC Code an');
//            }
        } else {
            echo sendError('Ungültige Zahlungsmethode');
        }

    } else {
        echo sendError($error);
    }

}