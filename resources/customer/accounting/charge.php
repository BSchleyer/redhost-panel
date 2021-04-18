<?php
$currPage = 'back_Guthaben aufladen';
include BASE_PATH.'app/controller/PageController.php';
include BASE_PATH.'app/manager/customer/payment/init.php';
include BASE_PATH.'app/manager/customer/payment/check_payments.php';

?>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="subheader py-2 py-lg-4 subheader-solid" id="kt_subheader">
        <div class="container-fluid d-flex align-items-center justify-content-between flex-wrap flex-sm-nowrap">
            <div class="d-flex align-items-center flex-wrap mr-2">
                <h5 class="text-dark font-weight-bold mt-2 mb-2 mr-5"><?= env('APP_NAME'); ?></h5>
                <div class="subheader-separator subheader-separator-ver mt-2 mb-2 mr-4 bg-gray-200"></div>
                <span class="text-muted font-weight-bold mr-4"><?= $currPageName; ?></span>
            </div>
        </div>
    </div>

    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="row">

                <div class="col-md-6">
                    <div class="card shadow mb-5">
                        <div class="card-body">

                            <form method="post">
                                <label for="amount">Betrag <small id="payment_fees"></small></label>
                                <input id="amount" class="form-control" value="1.00" name="amount" onkeyup="update();">

                                <br>

                                <label for="payment_method">Zahlungsmethode</label>
                                <select class="form-control" id="payment_method" name="payment_method" onchange="update();">
                                    <option data-method="paypal" value="paypal">paypal</option>
                                    <option data-method="paysafecard" value="paysafecard">paysafecard <?php $psc_fees = $helper->getSetting('psc_fees'); if($psc_fees != 0){ echo '('.$psc_fees.'% Zahlungsgebühren)'; } ?></option>
                                </select>

                                <div id="psc_code"></div>

                                <br>
                                <button type="submit" name="startPayment" class="btn btn-outline-primary font-weight-bolder"><i class="fas fa-wallet"></i> Guthaben aufladen</button><br><br>
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

                            <script>
                                function update() {
                                    var payment_method = $('#payment_method').val();
                                    var amount = $('#amount').val();
                                    if(payment_method == 'paysafecard'){
                                        var new_amount = (amount / 100 * (100 - <?= $psc_fees; ?>)).toFixed(2);
                                        $('#payment_fees').html('(Du erhälst '+new_amount+'€)');
                                    } else {
                                        $('#payment_fees').html('(Du erhälst '+amount+'€)');
                                    }
                                }
                                update();
                            </script>

                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow mb-5">
                        <div class="card-body">

                            <table id="dataTableLoad" class="table dt-responsive nowrap">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Beschreibung</th>
                                    <th>Betrag</th>
                                    <th>Datum</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $SQL = $db->prepare("SELECT * FROM `transactions` WHERE `user_id` = :user_id AND `state` = :state");
                                $SQL->execute(array(":user_id" => $userid, ":state" => 'success'));
                                if ($SQL->rowCount() != 0) {
                                    while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){ ?>
                                        <tr>
                                            <td><?= $row['id']; ?></td>
                                            <td><?= $row['desc']; ?></td>
                                            <td><?= $row['amount']; ?>€</td>
                                            <td><?= $helper->formatDate($row['created_at']); ?></td>
                                        </tr>
                                    <?php } } ?>
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>