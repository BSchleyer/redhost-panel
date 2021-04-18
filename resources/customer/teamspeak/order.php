s<?php
$currPage = 'front_Teamspeak bestellen';
include BASE_PATH.'app/controller/PageController.php';
include BASE_PATH.'app/manager/customer/teamspeak/order.php';
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
        <div class="text-center" style="margin-top: 50px; margin-bottom: 50px;">
            <h1 style="font-size: 70px;">Prepaid <b style="color: #9e2033;">Teamspeak</b> Server</h1>
            <hr style="width: 40%;">
            <h1>Miete dir jetzt deinen Prepaid Teamspeak Server</h1>
            <!--h1>Bestens geeignet für z.B. Teamspeak oder GameServer</h1-->
        </div>
    <?php } ?>

    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="row">

                <div class="col-md-9">
                    <div class="card shadow mb-5">
                        <div class="card-header"><h1>TeamSpeak 3 Server konfigurieren</h1>
						<!--hr>
						<div class="alert alert-primary" role="alert">
							<h1>Summer SALE</h1> <hr>
							Unsere Teamspeak Server sind nun rediziert, statt 18 cent pro Slot nur <b>16 cent pro Slot</b>
						</div-->
						<hr>
						<i class="fab fa-teamspeak"></i> Teamspeak 3 Server<br>
                        <i class="fas fa-server"></i> Standort OVH<br>
						<i class="fas fa-users"></i> Slots 5 - 1000 Slots <br>
                        <i class="fas fa-tachometer-alt"></i> Traffic Unlimitiert Inklusive<br>
						<i class="fas fa-shield-alt"></i> Permanenter Premium OVH Game DDoS Schutz<br>
						<br>
						<i class="fas fa-angle-double-right"></i> Offizieller ATHP TeamSpeak-Reseller, mehr dazu <a target="_blank" href="https://ts-reseller.de/">hier</a>.<br>						
						
						</div>
                        <div class="card-body">
                            <form method="post" id="orderForm">
                                <div class="form-group">
                                    <i class="fas fa-users"></i> <label style="font-weight: bold;" for="slot_count">Slots</label>
                                    <input name="slots" id="slots" class="form-control" type="number" value="5" min="5" max="1000">
                                </div>

                                <div class="form-group">
                                    <i class="fas fa-history"></i> <label style="font-weight: bold;" for="interval">Laufzeit</label>
                                    <select class="form-control" id="duration" name="duration">
                                        <option value="30" data-factor="1">30 Tage</option>
                                        <option value="60" data-factor="2">60 Tage</option>
                                        <option value="90" data-factor="3">90 Tage</option>
                                        <option value="180" data-factor="6">180 Tage</option>
                                        <option value="365" data-factor="12">365 Tage</option>
                                    </select>
                                </div>

                                <input hidden value="" name="order">

                                <label for="agb" class="checkbox noselect">
                                    <input type="checkbox" name="agb" id="agb">
                                    <span></span>
                                    Ich habe die <a href="<?= $helper->url(); ?>agb">AGB</a> und <a href="<?= $helper->url(); ?>datenschutz">Datenschutzerklärung</a> gelesen und akzeptiere diese.
                                </label>
                                <br>
                                <br>
                                <label for="wiederruf" class="checkbox noselect">
                                    <input type="checkbox" name="wiederruf" id="wiederruf">
                                    <span></span>
                                    Ich wünsche die vollständige Ausführung der Dienstleistung vor Fristablauf des Widerufsrechts gemäß Fernabsatzgesetz. Die automatische Einrichtung und Erbringung der Dienstleistung führt zum Erlöschen des Widerrufsrechts.
                                </label>

                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card shadow mb-5">
                        <div class="card-header text-center">
                            <h3 style="margin-bottom: 0px;">Kostenübersicht</h3>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <b class="mb-0">
                                        <span id="slot_count"></span> Slots
                                    </b>
                                </div>
                                <div class="col-auto">
                                    <a class="text-muted">
                                        <span id="slot_price"></span>
                                    </a>
                                </div>
                            </div>
                            <div class="row align-items-center">
                                <div class="col">
                                    <b class="mb-0">
                                        Laufzeit
                                    </b>
                                </div>
                                <div class="col-auto">
                                    <a class="text-muted">
                                        <span id="duration_val"></span> Tage
                                    </a>
                                </div>
                            </div>
                            <hr class="my-3">
                            <div class="row align-items-center">
                                <div class="col">
                                    <b class="mb-0">
                                        Gesamtbetrag:
                                    </b>
                                </div>
                                <div class="col-auto">
                                    <a class="text-muted">
                                        <span data-amount="">0.00</span>
                                    </a>
                                </div>
                            </div>
                            <br>
                            <hr>
                            <br>
                            <?php if($user->sessionExists($_COOKIE['session_token'])){ ?>
                            <a href="#" class="btn btn-block btn-outline-primary mb-4 pulse-red" onclick="orderNow();" id="orderBtn">
                                <i class="fas fa-shopping-cart"></i> <b>Kostenpflichtig bestellen</b>
                            </a>
                            <?php } else { ?>
                                <a href="<?= env('URL'); ?>" class="btn btn-block btn-outline-primary mb-4 pulse-red">
                                    <b>Account erstellen</b>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <script>

                    function orderNow() {
                        document.getElementById("orderForm").submit();
                        const button = document.getElementById('orderBtn');
                        button.disabled = true;
                        button.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Bestellung wird ausgeführt...';
                    }

                    $('#slots').on('input', function() {update();});
                    $("select, textarea").change(function() { update(); } ).trigger("change");

                    function update(){
                        var slot_count = $("#slots").val();
                        var slot_price = slot_count * <?= $site->getProductPrice('TEAMSPEAK'); ?>;
                        var end_slot_price = Number(slot_price * $("#duration").find("option:selected").data("factor")).toFixed(2);
                        $('#slot_count').html(slot_count);
                        $('#slot_price').html(end_slot_price + "€");
                        var end_price = Number(parseFloat(end_slot_price)).toFixed(2);
                        var duration = $("#duration").find("option:selected").val();
                        $('#duration_val').html(duration);

                        $("*[data-amount]").html(end_price + "€");
                    }

                    $(document).ready(function(){
                        update();
                    });
                </script>

            </div>
        </div>
    </div>
</div>