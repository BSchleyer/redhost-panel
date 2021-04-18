<?php
$currPage = 'front_GameServer bestellen';
include BASE_PATH.'app/controller/PageController.php';

include BASE_PATH.'app/manager/customer/gameserver/order.php';
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
            <h1 style="font-size: 70px;">Prepaid <b style="color: #9e2033;">Game</b>Server</h1>
            <hr style="width: 40%;">
            <h1>Miete dir jetzt einen Prepaid Minecraft GameServer</h1>
        </div>
    <?php } ?>
    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="row">

                <div class="col-md-9">
                    <div class="card shadow mb-5">
                        <div class="card-body">
                            <form method="post" id="orderForm">

                                <i class="fas fa-microchip"></i> <b><label style="font-weight: bold;" for="cores">Kerne</label></b>
                                <select class="form-control" id="cores" name="cores">
                                    <?php
                                    $SQL = $db->prepare("SELECT * FROM `product_option_entries` WHERE `option_id` = :option_id");
                                    $SQL->execute(array(":option_id" => '10'));
                                    if ($SQL->rowCount() != 0) { while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) { ?>
                                        <option data-price="<?= $row['price']; ?>" value="<?= $row['value']; ?>"><?= $row['name']; ?> (+ <?= $row['price']; ?>€)</option>
                                    <?php } } ?>
                                </select>

                                <br>
                                <i class="fas fa-memory"></i> <b><label style="font-weight: bold;" for="memory">Arbeitsspeicher</label></b>
                                <select class="form-control" id="memory" name="memory">
                                    <?php
                                    $SQL = $db->prepare("SELECT * FROM `product_option_entries` WHERE `option_id` = :option_id");
                                    $SQL->execute(array(":option_id" => '9'));
                                    if ($SQL->rowCount() != 0) { while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) { ?>
                                        <option data-price="<?= $row['price']; ?>" value="<?= $row['value']; ?>"><?= $row['name']; ?> (+ <?= $row['price']; ?>€)</option>
                                    <?php } } ?>
                                </select>

                                <br>
                                <i class="fas fa-hdd"></i> <b><label style="font-weight: bold;" for="disk">Festplatte</label></b>
                                <select class="form-control" id="disk" name="disk">
                                    <?php
                                    $SQL = $db->prepare("SELECT * FROM `product_option_entries` WHERE `option_id` = :option_id");
                                    $SQL->execute(array(":option_id" => '11'));
                                    if ($SQL->rowCount() != 0) { while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) { ?>
                                        <option data-price="<?= $row['price']; ?>" value="<?= $row['value']; ?>"><?= $row['name']; ?> (+ <?= $row['price']; ?>€)</option>
                                    <?php } } ?>
                                </select>

                                <br>
                                <label>Slots</label>
                                <select class="form-control">
                                    <option value="0">Keine Slotbegrenzung (+0.00 €)</option>
                                </select>

                                <br>
                                <label>MySQL-Datenbank</label>
                                <select class="form-control">
                                    <option value="0">Nein (+0.00 €)</option>
                                </select>

                                <br>
                                <div class="form-group">
                                    <i class="fas fa-history"></i> <b><label style="font-weight: bold;" for="duration">Laufzeit</label></b>
                                    <select class="form-control" id="duration" name="runtime">
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
                            <a href="#" onclick="orderNow();" id="orderBtn" class="btn btn-block btn-outline-primary mb-4 pulse-red">
                                <i class="fas fa-shopping-cart"></i> <b>Kostenpflichtig Bestellen</b>
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

                    $("select, textarea").change(function() { update(); } ).trigger("change");

                    function update()
                    {
                        sum = parseFloat($("#cores").find("option:selected").data("price"))
                            +parseFloat($("#memory").find("option:selected").data("price"))
                            +parseFloat($("#disk").find("option:selected").data("price"));
                        var price = Number(sum * $("#duration").find("option:selected").data("factor")).toFixed(2);
                        $("*[data-amount]").html(price + " €");

                        var duration = $("#duration").find("option:selected").val();
                        $('#duration_val').html(duration);
                    }

                    $(document).ready(function(){
                        update();
                    });
                </script>

            </div>
        </div>
    </div>
</div>