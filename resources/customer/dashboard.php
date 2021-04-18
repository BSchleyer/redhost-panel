<?php
$currPage = 'back_Dashboard';
include BASE_PATH.'app/controller/PageController.php';

$notes = $user->getDataById($userid,'notes');

if(isset($_POST['renewPin'])){
    $s_pin = $user->renewSupportPin($userid);
    echo sendSuccess('Support Pin wurde erneuert');
}

//echo sendSweetInfo('test');

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

                <div class="col-md-3">
                    <div class="card shadow mb-5">
                        <div class="card-body">
                            <div class="media">
                                <div class="media-body">
                                    <p class="text-muted font-weight-medium">Guthaben</p>
                                    <h4 class="mb-0"><?= $amount; ?>€</h4>
                                </div>

                                <div class="mini-stat-icon avatar-sm rounded-circle align-self-center">
                                    <span class="avatar-title">
                                        <span class="svg-icon svg-icon-primary svg-icon-4x">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24"/>
                                                    <circle fill="#000000" opacity="0.3" cx="20.5" cy="12.5" r="1.5"/>
                                                    <rect fill="#000000" opacity="0.3" transform="translate(12.000000, 6.500000) rotate(-15.000000) translate(-12.000000, -6.500000) " x="3" y="3" width="18" height="7" rx="1"/>
                                                    <path d="M22,9.33681558 C21.5453723,9.12084552 21.0367986,9 20.5,9 C18.5670034,9 17,10.5670034 17,12.5 C17,14.4329966 18.5670034,16 20.5,16 C21.0367986,16 21.5453723,15.8791545 22,15.6631844 L22,18 C22,19.1045695 21.1045695,20 20,20 L4,20 C2.8954305,20 2,19.1045695 2,18 L2,6 C2,4.8954305 2.8954305,4 4,4 L20,4 C21.1045695,4 22,4.8954305 22,6 L22,9.33681558 Z" fill="#000000"/>
                                                </g>
                                            </svg>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card mini-stats-wid shadow mb-5">
                        <div class="card-body">
                            <div class="media">
                                <div class="media-body">
                                    <p class="text-muted font-weight-medium">Offene Tickets</p>
                                    <h4 class="mb-0"><?= $user->getOpenTickets($userid); ?></h4>
                                </div>

                                <div class="mini-stat-icon avatar-sm rounded-circle align-self-center">
                                    <!--span class="avatar-title">
                                        <span class="svg-icon svg-icon-primary svg-icon-4x">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24"/>
                                                    <path d="M3,10.0500091 L3,8 C3,7.44771525 3.44771525,7 4,7 L9,7 L9,9 C9,9.55228475 9.44771525,10 10,10 C10.5522847,10 11,9.55228475 11,9 L11,7 L21,7 C21.5522847,7 22,7.44771525 22,8 L22,10.0500091 C20.8588798,10.2816442 20,11.290521 20,12.5 C20,13.709479 20.8588798,14.7183558 22,14.9499909 L22,17 C22,17.5522847 21.5522847,18 21,18 L11,18 L11,16 C11,15.4477153 10.5522847,15 10,15 C9.44771525,15 9,15.4477153 9,16 L9,18 L4,18 C3.44771525,18 3,17.5522847 3,17 L3,14.9499909 C4.14112016,14.7183558 5,13.709479 5,12.5 C5,11.290521 4.14112016,10.2816442 3,10.0500091 Z M10,11 C9.44771525,11 9,11.4477153 9,12 L9,13 C9,13.5522847 9.44771525,14 10,14 C10.5522847,14 11,13.5522847 11,13 L11,12 C11,11.4477153 10.5522847,11 10,11 Z" fill="#000000" opacity="0.3" transform="translate(12.500000, 12.500000) rotate(-45.000000) translate(-12.500000, -12.500000) "/>
                                                </g>
                                            </svg>
                                        </span>
                                    </span-->
                                    <span class="svg-icon svg-icon-primary svg-icon-4x">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                <rect x="0" y="0" width="24" height="24"/>
                                                <path d="M6,2 L18,2 C18.5522847,2 19,2.44771525 19,3 L19,12 C19,12.5522847 18.5522847,13 18,13 L6,13 C5.44771525,13 5,12.5522847 5,12 L5,3 C5,2.44771525 5.44771525,2 6,2 Z M7.5,5 C7.22385763,5 7,5.22385763 7,5.5 C7,5.77614237 7.22385763,6 7.5,6 L13.5,6 C13.7761424,6 14,5.77614237 14,5.5 C14,5.22385763 13.7761424,5 13.5,5 L7.5,5 Z M7.5,7 C7.22385763,7 7,7.22385763 7,7.5 C7,7.77614237 7.22385763,8 7.5,8 L10.5,8 C10.7761424,8 11,7.77614237 11,7.5 C11,7.22385763 10.7761424,7 10.5,7 L7.5,7 Z" fill="#000000" opacity="0.3"/>
                                                <path d="M3.79274528,6.57253826 L12,12.5 L20.2072547,6.57253826 C20.4311176,6.4108595 20.7436609,6.46126971 20.9053396,6.68513259 C20.9668779,6.77033951 21,6.87277228 21,6.97787787 L21,17 C21,18.1045695 20.1045695,19 19,19 L5,19 C3.8954305,19 3,18.1045695 3,17 L3,6.97787787 C3,6.70173549 3.22385763,6.47787787 3.5,6.47787787 C3.60510559,6.47787787 3.70753836,6.51099993 3.79274528,6.57253826 Z" fill="#000000"/>
                                            </g>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card mini-stats-wid shadow mb-5">
                        <div class="card-body">
                            <div class="media">
                                <div class="media-body">
                                    <p class="text-muted font-weight-medium">Monatliche Kosten</p>
                                    <h4 class="mb-0"><?= $user->getMontlyCosts($userid); ?>€</h4>
                                </div>

                                <div class="avatar-sm rounded-circle align-self-center mini-stat-icon">
                                    <span class="avatar-title rounded-circle">
                                        <span class="svg-icon svg-icon-primary svg-icon-4x">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24"/>
                                                    <path d="M2,6 L21,6 C21.5522847,6 22,6.44771525 22,7 L22,17 C22,17.5522847 21.5522847,18 21,18 L2,18 C1.44771525,18 1,17.5522847 1,17 L1,7 C1,6.44771525 1.44771525,6 2,6 Z M11.5,16 C13.709139,16 15.5,14.209139 15.5,12 C15.5,9.790861 13.709139,8 11.5,8 C9.290861,8 7.5,9.790861 7.5,12 C7.5,14.209139 9.290861,16 11.5,16 Z" fill="#000000" opacity="0.3" transform="translate(11.500000, 12.000000) rotate(-345.000000) translate(-11.500000, -12.000000) "/>
                                                    <path d="M2,6 L21,6 C21.5522847,6 22,6.44771525 22,7 L22,17 C22,17.5522847 21.5522847,18 21,18 L2,18 C1.44771525,18 1,17.5522847 1,17 L1,7 C1,6.44771525 1.44771525,6 2,6 Z M11.5,16 C13.709139,16 15.5,14.209139 15.5,12 C15.5,9.790861 13.709139,8 11.5,8 C9.290861,8 7.5,9.790861 7.5,12 C7.5,14.209139 9.290861,16 11.5,16 Z M11.5,14 C12.6045695,14 13.5,13.1045695 13.5,12 C13.5,10.8954305 12.6045695,10 11.5,10 C10.3954305,10 9.5,10.8954305 9.5,12 C9.5,13.1045695 10.3954305,14 11.5,14 Z" fill="#000000"/>
                                                </g>
                                            </svg>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card mini-stats-wid shadow mb-5">
                        <div class="card-body">
                            <div class="media">
                                <div class="media-body">
                                    <p class="text-muted font-weight-medium">Support-Pin</p>
                                    <h4 class="mb-0">
                                        <?= $s_pin; ?>
                                        <i style="cursor: pointer;" class="fas fa-copy copy-btn" data-clipboard-text="<?= $s_pin; ?>" data-toggle="tooltip" title="PIN kopieren"></i>
                                        <i style="cursor: pointer;" onclick="renew();" class="fas fa-sync-alt icon-rotate" data-clipboard-text="<?= $s_pin; ?>" data-toggle="tooltip" title="Neuen PIN generieren"></i>
                                    </h4>
                                </div>

                                <form method="post" id="renewPin">
                                    <input hidden name="renewPin">
                                </form>

                                <script>
                                    function renew() {
                                        document.getElementById('renewPin').submit();
                                    }
                                </script>

                                <div class="avatar-sm rounded-circle align-self-center mini-stat-icon">
                                    <span class="avatar-title rounded-circle">
                                        <span class="svg-icon svg-icon-primary svg-icon-4x">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24"/>
                                                    <circle fill="#000000" opacity="0.3" cx="12" cy="12" r="10"/>
                                                    <path d="M14.5,11 C15.0522847,11 15.5,11.4477153 15.5,12 L15.5,15 C15.5,15.5522847 15.0522847,16 14.5,16 L9.5,16 C8.94771525,16 8.5,15.5522847 8.5,15 L8.5,12 C8.5,11.4477153 8.94771525,11 9.5,11 L9.5,10.5 C9.5,9.11928813 10.6192881,8 12,8 C13.3807119,8 14.5,9.11928813 14.5,10.5 L14.5,11 Z M12,9 C11.1715729,9 10.5,9.67157288 10.5,10.5 L10.5,11 L13.5,11 L13.5,10.5 C13.5,9.67157288 12.8284271,9 12,9 Z" fill="#000000"/>
                                                </g>
                                            </svg>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <br>

            <div class="row">
                <div class="col-md-6">
                    <?php
                    if(isset($_POST['saveNotes'])){
                        $SQL = $db->prepare("UPDATE `users` SET `notes` = :notes WHERE `id` = :id");
                        $SQL->execute(array(":notes" => $_POST['notes'], ":id" => $userid));

                        $notes = $_POST['notes'];

                        echo sendSuccess('Notizen wurden gespeichert');
                    }
                    ?>
                    <form method="post">
                        <div class="card card-custom">
                            <div class="card-body">
                                <textarea class="form-control" name="notes" rows="12"><?= $helper->xssFix($notes); ?></textarea>
                                <br>
                                <button type="submit" name="saveNotes" class="btn btn-outline-primary btn-block"><b><i class="fas fa-save"></i> Speichern</b></button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-md-6 flex-fill d-flex">
                    <div class="card card-custom" style="width: 100%">
                        <div class="card-header border-0 pt-5" style="margin-bottom: -30px;">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label font-weight-bolder text-dark">Neuigkeiten</span>
                            </h3>
                        </div>
                        <div class="card-body" style="margin-bottom: -30px;">

                            <?php
                            $SQL = $db->prepare("SELECT * FROM `news` ORDER BY `id` DESC LIMIT 4");
                            $SQL->execute();
                            if ($SQL->rowCount() != 0) {
                            while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){ ?>
                                <div class="modal fade" id="newsModal<?= $row['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="newsModal<?= $row['id']; ?>Label" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="newsModal<?= $row['id']; ?>Label"><?= $row['title']; ?></h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                        <?= $helper->nl2br2($row['text']); ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-primary text-uppercase font-weight-bolder" data-dismiss="modal"><i class="fas fa-ban"></i> Schließen</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center mb-10" data-toggle="modal" data-target="#newsModal<?= $row['id']; ?>" style="cursor: pointer;">
                                    <div class="symbol symbol-40 mr-5">
                                            <span class="svg-icon svg-icon-primary svg-icon-3x"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24"/>
                                                    <circle fill="#000000" opacity="0.3" cx="12" cy="12" r="10"/>
                                                    <rect fill="#000000" x="11" y="10" width="2" height="7" rx="1"/>
                                                    <rect fill="#000000" x="11" y="7" width="2" height="2" rx="1"/>
                                                </g>
                                            </svg></span>
                                    </div>
                                    <div data-toggle="tooltip" data-placement="top" title="Hier klicken">
                                        <span class="text-dark news-hover"><?= $row['title']; ?></span>
                                    </div>
                                </div>
                            <?php } } ?>

                        </div>
                    </div>
                </div>

                <!--div class="col-md-12"> <br> </div>

                <div class="col-md-6">
                    <div class="card shadow mb-5">
                        <div class="card-body">
                            <div class="row">

                                <div class="col-md-6 text-center">
                                    <br>
                                    <br>
                                    <br>
                                    <i class="fab fa-teamspeak fa-10x"></i>
                                    <br>
                                    <br>
                                    <h4>Unser Teamspeak</h4>
                                    <span class="badge badge-success">ts.red-host.eu</span>
                                    <br>
                                    <br>
                                    <a href="ts3server://ts.red-host.eu" class="btn btn-outline-primary btn-block"><b><i class="fas fa-sign-in-alt"></i> Jetzt verbinden</b></a>
                                </div>

                                <div class="col-md-6">
                                    <?php if($datasavingmode == 0){ ?>
                                        <iframe src="https://discordapp.com/widget?id=698708348790636545&theme=dark" width="280" height="340" allowtransparency="true" frameborder="0"></iframe>
                                    <?php } ?>
                                </div>

                            </div>
                        </div>
                    </div>
                </div-->


            </div>

        </div>
    </div>
</div>