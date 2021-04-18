<?php
$currPage = 'back_TeamSpeak verwalten';
include BASE_PATH.'app/controller/PageController.php';
include BASE_PATH.'app/manager/customer/teamspeak/manage.php';
?>
<div class="modal fade" id="modalTeamSpeakToken" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-card card" data-toggle="lists" data-options='{"valueNames": ["name"]}'>
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="card-header-title" id="exampleModalCenterTitle">
                                Token hinzufügen
                            </h4>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    </div>
                </div>
                <form method="post">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="department">Servergruppe:</label>
                                    <select class="form-control" id="group" name="group">
                                        <?php
                                        if($serverStatus == 'OFFLINE') { echo '<option>Server Offline</option>'; } else {
                                            $groups = getServerGroups($ts3_query, $serverStatus);
                                            foreach ($groups as $group) {
                                                echo '<option value="' . $group->getId() . '">' . $group . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Beschreibung:</label>
                                    <input type="text" class="form-control form-control-prepended" name="description" placeholder="Deine Token Beschreibung...">
                                </div>
                            </div>
                        </div>
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <button type="submit" name="createToken" class="mt-3 btn btn-block btn-outline-primary"><b>Token erstellen</b></button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
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

            <nav>
                <div class="nav nav-tabs" id="nav-tab" role="tablist">
                    <a class="nav-item nav-link active" id="nav-main-tab" data-toggle="tab" href="#nav-main" role="tab" aria-controls="nav-main" aria-selected="true">Übersicht</a>
                    <a class="nav-item nav-link" id="nav-token-tab" data-toggle="tab" href="#nav-token" role="tab" aria-controls="nav-token" aria-selected="false">Tokenverwaltung</a>
                    <a class="nav-item nav-link" id="nav-backup-tab" data-toggle="tab" href="#nav-backup" role="tab" aria-controls="nav-backup" aria-selected="false">Backup</a>
                    <a class="nav-item nav-link" id="nav-viewer-tab" data-toggle="tab" href="#nav-viewer" role="tab" aria-controls="nav-viewer" aria-selected="false">Viewer</a>
                </div>
            </nav>
            <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade show active" id="nav-main" role="tabpanel" aria-labelledby="nav-main-tab">
                    <br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card shadow mb-5">
                                <div class="card-header"><h1>Übersicht</h1></div>			
                                <div class="card-body">

                                    <table class="table">
                                        <tbody>
                                        <tr>
                                            <td>Status</td>
                                            <td><?= $status_msg; ?></td>
                                        </tr>
										<!--tr>
											<td>IPv4</td>
											<td><a href="ts3server://178.32.161.43:<?= $serverInfos['teamspeak_port']; ?>">178.32.161.43:<?= $serverInfos['teamspeak_port']; ?></a></td>
                                        </tr-->
                                        <tr>
                                            <td>IPv4</td>
                                            <td><a href="ts3server://<?= $serverInfos['teamspeak_ip']; ?>:<?= $serverInfos['teamspeak_port']; ?>"><?= $serverInfos['teamspeak_ip']; ?>:<?= $serverInfos['teamspeak_port']; ?></a></td>
                                        </tr>
                                        <?php if($serverStatus == 'ONLINE'){ ?>
                                            <tr>
                                                <td>User Online</td>
                                                <td><?= getClientsOnline($ts3_query, $serverStatus); ?> / <?= $serverInfos['slots']; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Onlinezeit</td>
                                                <td><?= getOnlineTime($ts3_query); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Version</td>
                                                <td><?= $version['version']; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Channel</td>
                                                <td><?= getChannelCount($ts3_query); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Ping</td>
                                                <td><?= round($connection_info['connection_ping'], 2); ?>ms</td>
                                            </tr>
                                            <tr>
                                                <td>Paketverlust</td>
                                                <td><?php if(round($connection_info['connection_packetloss_total'], 2) == '1'){ echo '0.00'; } else { echo round($connection_info['connection_packetloss_total'], 2); } ?>%</td>
                                            </tr>
                                        <?php } ?>
                                        <tr>
                                            <td>Preis</td>
                                            <td><?= number_format($serverInfos['price'] * $serverInfos['slots'],2); ?>€ / Monat</td>
                                        </tr>
                                        <tr>
                                            <td>Laufzeit</td>
                                            <td><span id="countdown">Lädt...</span></td>
                                        </tr>
                                        </tbody>
                                    </table>

                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card shadow mb-5">
                                <div class="card-header"><h4>Verwaltung</h4></div>
                                <div class="card-body">


                                    <form method="POST" action="">
                                        <div class="row">
                                            <?php if($serverStatus == 'ONLINE'){ ?>
                                                <div class="col-md-6">
                                                    <button style="cursor: not-allowed;" type="button" disabled class="btn btn-outline-success btn-block"><span><b><i class="fas fa-play"></i>&nbsp; Starten</b></span></button>
                                                </div>
                                                <div class="col-md-6">
                                                    <button type="submit" name="sendStop" class="btn btn-outline-primary btn-block"><span><b><i class="fas fa-stop"></i>&nbsp; Stoppen</b></span></button>
                                                </div>
                                            <?php } elseif($serverStatus == 'OFFLINE') { ?>
                                                <div class="col-md-6">
                                                    <button type="submit" name="sendStart" class="btn btn-outline-success btn-block"><span><b><i class="fas fa-play"></i>&nbsp; Starten</b></span></button>
                                                </div>
                                                <div class="col-md-6">
                                                    <button type="button" style="cursor: not-allowed;" disabled class="btn btn-outline-primary btn-block"><span><b><i class="fas fa-stop"></i>&nbsp; Stoppen</b></span></button>
                                                </div>
                                            <?php } ?>

                                            <div class="col-md-6">
                                                <div style="margin-top: 10px;"></div>
                                                <a href="ts3server://<?= $serverInfos['teamspeak_ip']; ?>:<?= $serverInfos['teamspeak_port']; ?>" class="btn btn-outline-danger btn-block"><span><b><i class="fas fa-sign-in-alt"></i>&nbsp; Verbinden</b></span></a>
                                            </div>
                                            <div class="col-md-6">
                                                <div style="margin-top: 10px;"></div>
                                                <button type="button" class="btn btn-outline-danger btn-block" onclick="reinstall();"><span><b><i class="fas fa-redo-alt"></i>&nbsp; Neuinstallieren</b></span></button>
                                            </div>

                                            <div class="col-md-12">
                                                <hr>
                                            </div>

                                            <div class="col-md-6">
                                                <a href="<?= $helper->url(); ?>renew/teamspeak/<?= $id; ?>" class="btn btn-outline-warning btn-block"><span><b><i class="fas fa-history"></i>&nbsp; Produkt verlängern</b></span></a>
                                            </div>
                                            <div class="col-md-6">
                                                <a href="<?= $helper->url(); ?>reconfigure/teamspeak/<?= $id; ?>" class="btn btn-outline-info btn-block"><span><b><i class="fas fa-exchange-alt"></i>&nbsp; Up/Downgrade</b></span></a>
                                            </div>

                                        </div>
                                    </form>

                                    <script>
                                        function reinstall() {
                                            Swal.fire({
                                                title: 'Teamspeak neuinstallieren?',
                                                text: "Wenn du auf 'Ja' klickst werden alle Daten auf dem Teamspeak unwiederruflich gelöscht",
                                                icon: 'warning',
                                                showCancelButton: true,
                                                confirmButtonColor: '#3085d6',
                                                cancelButtonColor: '#d33',
                                                confirmButtonText: 'Ja',
                                                cancelButtonText: 'Nein'
                                            }).then((result) => {
                                                if (result.value) {
                                                    document.getElementById('reinstallServer').submit();
                                                }
                                            })
                                        }
                                    </script>
                                    <form method="post" id="reinstallServer"> <input hidden value="none" name="sendReinstall"> </form>

                                </div>
                            </div>

                        </div>
                    </div>

                </div>
                <div class="tab-pane fade" id="nav-token" role="tabpanel" aria-labelledby="nav-token-tab">
                    <br>

                    <div class="card shadow mb-5">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h4 class="card-header-title">
                                        Tokenverwaltung
                                    </h4>
                                </div>
                                <div class="col-auto">
                                    <?php if($serverStatus == 'ONLINE'){ ?>
                                        <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#modalTeamSpeakToken">
                                            <b><i class="fas fa-key"></i> Token hinzufügen</b>
                                        </button>
                                    <?php } else { ?>
                                        <button class="btn btn-outline-primary btn-sm" disabled style="cursor: not-allowed;">
                                            <b><i class="fas fa-key"></i> Token hinzufügen</b>
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table" id="teamspeak_token">
                                    <thead>
                                    <tr>
                                        <th>Token</th>
                                        <th>Gruppe</th>
                                        <th>Beschreibung</th>
                                        <th>Aktionen</th>
                                    </tr>
                                    </thead>
                                    <tbody class="list">
                                        <form method="post">
                                            <?php
                                            $tokens = listTokens($ts3_query, $serverStatus); if(isset($tokens) && !empty($tokens)){ foreach ($tokens as $token) { ?>
                                                <tr>
                                                    <td style="max-width: 200px; word-wrap: break-word;"><?php echo $token['token']; ?></td>
                                                    <td><?php echo getGroupName($ts3_query, $token['token_id1'])['name']; ?></td>
                                                    <td><?php echo $token['token_description']; ?></td>
                                                    <?php if($serverStatus == 'OFFLINE'){ ?>
                                                        <td><button type="button" disabled="disabled" class="btn btn-outline-primary btn-sm"><b><i class="far fa-trash-alt"></i> Löschen</b></button></td>
                                                    <?php } else { ?>
                                                        <td><button type="submit" name="deleteToken" value="<?php echo $token['token']; ?>" class="btn btn-outline-primary btn-sm"><b><i class="far fa-trash-alt"></i> Löschen</b></button></td>
                                                    <?php } ?>
                                                </tr>
                                            <?php }  } ?>
                                        </form>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="nav-backup" role="tabpanel" aria-labelledby="nav-backup-tab">
                    <br>

                    <div class="modal fade" id="backupModal" tabindex="-1" role="dialog" aria-labelledby="backupModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="backupModalLabel">Backup erstellen</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form method="post">
                                <div class="modal-body">

                                    <label>Backup Beschreibung</label>
                                    <textarea name="desc" required class="form-control" rows="5"></textarea>

                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                                    <button type="submit" name="createSnapshot" class="btn btn-primary">Backup erstellen</button>
                                </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-5">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h4 class="card-header-title">
                                        Backup erstellen
                                    </h4>
                                </div>
                                <div class="col-auto">
                                    <form method="post">
                                        <div class="row">
                                            <?php if($serverStatus == 'OFFLINE'){ ?>
                                                <div class="col-md-12">
                                                    <button type="button" style="cursor: not-allowed;" class="btn btn-outline-primary" disabled="disabled" style="cursor: not-allowed;"><b><i class="fas fa-save"></i> Backup erstellen</b></button>
                                                </div>
                                            <?php } else { ?>
                                                <div class="col-md-12">
                                                    <button type="button" data-toggle="modal" data-target="#backupModal" class="btn btn-outline-primary"><b><i class="fas fa-save"></i> Backup erstellen</b></button>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" data-toggle="lists" data-options='{"valueNames": ["date", "action"]}'>
                                <table class="table">
                                    <thead>
                                    <tr>
                                        <th scope="col">
                                            <a href="#" class="text-muted sort" data-sort="date">Datum</a>
                                        </th>
                                        <th scope="col">
                                            <a href="#" class="text-muted sort" data-sort="action">Beschreibung</a>
                                        </th>
                                        <th scope="col">
                                            <a href="#" class="text-muted sort" data-sort="action">Aktion</a>
                                        </th>
                                        <th scope="col">
                                        </th>
                                    </tr>
                                    </thead>
                                    <tbody id="tbody_tokens">

                                        <?php
                                        $SQL = $db->prepare("SELECT * FROM `teamspeak_backups` WHERE `user_id` = :user_id AND `teamspeak_id` = :ts3_id");
                                        $SQL->execute(array(":user_id" => $userid, ":ts3_id" => $id));
                                        if ($SQL->rowCount() != 0) {
                                        while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){ ?>
                                            <tr>
                                                <td style="max-width: 200px; word-wrap: break-word;"><?= $helper->formatDate($row['created_at']); ?></td>
                                                <?php if($serverStatus == 'OFFLINE'){ ?>
                                                    <td><button type="button" style="cursor: not-allowed;" disabled="disabled" class="btn btn-sm btn-outline-primary"><b><i class="far fa-trash-alt"></i> Backup Löschen</b></button></td>
                                                <?php } else { ?>
                                                    <td>
                                                        <?= $helper->xssFix($row['desc']); ?>
                                                    </td>
                                                    <td>
                                                        <form method="post">
                                                            <button type="submit" name="restoreSnapshot" value="<?= $row['id']; ?>" class="btn btn-sm btn-outline-success"><b><i class="fa fa-recycle"></i> Backup Laden</b></button>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <form method="post">
                                                            <button type="button" onclick="delBackup<?= $row['id']; ?>();" class="btn btn-sm btn-outline-primary"><b><i class="far fa-trash-alt"></i> Backup Löschen</b></button>
                                                        </form>
                                                    </td>
                                                <?php } ?>
                                            </tr>

                                            <script>
                                                function delBackup<?= $row['id']; ?>() {
                                                    Swal.fire({
                                                        title: 'Backup löschen?',
                                                        text: "Wenn du auf 'Ja' klickst wird dieses Backup unwiederruflich gelöscht",
                                                        icon: 'warning',
                                                        showCancelButton: true,
                                                        confirmButtonColor: '#3085d6',
                                                        cancelButtonColor: '#d33',
                                                        confirmButtonText: 'Ja',
                                                        cancelButtonText: 'Nein'
                                                    }).then((result) => {
                                                        if (result.value) {
                                                            document.getElementById('delBackup<?= $row["id"]; ?>').submit();
                                                        }
                                                    })
                                                }
                                            </script>
                                            <form method="post" id="delBackup<?= $row['id']; ?>"> <input hidden name="deleteSnapshot" value="<?= $row['id']; ?>"> </form>
                                        <?php }  } ?>

                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>

                </div>
                <div class="tab-pane fade" id="nav-viewer" role="tabpanel" aria-labelledby="nav-viewer-tab">
                    <br>
                    <div class="card shadow mb-5">
                        <div class="card-header"><h4>Teamspeak Viewer</h4></div>
                        <div class="card-body">
                            <?= getViewer($ts3_query,env('URL').'assets/images/',$serverStatus); ?>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
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