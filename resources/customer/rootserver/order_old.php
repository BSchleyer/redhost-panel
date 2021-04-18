<?php
$currPage = 'front_Rootserver bestellen';
include BASE_PATH.'app/controller/PageController.php';
include BASE_PATH.'app/manager/customer/rootserver/order.php';
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
            <div class="row">
                <div class="col-md-12">
                    <?php if($darkmode){ ?>
                        <div class="alert alert-dark text-center" role="alert">
                    <?php } else { ?>
                        <div class="alert alert-light text-center" role="alert">
                    <?php } ?>
                        <h1 class="alert-heading">
                            <br>
                            RootServer sind derzeit nicht Erhältlich 😲
                        </h1>
                        <br>
                        <h4>
                            Anfragen per Ticket möglich.
                        </h4>
                        <br>
                        <p>
                            <a href="<?= env('URL'); ?>tickets" class="btn btn-outline-primary text-uppercase font-weight-bolder pulse-red">Zum Ticket System</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>