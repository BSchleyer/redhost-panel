<?php
$currPage = 'front_Webspace bestellen';
include BASE_PATH.'app/controller/PageController.php';
include BASE_PATH.'app/manager/customer/webspace/order.php';
?>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    <?php if($user->sessionExists($_COOKIE['session_token'])){ ?>
    <div class="subheader py-2 py-lg-4 subheader-solid" id="kt_subheader">
        <div class="container-fluid d-flex align-items-center justify-content-between flex-wrap flex-sm-nowrap">
            <div class="d-flex align-items-center flex-wrap mr-2">
                <h5 class="text-dark font-weight-bold mt-2 mb-2 mr-5"><?= env('APP_NAME'); ?></h5>
                <div class="subheader-separator subheader-separator-ver mt-2 mb-2 mr-4 bg-gray-200"></div>
                <span class="text-muted font-weight-bold mr-4"><?= $currPageName; ?></span>
            </div>
        </div>
    </div>
    <?php } ?>

    <div class="d-flex flex-column-fluid">
        <div class="container">

            <div class="card shadow mb-5">
                <div class="d-block d-lg-none rounded-card-top bg-danger position-absolute w-100 h-25"></div>
                <div class="card-body position-relative p-0 rounded-card-top">
                    <b><h1 class="bg-danger text-white text-center py-10 py-lg-20 m-0 rounded-card-top">Günstige Plesk Prepaid Webspaces mieten</h1></b>
                    <div class="row justify-content-center m-0 position-relative">
                        <div class="d-none d-lg-block bg-danger position-absolute w-100 h-100"></div>
                        <div class="col-11">
                            <div class="row">

                                <?php
                                $i = 0;
