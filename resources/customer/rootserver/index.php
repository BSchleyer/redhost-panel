<?php
$currPage = 'back_Rootserver';
include BASE_PATH.'app/controller/PageController.php';

$items = 0;

if(isset($_POST['saveCustomName'])){
    $error = null;

    if(empty($_POST['service_id'])){
        $error = 'Produkt wurde nicht gefunden';
    }

    $SQLGetServerInfos = $db->prepare("SELECT * FROM `vm_servers` WHERE `id` = :id");
    $SQLGetServerInfos -> execute(array(":id" => $_POST['service_id']));
    $serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);
    if($userid != $serverInfos['user_id']){
        $error = 'Du hast keine Rechte auf dieses Produkt';
    }

    if(empty($error)){
        if(empty($_POST['custom_name'])){
            $custom_name = null;
            $msg = 'Name wurde entfernt';
        } else {
            $custom_name = $_POST['custom_name'];
            $msg = 'Name wurde gespeichert';
        }

        $SQL = $db->prepare("UPDATE `vm_servers` SET `custom_name` = :custom_name WHERE `id` = :id");
        $SQL -> execute(array(":custom_name" => $custom_name, ":id" => $_POST['service_id']));

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
                $SQL = $db->prepare("SELECT * FROM `vm_servers` WHERE `user_id` = :user_id AND `deleted_at` IS NULL AND `type` = 'KVM' ORDER BY `id` DESC");
                $SQL->execute(array(":user_id" => $userid));
                if ($SQL->rowCount() != 0) {
                    while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){

                        if($row['state'] == 'PENDING'){
                            $state = '<span class="badge badge-warning">Warte auf Freischaltung</span>';
                        } elseif($row['state'] == 'ACTIVE'){
                            $state = '<span class="badge badge-success">Aktiv</span>';
                        } else {
                            $state = '<span class="badge badge-warning">Gesperrt</span>';
                        }

                        if(!is_null($row['locked'])){
                            $state = '<span class="badge badge-warning">Gesperrt</span>';
                        }

                        if(!is_null($row['job_id'])){
                            $job_infos = $venocix->getJobInfo($row['job_id']);
                            if($job_infos->result->status == 'finished'){
                                $update = $db->prepare("UPDATE `vm_servers` SET `venocix_id` = :venocix_id, `job_id` = NULL, `password` = :password WHERE `id` = :id");
                                $update->execute(array(":venocix_id" => json_encode($job_infos), ":password" => $job_infos->result->output->password, ":id" => $row['id']));
                            } else {
                                $state = '<span class="badge badge-warning">Server wird installiert</span>';
                            }
                        }

                        ?>

                        <div class="modal fade" id="productModal<?= $row['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="productModal<?= $row['id']; ?>Label" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="productModal<?= $row['id']; ?>Label">Produkt umbenennen</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">

                                            <label>Produkt Name</label>
                                            <input class="form-control" name="custom_name" value="<?= $row['custom_name']; ?>">
                                            <input hidden name="service_id" value="<?= $row['id']; ?>">

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
												<!--
                                                <i style="color:#9f1a30;" class="fas fa-server fa-5x"></i>
                                                -->
                                                <span class="svg-icon svg-icon-primary svg-icon-8x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Devices/Server.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <rect x="0" y="0" width="24" height="24"/>
                                                        <path d="M5,2 L19,2 C20.1045695,2 21,2.8954305 21,4 L21,6 C21,7.1045695 20.1045695,8 19,8 L5,8 C3.8954305,8 3,7.1045695 3,6 L3,4 C3,2.8954305 3.8954305,2 5,2 Z M11,4 C10.4477153,4 10,4.44771525 10,5 C10,5.55228475 10.4477153,6 11,6 L16,6 C16.5522847,6 17,5.55228475 17,5 C17,4.44771525 16.5522847,4 16,4 L11,4 Z M7,6 C7.55228475,6 8,5.55228475 8,5 C8,4.44771525 7.55228475,4 7,4 C6.44771525,4 6,4.44771525 6,5 C6,5.55228475 6.44771525,6 7,6 Z" fill="#000000" opacity="0.3"/>
                                                        <path d="M5,9 L19,9 C20.1045695,9 21,9.8954305 21,11 L21,13 C21,14.1045695 20.1045695,15 19,15 L5,15 C3.8954305,15 3,14.1045695 3,13 L3,11 C3,9.8954305 3.8954305,9 5,9 Z M11,11 C10.4477153,11 10,11.4477153 10,12 C10,12.5522847 10.4477153,13 11,13 L16,13 C16.5522847,13 17,12.5522847 17,12 C17,11.4477153 16.5522847,11 16,11 L11,11 Z M7,13 C7.55228475,13 8,12.5522847 8,12 C8,11.4477153 7.55228475,11 7,11 C6.44771525,11 6,11.4477153 6,12 C6,12.5522847 6.44771525,13 7,13 Z" fill="#000000"/>
                                                        <path d="M5,16 L19,16 C20.1045695,16 21,16.8954305 21,18 L21,20 C21,21.1045695 20.1045695,22 19,22 L5,22 C3.8954305,22 3,21.1045695 3,20 L3,18 C3,16.8954305 3.8954305,16 5,16 Z M11,18 C10.4477153,18 10,18.4477153 10,19 C10,19.5522847 10.4477153,20 11,20 L16,20 C16.5522847,20 17,19.5522847 17,19 C17,18.4477153 16.5522847,18 16,18 L11,18 Z M7,20 C7.55228475,20 8,19.5522847 8,19 C8,18.4477153 7.55228475,18 7,18 C6.44771525,18 6,18.4477153 6,19 C6,19.5522847 6.44771525,20 7,20 Z" fill="#000000"/>
                                                    </g>
                                                </svg><!--end::Svg Icon--></span>
												<br><br>
                                                <?php if(is_null($row['custom_name'])){ ?>
                                                    <b>Rootserver</b> #<?= $row['id']; ?> <span data-toggle="tooltip" title="Eigenen Produkt-Namen setzen"><i style="cursor:pointer;" data-toggle="modal" data-target="#productModal<?= $row['id']; ?>" class="far fa-edit"></i></span>
                                                <?php } else { ?>
                                                    <?= $helper->xssFix($row['custom_name']); ?> <span data-toggle="tooltip" title="Eigenen Produkt-Namen setzen"><i style="cursor:pointer;" data-toggle="modal" data-target="#productModal<?= $row['id']; ?>" class="far fa-edit"></i></span>
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
                                            <h4 class="mb-0">
                                                <b>Ablaufdatum</b><br>
                                                <?php if($row['state'] == 'PENDING'){ ?>
                                                    <span>Unbekannt</span>
                                                <?php } else { ?>
                                                    <span id="countdown<?= $row['id']; ?>">Lädt...</span>
                                                <?php } ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="row align-items-center text-center">
                                        <div class="col-md-6">
                                            <?php if($row['state'] == 'PENDING'){ ?>
                                                <button disabled="" class="btn btn-block btn-outline-primary">
                                                    <b>Verwalten</b>
                                                </button>
                                            <?php } else { ?>
                                                <a href="<?= env('URL'); ?>manage/rootserver/<?= $row['id']; ?>" class="btn btn-block btn-outline-primary">
                                                    <b>Verwalten</b>
                                                </a>
                                            <?php } ?>
                                        </div>
										
										<br><br><br>
										
                                        <div class="col-md-6">
                                            <?php if($row['state'] == 'PENDING'){ ?>
                                                <button disabled="" class="btn btn-block btn-outline-primary">
                                                    <b>Verlängern</b>
                                                </button>
                                            <?php } else { ?>
                                                <a href="<?= env('URL'); ?>renew/rootserver/<?= $row['id']; ?>" class="btn btn-block btn-outline-primary">
                                                    <b>Verlängern</b>
                                                </a>
                                            <?php } ?>
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
                                    Du hast aktuell noch keinen Rootserver 😲
                                </h1>
                                <br>
                                <h4>
                                    Miete dir jetzt einen Prepaid KVM Rootserver bei uns!
                                </h4>
                                <br>
                                <p>
                                    <a href="<?= env('URL'); ?>order/rootserver" class="btn btn-outline-primary text-uppercase font-weight-bolder pulse-red">Jetzt bestellen</a>
                                </p>
                            </div>
                        </div>
                    <?php } ?>
            </div>
        </div>
    </div>
</div>