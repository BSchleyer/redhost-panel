<?php
$currPage = 'back_Gameserver up/downgrade';
include BASE_PATH.'app/controller/PageController.php';
include BASE_PATH.'app/manager/customer/gameserver/reconfigure.php';
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

                <div class="col-md-9">
                    <div class="card shadow mb-5">
                        <div class="card-body">

                            <form method="post" id="reconfigureForm">

                                <label for="cores">Kerne</label>
                                <select class="form-control" id="cores" name="cores">
                                    <?php
                                    $SQL = $db->prepare("SELECT * FROM `product_option_entries` WHERE `option_id` = :option_id");
                                    $SQL->execute(array(":option_id" => '10'));
                                    if ($SQL->rowCount() != 0) { while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) { ?>
                                        <option <?php if($serverInfos['cores'] == $row['value']){ echo 'selected'; } ?> data-price="<?= number_format($row['price'] - $site->getProductOptionEntrie('1', $serverInfos['cores'],'price'),2); ?>" value="<?= $row['value']; ?>"><?= $row['name']; ?> (+ <?= $row['price']; ?>€)</option>
                                    <?php } } ?>
                                </select>

                                <br>
                                <label for="memory">Arbeitsspeicher</label>
                                <select class="form-control" id="memory" name="memory">
                                    <?php
                                    $SQL = $db->prepare("SELECT * FROM `product_option_entries` WHERE `option_id` = :option_id");
                                    $SQL->execute(array(":option_id" => '9'));
                                    if ($SQL->rowCount() != 0) { while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) { ?>
                                        <option <?php if($serverInfos['memory'] == $row['value']){ echo 'selected'; } ?> data-price="<?= number_format($row['price'] - $site->getProductOptionEntrie('2', $serverInfos['memory'],'price'),2); ?>" value="<?= $row['value']; ?>"><?= $row['name']; ?> (+ <?= $row['price']; ?>€)</option>
                                    <?php } } ?>
                                </select>

                                <br>
                                <label for="disk">Festplatte</label>
                                <select class="form-control" id="disk" name="disk">
                                    <?php
                                    $SQL = $db->prepare("SELECT * FROM `product_option_entries` WHERE `option_id` = :option_id");
                                    $SQL->execute(array(":option_id" => '11'));
                                    if ($SQL->rowCount() != 0) { while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) { ?>
                                        <option <?php if($serverInfos['disc'] == $row['value']){ echo 'selected'; } ?> <?php if($serverInfos['disc'] > $row['value']){ echo 'disabled'; } ?> data-price="<?= number_format($row['price'] - $site->getProductOptionEntrie('3', $serverInfos['disc'],'price'),2); ?>" value="<?= $row['value']; ?>"><?= $row['name']; ?> (+ <?= $row['price']; ?>€)</option>
                                    <?php } } ?>
                                </select>

                                <br>

                                <input hidden name="reconfigure" value="">
                                <button type="button" onclick="reconfigureNow();" class="btn btn-outline-primary"><b><i class="fas fa-exchange-alt"></i> Kostenpflichtig up/downgraden</b></button>
                            </form>

                            <script>
                                function reconfigureNow() {
                                    Swal.fire({
                                        title: 'Warnung',
                                        text: "Achtung, dein Server wird nach dem up/downgrade Automatisch neugestartet",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#d33',
                                        cancelButtonColor: '#3085d6',
                                        confirmButtonText: 'Okay',
                                        cancelButtonText: 'Lieber doch nicht'
                                    }).then((result) => {
                                        if (result.value) {
                                            document.getElementById('reconfigureForm').submit();
                                        }
                                    })
                                }
                            </script>

                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card shadow mb-5">
                        <div class="card-header text-center">
                            <h4 style="margin-bottom: 0px;">Produkt Übersicht</h4>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <b class="mb-0">
                                        Gesamtbetrag:
                                    </b>
                                </div>
                                <div class="col-auto">
                                    <a class="text-muted">
                                        <span id="need_pay" data-amount=""></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<script>
    $("select, textarea").change(function() { update(); } ).trigger("change");

    function update(){
        sum = parseFloat($("#cores").find("option:selected").data("price"))
            +parseFloat($("#memory").find("option:selected").data("price"))
            +parseFloat($("#disk").find("option:selected").data("price"));
        var price = Number((sum * <?= $site->getDiffInDays($serverInfos['expire_at']) ?> / 30)).toFixed(2);
        $("*[data-amount]").html(price + " €");
    }

    $(document).ready(function(){
        update();
    });
</script>