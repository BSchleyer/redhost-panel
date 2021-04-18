<?php
$currPage = 'back_Spenden_hidehead';
include BASE_PATH.'app/controller/PageController.php';

$userid = $_GET['id'];

$SQL = $db->prepare("SELECT * FROM `users` WHERE `id` = :id");
$SQL->execute(array(':id' => $userid));
$userData = $SQL -> fetch(PDO::FETCH_ASSOC);

if($userData['cashbox'] == 'active'){
    if(!$user->sessionExists($_COOKIE['session_token'])){
        header('Location: '.env('URL'));
        die();
    }
}

if($SQL->rowCount() != 1){
    header('Location: '.env('URL'));
    die();
}

$cashbox->click($userid, $user->getIP());

include BASE_PATH.'app/manager/customer/payment/init.php';
include BASE_PATH.'app/manager/customer/payment/check_payments.php';
?>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="row">

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center">

                            <?php if(!is_null($user->getDataById($userid,'projectlogo'))){ ?>
                                <img src="<?= $user->getDataById($userid,'projectlogo'); ?>">
                            <?php } ?>

                            <?php if(!is_null($user->getDataById($userid,'projectname'))){ ?>
                                <br>
                                <br>
                                <h1><?= $user->getDataById($userid,'projectname'); ?></h1>
                            <?php } ?>
                            
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">

                            <h3 class="text-center">Guthaben spenden an <?= $userData['username']; ?></h3>

                            <br>

                            <form method="post">
                                <label for="amount">Betrag</label>
                                <input id="amount" class="form-control" value="1.00" name="amount">

                                <br>

                                <label for="payment_method">Zahlungsmethode</label>
                                <select class="form-control" id="payment_method" name="payment_method">
                                    <option data-method="paypal" value="paypalDonate">paypal</option>
                                    <option data-method="paysafecard" value="paysafecard">paysafecard <?php $psc_fees = $helper->getSetting('psc_fees'); if($psc_fees != 0){ echo '('.$psc_fees.'% Zahlungsgebühren)'; } ?></option>
                                </select>

                                <div id="psc_code"></div>

                                <br>
                                <button type="submit" name="startPayment" class="btn btn-outline-primary"><b>Guthaben spenden</b></button><br><br>
                                <center>
                                    <font size="2">
                                        <p>
                                        <b>Hinweis:</b> Es ist kein Abo. Der Betrag wird nur einmalig fällig,<br>
                                        es entstehen <u>keine</u> weiteren Kosten. Keine Kündigung notwendig!<br>
                                        Mit dieser Zahlung wird nur das Guthaben des Kundenkontos aufgeladen.<br>
                                        Guthaben kann <u>nicht</u> wieder ausgezahlt werden. (siehe <a target="_blank" href="https://cp.red-host.eu/agb">AGBs</a> §3.3)
                                        </p>
                                    </font>
                                </center>
                            </form>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>