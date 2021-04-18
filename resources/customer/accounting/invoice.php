<?php
$currPage = 'back_Rechnung_hidelayout';
include BASE_PATH.'app/controller/PageController.php';

$id = $helper->protect($_GET['id']);

$SQL = $db->prepare("SELECT * FROM `user_transactions` WHERE `id` = :id");
$SQL -> execute(array(":id" => $id));
$invoice = $SQL -> fetch(PDO::FETCH_ASSOC);

if($invoice['user_id'] != $userid){
    header('Location: '.env('URL').'dashboard');
    die();
}

$firstname = $user->getDataById($userid,'firstname');
$lastname = $user->getDataById($userid,'lastname');
$street = $user->getDataById($userid,'street');
$number = $user->getDataById($userid,'number');
$postcode = $user->getDataById($userid,'postcode');
$city = $user->getDataById($userid,'city');
$country = $user->getDataById($userid,'country');
$amount = str_replace('-','', $invoice['amount']);

?>
<br>
<br>
<div class="d-flex flex-column-fluid">

    <div class="container">

        <div class="card card-custom overflow-hidden shadow mb-5">
            <div class="card-body p-0">

                <div class="row justify-content-center bgi-size-cover bgi-no-repeat py-8 px-8 py-md-27 px-md-0" style="background-image: url(<?= $helper->cdnUrl(); ?>assets/media/bg/bg-6.jpg);">
                    <div class="col-md-9">
                        <div class="d-flex justify-content-between pb-10 pb-md-20 flex-column flex-md-row">
                            <h1 class="display-4 text-white font-weight-boldest mb-10">RECHNUNG</h1>
                            <div class="d-flex flex-column align-items-md-end px-0">
                                <span class="text-white d-flex flex-column align-items-md-end opacity-70">
                                    <span>Severin Beyer Einzelunternehmen</span>
                                    <span>Severin Beyer</span>
                                    <span>Rochusstraße 11</span>
                                    <span>45470 Mülheim an der Ruhr</span>
                                    <span>Deutschland</span>
                                </span>
                            </div>
                        </div>
                        <div class="border-bottom w-100 opacity-20"></div>
                        <div class="d-flex justify-content-between text-white pt-6">
                            <div class="d-flex flex-column flex-root">
                                <span class="font-weight-bolde mb-2r">Datum</span>
                                <span class="opacity-70"><?= $helper->formatDate($invoice['created_at']); ?></span>
                            </div>
                            <div class="d-flex flex-column flex-root">
                                <span class="font-weight-bolder mb-2">Rechnungsnummer</span>
                                <span class="opacity-70">RE-<?= $id; ?></span>
                            </div>
                            <div class="d-flex flex-column flex-root">
                                <span class="font-weight-bolder mb-2">Rechnung an</span>
                                <span class="opacity-70">
                                    <?= $firstname ?> <?= $lastname ?><br>
                                    <?= $street ?> <?= $number ?>, <br>
                                    <?= $postcode ?> <?= $city ?> <?= $country ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center py-8 px-8 py-md-10 px-md-0">
                    <div class="col-md-9">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th class="pl-0 font-weight-bold text-muted text-uppercase">Beschreibung</th>
                                    <th class="text-right font-weight-bold text-muted text-uppercase">Menge</th>
                                    <th class="text-right font-weight-bold text-muted text-uppercase">Preis</th>
                                    <th class="text-right pr-0 font-weight-bold text-muted text-uppercase">Gesammtpreis</th>
                                </tr>
                                </thead>
                                <tbody>
                                    <tr class="font-weight-boldest font-size-lg">
                                        <td class="pl-0 pt-7"><?= $invoice['desc']; ?></td>
                                        <td class="text-right pt-7">1</td>
                                        <td class="text-right pt-7"><?= $amount; ?>€</td>
                                        <td class="text-danger pr-0 pt-7 text-right"><?= $amount; ?>€</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- end: Invoice body-->
                <!-- begin: Invoice footer-->
                <div class="row justify-content-center bg-gray-100 py-8 px-8 py-md-10 px-md-0">
                    <div class="col-md-9">
                        <div class="d-flex justify-content-between flex-column flex-md-row font-size-lg">
                            <div class="d-flex flex-column mb-10 mb-md-0">

                            </div>
                            <div class="d-flex flex-column text-md-right">
                                <span class="font-size-lg font-weight-bolder mb-1">Gesammtbetrag</span>
                                <span class="font-size-h2 font-weight-boldest text-danger mb-1"><?= $amount ?>€</span>
                                <!--span>Gemäß § 19 UStG wird keine Umsatzsteuer ausgewiesen.</span-->
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end: Invoice footer-->
                <!-- begin: Invoice action-->
                <div class="row justify-content-center py-8 px-8 py-md-10 px-md-0">
                    <div class="col-md-9">
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-primary font-weight-bold" onclick="window.print();">Rechnung drucken</button>
                        </div>
                    </div>
                </div>
                <!-- end: Invoice action-->
                <!-- end: Invoice-->
            </div>
        </div>
        <!-- end::Card-->
    </div>
</div>

<br>
<br>
<br>
