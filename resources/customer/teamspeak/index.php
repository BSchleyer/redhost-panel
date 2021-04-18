<?php
$currPage = 'back_Teamspeak';
include BASE_PATH.'app/controller/PageController.php';

if(isset($_POST['saveCustomName'])){
    $error = null;

    if(empty($_POST['service_id'])){
        $error = 'Produkt wurde nicht gefunden';
    }

    $SQLGetServerInfos = $db->prepare("SELECT * FROM `teamspeaks` WHERE `id` = :id");
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

        $SQL = $db->prepare("UPDATE `teamspeaks` SET `custom_name` = :custom_name WHERE `id` = :id");
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
                $SQL = $db->prepare("SELECT * FROM `teamspeaks` WHERE `user_id` = :user_id AND `deleted_at` IS NULL ORDER BY `id` DESC");
                $SQL->execute(array(":user_id" => $userid));
                if ($SQL->rowCount() != 0) {
                    while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){

                        if($row['state'] == 'ACTIVE'){
                            $state = '<span class="badge badge-success">Aktiv</span>';
                        } else {
                            $state = '<span class="badge badge-warning">Gesperrt</span>';
                        }

                        if(!is_null($row['locked'])){
                            $state = '<span class="badge badge-warning">Gesperrt</span>';
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
                                            <button type="button" class="btn btn-outline-primary text-uppercase font-weight-bolder" data-dismiss="modal">Abbrechen</button>
                                            <button type="submit" name="saveCustomName" class="btn btn-outline-success text-uppercase font-weight-bolder">Speichern</button>
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
											<span class="svg-icon svg-icon-primary svg-icon-8x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Devices/Mic.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24"/>
                                                    <path d="M12.9975507,17.929461 C12.9991745,17.9527631 13,17.9762852 13,18 L13,21 C13,21.5522847 12.5522847,22 12,22 C11.4477153,22 11,21.5522847 11,21 L11,18 C11,17.9762852 11.0008255,17.9527631 11.0024493,17.929461 C7.60896116,17.4452857 5,14.5273206 5,11 L7,11 C7,13.7614237 9.23857625,16 12,16 C14.7614237,16 17,13.7614237 17,11 L19,11 C19,14.5273206 16.3910388,17.4452857 12.9975507,17.929461 Z" fill="#000000" fill-rule="nonzero"/>
                                                    <rect fill="#000000" opacity="0.3" transform="translate(12.000000, 8.000000) rotate(-360.000000) translate(-12.000000, -8.000000) " x="9" y="2" width="6" height="12" rx="3"/>
                                                </g>
                                            </svg><!--end::Svg Icon--></span>
											<br><br>
                                                <?php if(is_null($row['custom_name'])){ ?>
                                                    <b>Teamspeak</b> #<?= $row['id']; ?> <span data-toggle="tooltip" title="Eigenen Produkt-Namen setzen"><i style="cursor:pointer;" data-toggle="modal" data-target="#productModal<?= $row['id']; ?>" class="far fa-edit"></i></span>
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
                                                <span id="countdown<?= $row['id']; ?>">Lädt...</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="row align-items-center text-center">
                                        <div class="col-md-6">
                                            <a href="<?= env('URL'); ?>manage/teamspeak/<?= $row['id']; ?>" class="btn btn-block btn-outline-primary">
                                                <b>Verwalten</b>
                                            </a>
                                        </div>
										
										<br><br><br>
										
                                        <div class="col-md-6">
                                            <a href="<?= env('URL'); ?>renew/teamspeak/<?= $row['id']; ?>" class="btn btn-block btn-outline-primary">
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
                                    Du hast aktuell noch keinen Teamspeak3-Server 😲
                                </h1>
                                <br>
                                <h4>
                                    Miete dir jetzt einen Prepaid Teamspeak3-Server bei uns!
                                </h4>
                                <br>
                                <p>
                                    <a href="<?= env('URL'); ?>order/teamspeak" class="btn btn-outline-primary text-uppercase font-weight-bolder pulse-red">Jetzt bestellen</a>
                                </p>
                            </div>
                        </div>
                    <?php } ?>

            </div>
        </div>
    </div>
</div>