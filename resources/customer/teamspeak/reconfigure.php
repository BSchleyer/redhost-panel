<?php
$currPage = 'back_Teamspeak';
include BASE_PATH.'app/controller/PageController.php';
include BASE_PATH.'app/manager/customer/teamspeak/reconfigure.php';
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

                            <form method="post">
                                <label for="slots">Slots</label>
                                <input id="slots" name="slots" type="number" min="5" max="1000" value="<?= $serverInfos['slots']; ?>" class="form-control">

                                <br>

                                <button type="submit" class="btn btn-outline-primary font-weight-bolder" name="reconfigure"><i class="fas fa-exchange-alt"></i> Kostenpflichtig up/downgraden</button>
                            </form>

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
                                        Akuelle Slots
                                    </b>
                                </div>
                                <div class="col-auto">
                                    <a class="text-muted">
                                        <?= $serverInfos['slots']; ?>
                                    </a>
                                </div>
                            </div>

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
    $('#slots').on('input', function() {update();});
    $("select, textarea").change(function() { update(); } ).trigger("change");

    function update(){
        var sum = ($("#slots").val() * <?= $site->getProductPrice('TEAMSPEAK'); ?>) - (<?= $serverInfos['slots']; ?> * <?= $site->getProductPrice('TEAMSPEAK'); ?>);

        var price = Number((sum * <?= $site->getDiffInDays($serverInfos['expire_at']); ?> / 30)).toFixed(2);
            $('#money_after').html(Number(parseFloat('<?php echo $amount; ?>') - parseFloat(price)).toFixed(2) + '€')
        $("*[data-amount]").html(price + " €");
    }

    $(document).ready(function(){
        update();
    });
</script>