//                                    $names = ['disc', 'domains', 'subdomains', 'databases', 'ftp_accounts', 'emails'];

                                $disc = [];
                                $domains = [];
                                $subdomains = [];
                                $databases = [];
                                $ftp_accounts = [];
                                $emails = [];

                                $SQL = $db->prepare("SELECT * FROM `webspace_packs`");
                                $SQL->execute();
                                if ($SQL->rowCount() != 0) {
                                while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){

                                    array_push($disc, $row['disc']);
                                    array_push($domains, $row['domains']);
                                    array_push($subdomains, $row['subdomains']);
                                    array_push($databases, $row['databases']);
                                    array_push($ftp_accounts, $row['ftp_accounts']);
                                    array_push($emails, $row['emails']);

                                ?>

                                <div class="modal fade" id="webspaceModal<?= $row['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="webspaceModal<?= $row['id']; ?>Label" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="webspaceModal<?= $row['id']; ?>Label">Webspace mieten</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">


                                                <ul class="nav nav-pills" id="myTab" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" id="custom-tab<?= $row['id']; ?>" data-toggle="tab" href="#custom<?= $row['id']; ?>" role="tab" aria-controls="custom<?= $row['id']; ?>" aria-selected="true">Eigene Domain</a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="subdomain-tab<?= $row['id']; ?>" data-toggle="tab" href="#subdomain<?= $row['id']; ?>" role="tab" aria-controls="subdomain<?= $row['id']; ?>" aria-selected="false">Subdomain von Uns</a>
                                                    </li>
                                                </ul>

                                                <hr>


                                                <div class="tab-content" id="myTabContent">
                                                    <div class="tab-pane fade show active" id="custom<?= $row['id']; ?>" role="tabpanel" aria-labelledby="custom-tab<?= $row['id']; ?>">

                                                        <form method="post">

                                                            <label>Domain</label>
                                                            <input class="form-control" name="domainName" placeholder="deine-domain.de" required>

                                                            <br>
                                                            <label for="agb<?= $row['id']; ?>_1" class="checkbox noselect">
                                                                <input type="checkbox" name="agb" id="agb<?= $row['id']; ?>_1">
                                                                <span></span>
                                                                Ich habe die <a href="<?= $helper->url(); ?>agb">AGB</a> und <a href="<?= $helper->url(); ?>datenschutz">Datenschutzerklärung</a> gelesen und akzeptiere diese.
                                                            </label>
                                                            <label for="wiederruf<?= $row['id']; ?>_1" class="checkbox noselect">
                                                                <input type="checkbox" name="wiederruf" id="wiederruf<?= $row['id']; ?>_1">
                                                                <span></span>
                                                                Ich wünsche die vollständige Ausführung der Dienstleistung vor Fristablauf des Widerufsrechts gemäß Fernabsatzgesetz. Die automatische Einrichtung und Erbringung der Dienstleistung führt zum Erlöschen des Widerrufsrechts.
                                                            </label>

                                                            <input hidden value="<?= $row['plesk_id']; ?>" name="planName">

                                                            <br>
                                                            <hr>

                                                            <button type="submit" name="order" class="btn btn-outline-success text-uppercase font-weight-bolder px-15 py-3 pulse-green">
                                                                <i class="fas fa-shopping-cart"></i> Kostenpflichtig bestellen
                                                            </button>
                                                            <button type="button" class="btn btn-outline-primary text-uppercase font-weight-bolder" data-dismiss="modal">
                                                                <i class="fas fa-ban"></i> Abbrechen
                                                            </button>
                                                        </form>

                                                    </div>
                                                    <div class="tab-pane fade" id="subdomain<?= $row['id']; ?>" role="tabpanel" aria-labelledby="subdomain-tab<?= $row['id']; ?>">

                                                        <form method="post">

                                                            <label>Domain</label>
                                                            <input class="form-control" style="background-color: #292929;" readonly name="domainName" value="web<?= rand(0,9).rand(0,9).rand(0,9).'-'.$userid; ?>.<?= env('CUSTOM_WEBSPACE_SUBDOMAIN'); ?>" required>

                                                            <br>
                                                            <label for="agb<?= $row['id']; ?>_2" class="checkbox noselect">
                                                                <input type="checkbox" name="agb" id="agb<?= $row['id']; ?>_2">
                                                                <span></span>
                                                                Ich habe die <a href="<?= $helper->url(); ?>agb">AGB</a> und <a href="<?= $helper->url(); ?>datenschutz">Datenschutzerklärung</a> gelesen und akzeptiere diese.
                                                            </label>
                                                            <label for="wiederruf<?= $row['id']; ?>_2" class="checkbox noselect">
                                                                <input type="checkbox" name="wiederruf" id="wiederruf<?= $row['id']; ?>_2">
                                                                <span></span>
                                                                Ich wünsche die vollständige Ausführung der Dienstleistung vor Fristablauf des Widerufsrechts gemäß Fernabsatzgesetz. Die automatische Einrichtung und Erbringung der Dienstleistung führt zum Erlöschen des Widerrufsrechts.
                                                            </label>

                                                            <input hidden value="<?= $row['plesk_id']; ?>" name="planName">

                                                            <br>
                                                            <hr>

                                                            <button type="submit" name="order" class="btn btn-outline-success text-uppercase font-weight-bolder px-15 py-3 pulse-green">
                                                                <i class="fas fa-shopping-cart"></i> Kostenpflichtig bestellen
                                                            </button>
                                                            <button type="button" class="btn btn-outline-primary text-uppercase font-weight-bolder" data-dismiss="modal">
                                                                <i class="fas fa-ban"></i> Abbrechen
                                                            </button>
                                                        </form>

                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    function orderNow<?= $row['id']; ?>() {
                                        document.getElementById("orderForm<?= $row['id']; ?>").submit();
                                        const button = document.getElementById('orderBtn<?= $row["id"]; ?>');
                                        button.disabled = true;
                                        button.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> wird ausgeführt...';
                                    }
                                </script>

                                <!-- begin: Pricing-->
                                <div class="<?php if($i == 0){ echo 'offset-lg-3 col-12 col-lg-3 bg-white p-0'; } else { echo 'col-12 col-lg-3 bg-white border-x-0 border-x-lg border-y border-y-lg-0 p-0'; } ?>">
                                    <div class="py-15 px-0 px-lg-5 text-center">
                                        <i style="color:#9f1a30;" class="fas fa-cloud fa-7x"></i>
										<br><br>
                                        <h4 class="font-size-h3 mb-10 text-dark"><?= $row['name']; ?></h4>
                                        <div class="d-flex flex-column pb-7  text-dark-50">
                                            <span><?= $row['desc']; ?></span>
                                        </div>
                                        <span class="font-size-h1 font-weight-boldest text-dark"><?= $row['price']; ?><sup class="font-size-h3 font-weight-normal pl-1">€</sup></span>
                                        <!--begin::Mobile Pricing Table-->
                                        <div class="d-lg-none">
                                            <div class="bg-gray-100 py-3">
                                                <span class="font-weight-boldest">Speicherplatz</span>
                                                <span><?= $row['disc']; ?> GB</span>
                                            </div>
                                            <div class="py-3">
                                                <span class="font-weight-boldest">Domains</span>
                                                <span><?= $row['domains']; ?></span>
                                            </div>
                                            <div class="bg-gray-100 py-3">
                                                <span class="font-weight-boldest">Subdomains</span>
                                                <span><?= $row['subdomains']; ?></span>
                                            </div>
                                            <div class="py-3">
                                                <span class="font-weight-boldest">Datenbanken</span>
                                                <span><?= $row['databases']; ?></span>
                                            </div>
                                            <div class="bg-gray-100 py-3">
                                                <span class="font-weight-boldest">FTP-Accounts</span>
                                                <span><?= $row['ftp_accounts']; ?></span>
                                            </div>
                                            <div class="bg-gray-100 py-3">
                                                <span class="font-weight-boldest">E-Mails</span>
                                                <span><?= $row['emails']; ?></span>
                                            </div>
                                        </div>
                                        <!--end::Mobile Pricing Table-->
                                        <div class="mt-7">
                                            <?php if($user->sessionExists($_COOKIE['session_token'])){ ?>
                                            <a href="#" data-toggle="modal" data-target="#webspaceModal<?= $row['id']; ?>" class="btn btn-outline-primary text-uppercase font-weight-bolder px-15 py-3 pulse-red">
                                                <i class="fas fa-share-square"></i> Bestellung prüfen
                                            </a>
                                            <?php } else { ?>
                                            <a href="<?= env('URL'); ?>register" class="btn btn-outline-primary text-uppercase font-weight-bolder px-15 py-3 pulse-red">
                                                <i class="fas fa-share-square"></i> Account erstellen
                                            </a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                                <!-- end: Pricing-->

                                <?php $i++; } } ?>

                            </div>

                        </div>
                    </div>

                    <div class="row justify-content-center mx-0 mb-15 d-none d-lg-flex">
                        <div class="col-11">
                            <!-- begin: Bottom Table-->
                            <div class="row bg-gray-100 py-5 font-weight-bold text-center">
                                <div class="col-3 text-left px-5 font-weight-boldest">
                                    Festplatte
                                </div>
                                <?php foreach ($disc as $item) {?>
                                    <div class="col-3">
                                        <?= $item ?>GB
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="row bg-white py-5 font-weight-bold text-center">
                                <div class="col-3 text-left px-5 font-weight-boldest">
                                    Domains *
                                </div>
                                <?php foreach ($domains as $item) {?>
                                    <div class="col-3">
                                        <?= $item ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="row bg-gray-100 py-5 font-weight-bold text-center">
                                <div class="col-3 text-left px-5 font-weight-boldest">
                                    Subdomains
                                </div>
                                <?php foreach ($subdomains as $item) {?>
                                    <div class="col-3">
                                        <?= $item ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="row bg-white py-5 font-weight-bold text-center">
                                <div class="col-3 text-left px-5 font-weight-boldest">
                                    Datenbanken
                                </div>
                                <?php foreach ($databases as $item) {?>
                                    <div class="col-3">
                                        <?= $item ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="row bg-gray-100 py-5 font-weight-bold text-center">
                                <div class="col-3 text-left px-5 font-weight-boldest">
                                    FTP Accounts
                                </div>
                                <?php foreach ($ftp_accounts as $item) {?>
                                    <div class="col-3">
                                        <?= $item ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="row bg-white py-5 font-weight-bold text-center">
                                <div class="col-3 text-left px-5 font-weight-boldest">
                                    Emails
                                </div>
                                <?php foreach ($emails as $item) {?>
                                    <div class="col-3">
                                        <?= $item ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <!-- end: Bottom Table-->
							<br>
							
							* Die Domains sind nicht inklusive, die angegebene Zahl an Domains ist lediglich in der Verwaltungsoberfläche zuweisbar.
                        </div>
                    </div>
                    <!--end::Pricings-->

                </div>
                <!--end::Card body-->

                
            </div>
            

        </div>
    </div>
</div>