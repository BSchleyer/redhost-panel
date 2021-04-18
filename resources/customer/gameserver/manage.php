<?php
$currPage = 'back_Gameserver verwalten';
include BASE_PATH.'app/controller/PageController.php';

include BASE_PATH.'app/manager/customer/gameserver/manage.php';
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
                <div class="col-md-8">
                    <div class="card shadow mb-5">
                        <div class="card-header"><h1>Informationen</h1></div>
                        <div class="card-body">

                            <div class="row">
                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Server ID:</strong>
                                    </p>
                                </div>


                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2">GAME-<?= $serverInfos['id']; ?></span>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Status:</strong>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2"><?= $state; ?></span>
                                    </p>
                                </div>

                                <?php if(is_null($serverInfos['custom_name'])){ ?>
                                <?php } else { ?>
                                    <div class="col-md-6">
                                        <p class="text-muted mb-2 font-13">
                                            <strong>Produkt Name:</strong>
                                        </p>
                                    </div>

                                    <div class="col-md-6">
                                        <p class="text-muted mb-2 font-13">
                                            <span class="ml-2"><?= $helper->xssFix($serverInfos['custom_name']); ?></span>
                                        </p>
                                    </div>
                                <?php } ?>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Laufzeit:</strong>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2">
                                            <span id="countdown">Lädt...</span>
                                        </span>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Identifier:</strong>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2"><?= $serverInfos['identifier']; ?></span>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Verwaltungs Url:</strong>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2"><a href="<?= env('PTERODACTYL_USER_URL'); ?>"><?= env('PTERODACTYL_USER_URL'); ?></a> </span>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Benutzername: </strong>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2">game<?= $userid; ?></span>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Passwort:</strong>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2">
                                            <span id="gameserver_password">************************</span>
                                            <span style="cursor: pointer;" id="gameserver_icon" onclick="passwordEye('gameserver');">
                                                <i class="far fa-eye"></i>
                                            </span>

                                            <i style="cursor: pointer;" class="fas fa-copy copy-btn" data-clipboard-text="<?= $user->getDataById($userid,'pterodactyl_password') ?>" data-toggle="tooltip" title="Passwort kopieren"></i>
                                        </span>
                                    </p>
                                </div>


                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Preis:</strong>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2"><?= $serverInfos['price']; ?>€ / Monat</span>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Kerne:</strong>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2"><?= $serverInfos['cpu']; ?></span>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Arbeitsspeicher:</strong>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2"><?= $serverInfos['memory'] / 1024; ?>GB Ram</span>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <strong>Festplatte:</strong>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <p class="text-muted mb-2 font-13">
                                        <span class="ml-2"><?= $serverInfos['disk']/1000; ?>GB SSD</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow mb-5">
                        <div class="card-header"><h1>Verwaltung</h1></div>
                        <div class="card-body">

                            <div class="row">
                                <div class="col-md-12">
                                    <form method="post">
                                        <a href="<?= env('PTERODACTYL_USER_URL'); ?>" target="_blank" class="btn btn-block btn-outline-success"><i class="fas fa-share-square"></i> <b>Einloggen</b></a>
                                    </form>

                                    <div class="mt-5"></div>
                                    <a class="btn btn-block btn-outline-warning" href="<?= $helper->url(); ?>renew/gameserver/<?= $id; ?>"><i class="fas fa-history"></i> <b>Verlängern</b></a>

                                    <div class="mt-5"></div>
                                    <a class="btn btn-block btn-outline-info" href="<?= $helper->url(); ?>reconfigure/gameserver/<?= $id; ?>"><i class="fas fa-exchange-alt"></i> <b>Up / Downgrade</b></a>

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

    let gameserver = true;
    function passwordEye(type) {
        if(type == 'gameserver'){
            if(gameserver){
                $('#gameserver_password').html("<?= $user->getDataById($userid,'pterodactyl_password'); ?>");
                $('#gameserver_icon').html('<i class="far fa-eye-slash"></i>');
                gameserver = false;
            } else {
                $('#gameserver_password').html('************');
                $('#gameserver_icon').html('<i class="far fa-eye"></i>');
                gameserver = true;
            }
        }
    }

    var countDownDate = new Date("<?= $serverInfos['expire_at']; ?>").getTime();
    var x = setInterval(function() {

        var now = new Date().getTime();
        var distance = countDownDate - now;

        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
        var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);

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
            $('#countdown').html(days+days_text+', '+  hours+hours_text+', '+  minutes+minutes_text+' und '+  seconds+seconds_text);
        }

        if (distance <= 0) {
            clearInterval(x);
        }
    }, 1000);
</script>