<?php
$currPage = 'back_Webspace';
include BASE_PATH.'app/controller/PageController.php';

$items = 0;

if(isset($_POST['saveCustomName'])){
    $error = null;

    if(empty($_POST['webspace_id'])){
        $error = 'Webspace wurde nicht gefunden';
    }

    if($userid != $plesk->getWebspace($_POST['webspace_id'],'user_id')){
        $error = 'Du hast keine Rechte auf diesen Webspace';
    }

    if(empty($error)){
        if(empty($_POST['custom_name'])){
            $custom_name = null;
            $msg = 'Name wurde entfernt';
        } else {
            $custom_name = $_POST['custom_name'];
            $msg = 'Name wurde gespeichert';
        }

        $SQL = $db->prepare("UPDATE `webspace` SET `custom_name` = :custom_name WHERE `id` = :id");
        $SQL -> execute(array(":custom_name" => $custom_name, ":id" => $_POST['webspace_id']));

        echo sendSuccess($msg);
    } else {
        echo sendError($error);
    }
}

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

                <?php
                $SQL = $db->prepare("SELECT * FROM `queue` WHERE `user_id` = :user_id");
                $SQL->execute(array(":user_id" => $userid));
                if ($SQL->rowCount() != 0) {
                while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){
                $payload = json_decode($row['payload']);
                if($payload->action == 'PLESK_ORDER'){ ?>
                    <div class="col-12 col-xl-3">
                        <div class="card text-center shadow mb-5">
                            <div class="card-header">
                                <div class="row align-items-center text-center">
                                    <div class="col">
                                        <h3 class="mb-0">
                                        <span class="svg-icon svg-icon-primary svg-icon-8x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Weather/Cloudy.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                <polygon points="0 0 24 0 24 24 0 24"/>
                                                <path d="M7.74714567,15.0425758 C6.09410362,13.9740356 5,12.1147886 5,10 C5,6.6862915 7.6862915,4 11,4 C13.7957591,4 16.1449096,5.91215918 16.8109738,8.5 L19.25,8.5 C21.3210678,8.5 23,10.1789322 23,12.25 C23,14.3210678 21.3210678,16 19.25,16 L10.25,16 C9.28817895,16 8.41093178,15.6378962 7.74714567,15.0425758 Z" fill="#000000" opacity="0.3"/>
                                                <path d="M3.74714567,19.0425758 C2.09410362,17.9740356 1,16.1147886 1,14 C1,10.6862915 3.6862915,8 7,8 C9.79575914,8 12.1449096,9.91215918 12.8109738,12.5 L15.25,12.5 C17.3210678,12.5 19,14.1789322 19,16.25 C19,18.3210678 17.3210678,20 15.25,20 L6.25,20 C5.28817895,20 4.41093178,19.6378962 3.74714567,19.0425758 Z" fill="#000000"/>
                                            </g>
                                        </svg><!--end::Svg Icon--></span>
                                            <br><br>
                                            <b>Webspace</b>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="mb-lg-4">
                                            <b>Status</b><br>
                                            <i class="fas fa-sync-alt fa-spin"></i>
                                        </h4>
                                    </div>
                                </div>

                                <hr>

                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="mb-0">
                                            <b>Ablaufdatum</b><br>
                                            -
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="row align-items-center text-center">
                                    <div class="col-md-6">
                                        <a href="#" class="btn btn-block btn-outline-primary">
                                            <b>Verwalten</b>
                                        </a>
                                    </div>

                                    <br><br><br>

                                    <div class="col-md-6">
                                        <a href="#" class="btn btn-block btn-outline-primary">
                                            <b>Verlängern</b>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php $items++; } } } ?>

                <?php
                $SQL = $db->prepare("SELECT * FROM `webspace` WHERE `user_id` = :user_id AND `deleted_at` IS NULL ORDER BY `id` DESC");
                $SQL->execute(array(":user_id" => $userid));
                if ($SQL->rowCount() != 0) {
                    while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){

                        if($row['state'] == 'active'){
                            $state = '<span class="badge badge-success">Aktiv</span>';
                        } else {
                            $state = '<span class="badge badge-warning">Gesperrt</span>';
                        }

                        if(!is_null($row['locked'])){
                            $state = '<span class="badge badge-warning">Gesperrt</span>';
                        }

                        ?>

                        <div class="modal fade" id="webspaceModal<?= $row['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="webspaceModal<?= $row['id']; ?>Label" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="webspaceModal<?= $row['id']; ?>Label">Webspace umbenennen</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">

                                            <label>Produkt Name</label>
                                            <input class="form-control" name="custom_name" value="<?= $row['custom_name']; ?>">
                                            <input hidden name="webspace_id" value="<?= $row['id']; ?>">

                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-primary text-uppercase font-weight-bolder" data-dismiss="modal"><i class="fas fa-ban"></i> Abbrechen</button>
                                            <button type="submit" name="saveCustomName" class="btn btn-outline-success text-uppercase font-weight-bolder"><i class="fas fa-save"></i> Speichern</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-3">
                            <div class="card text-center shadow mb-5">
                                <div class="card-header">
                                    <div class="row align-items-center text-center">
                                        <div class="col">
                                            <h3 class="mb-0">
                                            <span class="svg-icon svg-icon-primary svg-icon-8x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Weather/Cloudy.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <polygon points="0 0 24 0 24 24 0 24"/>
                                                    <path d="M7.74714567,15.0425758 C6.09410362,13.9740356 5,12.1147886 5,10 C5,6.6862915 7.6862915,4 11,4 C13.7957591,4 16.1449096,5.91215918 16.8109738,8.5 L19.25,8.5 C21.3210678,8.5 23,10.1789322 23,12.25 C23,14.3210678 21.3210678,16 19.25,16 L10.25,16 C9.28817895,16 8.41093178,15.6378962 7.74714567,15.0425758 Z" fill="#000000" opacity="0.3"/>
                                                    <path d="M3.74714567,19.0425758 C2.09410362,17.9740356 1,16.1147886 1,14 C1,10.6862915 3.6862915,8 7,8 C9.79575914,8 12.1449096,9.91215918 12.8109738,12.5 L15.25,12.5 C17.3210678,12.5 19,14.1789322 19,16.25 C19,18.3210678 17.3210678,20 15.25,20 L6.25,20 C5.28817895,20 4.41093178,19.6378962 3.74714567,19.0425758 Z" fill="#000000"/>
                                                </g>
                                            </svg><!--end::Svg Icon--></span>
												<br><br>
                                                <?php if(is_null($row['custom_name'])){ ?>
                                                    <b>Webspace</b> #<?= $row['id']; ?> <span data-toggle="tooltip" title="Eigenen Produkt-Namen setzen"><i style="cursor:pointer;" data-toggle="modal" data-target="#webspaceModal<?= $row['id']; ?>" class="far fa-edit"></i></span>
                                                <?php } else { ?>
                                                    <?= $helper->xssFix($row['custom_name']); ?> <span data-toggle="tooltip" title="Eigenen Produkt-Namen setzen"><i style="cursor:pointer;" data-toggle="modal" data-target="#webspaceModal<?= $row['id']; ?>" class="far fa-edit"></i></span>
                                                <?php } ?>
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h4 class="mb-lg-4">
                                                <b>Status</b><br>
                                                <?= $state; ?>
                                            </h4>
                                        </div>
                                    </div>
									
									<hr>
									
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h4 class="mb-lg-4">
                                                <b>Domain</b><br>
                                                <span class="badge badge-success"><?= $row['domainName']; ?></span>
                                            </h4>
                                        </div>
                                    </div>
									
									<hr>
									
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h4 class="mb-0">
                                                <b>Ablaufdatum</b><br>
                                                <span id="countdown<?= $row['id']; ?>">Lädt...</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <a href="<?= env('URL'); ?>manage/webspace/<?= $row['id']; ?>" class="btn btn-block btn-outline-primary">
                                                <b>Verwalten</b>
                                            </a>
                                        </div>
										
										<br><br><br>
										
                                        <div class="col-md-6">
                                            <a href="<?= env('URL'); ?>renew/webspace/<?= $row['id']; ?>" class="btn btn-block btn-outline-primary">
                                                <b>Verlängern</b>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            var countDownDate<?= $row['id']; ?> = new Date("<?= $row['expire_at']; ?>").getTime();
                            var x<?= $row['id']; ?> = setInterval(function() {

                                var now<?= $row['id']; ?> = new Date().getTime();
                                var distance<?= $row['id']; ?> = countDownDate<?= $row['id']; ?> - now<?= $row['id']; ?>;

                                var days = Math.floor(distance<?= $row['id']; ?> / (1000 * 60 * 60 * 24));
                                var hours = Math.floor((distance<?= $row['id']; ?> % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                var minutes = Math.floor((distance<?= $row['id']; ?> % (1000 * 60 * 60)) / (1000 * 60));
                                var seconds = Math.floor((distance<?= $row['id']; ?> % (1000 * 60)) / 1000);

                                if(days == 1){ var days_text = ' Tag' } else { var days_text = ' Tage'; }
                                if(hours == 1){ var hours_text = ' Stunde' } else { var hours_text = ' Stunden'; }
                                if(minutes == 1){ var minutes_text = ' Minute' } else { var minutes_text = ' Minuten'; }
                                if(seconds == 1){ var seconds_text = ' Sekunde' } else { var seconds_text = ' Sekunden'; }

                                if(days == 0 && !(hours == 0 && minutes == 0 && seconds == 0)){
                                    $('#countdown<?= $row["id"]; ?>').html(hours+hours_text+', '+  minutes+minutes_text+' und ' +  seconds+seconds_text);
                                    if(days == 0 && hours == 0 && !(minutes == 0 && seconds == 0)){
                                        $('#countdown<?= $row["id"]; ?>').html(minutes+minutes_text+' und '+  seconds+seconds_text);
                                        if(days == 0 && hours == 0 && minutes == 0 && !(seconds == 0)){
                                            $('#countdown<?= $row["id"]; ?>').html(seconds+seconds_text);
                                        }
                                    }
                                } else {
                                    $('#countdown<?= $row["id"]; ?>').html(days+days_text+', '+  hours+hours_text+', '+  minutes+minutes_text+' und '+  seconds+seconds_text);
                                }

                                if (distance<?= $row['id']; ?> <= 0) {
                                    clearInterval(x<?= $row['id']; ?>);
                                }
                            }, 1000);
                        </script>
                    <?php $items++; } } if($items == 0){ ?>
                        <div class="col-md-12">
                            <?php if($darkmode){ ?>
                                <div class="alert alert-dark text-center" role="alert">
                            <?php } else { ?>
                                <div class="alert alert-light text-center" role="alert">
                            <?php } ?>
                                <h1 class="alert-heading">
                                    <br>
                                    Du hast aktuell noch keinen Webspace 😲
                                </h1>
                                <br>
                                <h4>
                                    Miete dir jetzt einen Prepaid Plesk Webspace bei uns!
                                </h4>
                                <br>
                                <p>
                                    <a href="<?= env('URL'); ?>order/webspace" class="btn btn-outline-primary text-uppercase font-weight-bolder pulse-red">Jetzt bestellen</a>
                                </p>
                            </div>
                        </div>
                    <?php } ?>
            </div>
        </div>
    </div>
</div>