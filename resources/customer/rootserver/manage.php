<?php
$currPage = 'back_Rootserver verwalten';
include BASE_PATH.'app/controller/PageController.php';

$id = $helper->protect($_GET['id']);

$SQLGetServerInfos = $db->prepare("SELECT * FROM `vm_servers` WHERE `id` = :id AND `type` = 'KVM'");
$SQLGetServerInfos -> execute(array(":id" => $id));
$serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);

if(is_null($serverInfos['venocix_id'])){
    include BASE_PATH.'app/manager/customer/rootserver/manage.php';
} else {
    include BASE_PATH.'app/manager/customer/rootserver/manage_venocix.php';
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

                <?php if($serverInfos['curr_traffic'] >= $available_traffic){ ?>
                <div class="col-md-12">
                    <div class="card shadow mb-5">
                        <div class="card-header"><h1>Traffic Kontingent aufgebraucht 😲</h1></div>
                        <div class="card-body">
                            <p style="font-size: 120%;">
                                Du hast dein aktuelles Traffic Kontingent von <b><?= $available_traffic; ?>GB</b> aufgebraucht.
                                <br>
                                Wenn du deinen Server weiterhin verwenden möchtest, 
                                erweitere dein Traffic Kontingent und dein Server wird sofort wieder freigeschaltet.
                            </p>

                            <form method="post">
                                <select class="form-control" name="traffic_amount">
                                    <option value="512">512GB extra Traffic (7.00€)</option>
                                    <option value="1024">1024GB extra Traffic (14.00€)</option>
                                </select>
                                <br>
                                <button type="submit" name="buyTraffic" class="btn btn-outline-primary font-weight-bolder pulse-red">Extra Traffic kostenpflichtig bestellen</button>
                            </form>

                        </div>
                    </div>
                </div>
                <?php } else { ?>
            </div>

            <nav>
                <div class="nav nav-tabs" id="nav-tab" role="tablist">
                    <a class="nav-item nav-link active" id="nav-main-tab" data-toggle="tab" href="#nav-main" role="tab" aria-controls="nav-main" aria-selected="true">Übersicht</a>
                    <a class="nav-item nav-link" id="nav-token-tab" data-toggle="tab" href="#nav-load" role="tab" aria-controls="nav-load" aria-selected="false">Serverauslastung</a>
                    <a class="nav-item nav-link" id="nav-backup-tab" data-toggle="tab" href="#nav-network" role="tab" aria-controls="nav-network" aria-selected="false">Netzwerk</a>
                </div>
            </nav>

            <br>

            <div class="row">
                <div class="col-md-8">
                    <div class="tab-content" id="nav-tabContent">
                        <div class="tab-pane fade show active" id="nav-main" role="tabpanel" aria-labelledby="nav-main-tab">
                            <div class="card shadow mb-5">
                                <div class="card-header"><h1>Informationen</h1></div>
                                <div class="card-body">

                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <strong>Node-ID:</strong>
                                            </p>
                                        </div>

                                        <?php if(is_null($serverInfos['venocix_id'])){ ?>
                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <span class="ml-2"><?= $serverInfos['node_id']; ?></span>
                                            </p>
                                        </div>
                                        <?php } else { ?>
                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <span class="ml-2">Cloud-Cluster</span>
                                            </p>
                                        </div>
                                        <?php } ?>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <strong>Server-ID:</strong>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <span class="ml-2"><?= $serverInfos['id']; ?></span>
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
                                                <strong>Betriebssystem:</strong>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <span class="ml-2"><?= $serverInfos['template_id']; ?></span>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <strong>Hostname:</strong>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <span class="ml-2"><?= $serverInfos['hostname']; ?></span> <i class="fas fa-question-circle" style="cursor: help" data-toggle="tooltip" data-placement="top" title="" aria-hidden="true" data-original-title="Der Hostname kann nicht zum verbinden per SSH-Client genutzt werden, nutzen kannst du stattdessen die IP unter 'Netzwerk'"></i>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <strong>Status: </strong>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <span class="ml-2"><?= $state; ?></span>
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
                                                    <span id="root_password">************************</span>
                                                    <span style="cursor: pointer;" id="root_icon" onclick="passwordEye('root');">
                                                        <i class="far fa-eye"></i>
                                                    </span>

                                                    <i style="cursor: pointer;" class="fas fa-copy copy-btn" data-clipboard-text="<?=$serverInfos['password']?>" data-toggle="tooltip" title="Passwort kopieren"></i>
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
                                                <span class="ml-2"><?= $serverInfos['cores']; ?></span>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <strong>IP-Adressen:</strong>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <span class="ml-2"><?= $serverInfos['addresses']; ?></span>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <strong>Arbeitsspeicher:</strong>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <span class="ml-2"><span id="memory_text"></span></span>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <strong>Festplatte:</strong>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="text-muted mb-2 font-13">
                                                <span class="ml-2"><?= $serverInfos['disc']; ?> GB SSD</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card shadow mb-5">
                                <div class="card-header"><h1>Informationen</h1></div>
                                <div class="card-body">
                                    <textarea style="background-color: #292929" class="form-control" readonly rows="15"><?= $serverInfos['notes']; ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="nav-load" role="tabpanel" aria-labelledby="nav-load-tab">
                            <div class="card shadow mb-5">
                                <div class="card-header">Serverauslastung</div>
                                <div class="card-body">

                                    <label id="cpu_lable"><b>CPU</b></label>
                                    <div class="progress">
                                        <div id="cpu_progress_bar" class="progress-bar progress-bar-striped progress-bar-animated bg-danger" role="progressbar" style="width: 100%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>

                                    <br>

                                    <label id="memory_lable">Arbeitsspeicher</label>
                                    <div class="progress">
                                        <div id="memory_progress_bar" class="progress-bar progress-bar-striped progress-bar-animated bg-danger" role="progressbar" style="width: 100%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>

                                    <?php if(is_null($serverInfos['venocix_id'])){ ?>
                                    <script>
                                        function refreshStatus() {
                                            <?php if($serverStatus == 'ONLINE'){ ?>
                                            $.getJSON('<?= $helper->url(); ?>ajax/getload/<?= $serverInfos["id"]; ?>', function(data) {
                                                $('#cpu_lable').html('<b>CPU</b> ' + parseFloat((data.data.cpu)*100).toFixed(2) + '%');
                                                $('#cpu_progress_bar').css('width', parseFloat((data.data.cpu)*100).toFixed(0)+'%');

                                                $('#memory_lable').html('<b>Arbeitsspeicher</b> ' + parseFloat((data.data.mem/data.data.maxmem)*100).toFixed(2) + '%');
                                                $('#memory_text').html(humanFileSize(data.data.mem) + ' von ' + humanFileSize(data.data.maxmem));
                                                $('#memory_progress_bar').css('width', parseFloat((data.data.mem/data.data.maxmem)*100).toFixed(0)+'%');
                                            });
                                            <?php } else { ?>
                                            $('#memory_text').html('0 MiB von <?= $serverInfos[memory]; ?>MiB');
                                            $('#disk_text').html('0 MiB von <?= $serverInfos[disc]; ?>GB');
                                            <?php } ?>
                                        }
                                        refreshStatus();
                                        setInterval(function () {
                                            refreshStatus();
                                        }, 5000);
                                    </script>
                                    <?php } else { ?>
                                    <script>
                                        function refreshStatus() {
                                            <?php if($serverStatus == 'ONLINE'){ ?>
                                            $.getJSON('<?= $helper->url(); ?>ajax/getload/<?= $serverInfos["id"]; ?>', function(data) {
                                                $('#cpu_lable').html('<b>CPU</b> ' + parseFloat((data.result.cpu)*100).toFixed(2) + '%');
                                                $('#cpu_progress_bar').css('width', parseFloat((data.result.cpu)*100).toFixed(0)+'%');

                                                $('#memory_lable').html('<b>Arbeitsspeicher</b> ' + parseFloat((data.result.memfree/data.result.mem)*100).toFixed(2) + '%');
                                                $('#memory_text').html(humanFileSize(data.result.memfree) + ' von ' + humanFileSize(data.result.mem));
                                                $('#memory_progress_bar').css('width', parseFloat((data.result.memfree/data.result.mem)*100).toFixed(0)+'%');
                                            });
                                            <?php } else { ?>
                                            $('#memory_text').html('0 MiB von <?= $serverInfos[memory]; ?>MiB');
                                            $('#disk_text').html('0 MiB von <?= $serverInfos[disc]; ?>GB');
                                            <?php } ?>
                                        }
                                        refreshStatus();
                                        setInterval(function () {
                                            refreshStatus();
                                        }, 5000);
                                    </script>
                                    <?php } ?>

                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="nav-network" role="tabpanel" aria-labelledby="nav-network-tab">
                            <div class="card shadow mb-5">
                                <div class="card-header">Netzwerk</div>
                                <div class="card-body">

                                    <?php if(is_null($serverInfos['venocix_id'])){ ?>
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            <th scope="col">IPv4 Adresse</th>
                                            <th scope="col">Gateway</th>
                                            <th scope="col">MAC Adresse</th>
                                            <?php if($serverInfos['api_name'] != 'NO_API'){ ?>
                                            <!--th scope="col">rDNS</th-->
                                            <th scope="col">Traffic In</th>
                                            <th scope="col">Traffic Out</th>
                                            <?php } ?>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($site->getAddressesFromServer($id) as $ip){
                                            if($serverInfos['api_name'] == 'VENOCIX') {
                                                $date1 = date("m", strtotime($serverInfos['created_at']));
                                                $date2 = date("m", strtotime("first day of this month"));
                                                if($date1 == $date2){
                                                    $first = date("Y-m-d", strtotime($serverInfos['created_at']));
                                                } else {
                                                    $first = date("Y-m-d", strtotime("first day of this month"));
                                                }
                                                $res = $venocix->getTraffic($ip['ip'], $first, 0);
                                                $traffic_in = $helper->human_filesize($res->data->bytes_in);
                                                $traffic_out = $helper->human_filesize($res->data->bytes_out);
                                            } else if($serverInfos['api_name'] == 'PLOCIC') {
                                                $traffic_in = $helper->human_filesize(0);
                                                $traffic_out = $helper->human_filesize(0);
                                            } else {
                                                $traffic_in = $helper->human_filesize(0);
                                                $traffic_out = $helper->human_filesize(0);
                                            }
                                        ?>
                                            <tr>
                                                <td><?= $ip['ip']; ?> &nbsp;<i style="cursor: pointer;" class="fas fa-copy copy-btn" data-clipboard-text="<?= $ip['ip']; ?>" data-toggle="tooltip" title="IPv4 kopieren"></i>&nbsp;<span data-toggle="tooltip" title="rDNS setzen"><i data-toggle="modal" data-target="#rdnsModal<?= $ip['id']; ?>" style="cursor: pointer;" class="far fa-edit"></i></span> </td>
                                                <td><?= $ip['gateway']; ?> &nbsp;<i style="cursor: pointer;" class="fas fa-copy copy-btn" data-clipboard-text="<?= $ip['gateway']; ?>" data-toggle="tooltip" title="Gateway kopieren"></i></td>
                                                <td><?= $ip['mac_address']; ?> &nbsp;<i style="cursor: pointer;" class="fas fa-copy copy-btn" data-clipboard-text="<?= $ip['mac_address']; ?>" data-toggle="tooltip" title="Mac Adresse kopieren"></i></td>
                                                <?php if($serverInfos['api_name'] != 'NO_API'){ ?>
                                                <!--td><?= $ip['rdns']; ?></td-->
                                                <td><?= $traffic_in; ?></td>
                                                <td><?= $traffic_out; ?></td>
                                                <?php } ?>
                                            </tr>

                                            <div class="modal fade" id="rdnsModal<?= $ip['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="rdnsModal<?= $ip['id']; ?>Label" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <form method="post">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="rdnsModal<?= $ip['id']; ?>Label">rDNS setzen</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">

                                                                <label>rDNS</label>
                                                                <input class="form-control" name="rdns" value="<?= $ip['rdns']; ?>">
                                                                <input hidden name="ip_addr" value="<?= $ip['ip']; ?>">

                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-outline-primary text-uppercase font-weight-bolder" data-dismiss="modal"><i class="fas fa-ban"></i> Abbrechen</button>
                                                                <button type="submit" name="saveRDNS" class="btn btn-outline-success text-uppercase font-weight-bolder"><i class="fas fa-save"></i> Speichern</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                    <?php } else { ?>
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            <th scope="col">IPv4 Adresse</th>
                                            <th scope="col">Gateway</th>
                                            <th scope="col">MAC Adresse</th>
                                            <?php if($serverInfos['api_name'] != 'NO_API'){ ?>
                                                <!--th scope="col">rDNS</th-->
                                                <th scope="col">Traffic In</th>
                                                <th scope="col">Traffic Out</th>
                                            <?php } ?>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php $i = 0; foreach ($venocix_id->result->output->ips as $ip){
                                            $date1 = date("m", strtotime($serverInfos['created_at']));
                                            $date2 = date("m", strtotime("first day of this month"));
                                            if($date1 == $date2){
                                                $first = date("Y-m-d", strtotime($serverInfos['created_at']));
                                            } else {
                                                $first = date("Y-m-d", strtotime("first day of this month"));
                                            }
                                            $res = $venocix->getTraffic($ip, $first,0);
                                            $traffic_in = $helper->human_filesize($res->data->bytes_in);
                                            $traffic_out = $helper->human_filesize($res->data->bytes_out);
                                        ?>
                                            <tr>
                                                <td><?= $ip; ?> &nbsp;<i style="cursor: pointer;" class="fas fa-copy copy-btn" data-clipboard-text="<?= $ip; ?>" data-toggle="tooltip" title="IPv4 kopieren"></i>&nbsp;<span data-toggle="tooltip" title="rDNS setzen"><i data-toggle="modal" data-target="#rdnsModal<?= $i; ?>" style="cursor: pointer;" class="far fa-edit"></i></span> </td>
                                                <td> </td>
                                                <td> </td>
                                                <?php if($serverInfos['api_name'] != 'NO_API'){ ?>
                                                    <!--td><?= $ip['rdns']; ?></td-->
                                                    <td><?= $traffic_in; ?></td>
                                                    <td><?= $traffic_out; ?></td>
                                                <?php } ?>
                                            </tr>

                                            <div class="modal fade" id="rdnsModal<?= $i; ?>" tabindex="-1" role="dialog" aria-labelledby="rdnsModal<?= $i; ?>Label" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <form method="post">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="rdnsModal<?= $i; ?>Label">rDNS setzen</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">

                                                                <label>rDNS</label>
                                                                <input class="form-control" name="rdns" value="<?= $ip['rdns']; ?>">
                                                                <input hidden name="ip_addr" value="<?= $ip; ?>">

                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-outline-primary text-uppercase font-weight-bolder" data-dismiss="modal"><i class="fas fa-ban"></i> Abbrechen</button>
                                                                <button type="submit" name="saveRDNS" class="btn btn-outline-success text-uppercase font-weight-bolder"><i class="fas fa-save"></i> Speichern</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                    <?php $i++; } ?>

                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow mb-5">
                    <div class="card-header"><h1>Server steuern</h1></div>
                    <!--div class="card-header"><h3>Server steuern</h3></div-->
                        <div class="card-body">

                            <?php if($serverStatus == 'ONLINE'){ ?>
                                <form method="post" id="stopServer">
                                    <input name="sendStop" hidden>
                                        <button type="button" style="cursor: not-allowed;" disabled class="btn btn-outline-success btn-block">
                                            <b><i class="fas fa-play"></i>&nbsp;  Starten </b>
                                        </button>
                                    <button type="button" onclick="stop();" class="btn btn-outline-primary btn-block">
                                        <b><i class="fas fa-stop"></i>&nbsp; Stoppen </b>
                                    </button>
                                </form>
                                <br>
                                <form method="post" id="restartServer">
                                    <input name="sendRestart" hidden>
                                    <button type="button" onclick="restart();" class="btn btn-outline-warning btn-block">
                                        <b><i class="fas fa-power-off"></i>&nbsp; Neustarten </b>
                                    </button>
                                </form>
                            <?php } else { ?>
                                <form method="post">
                                    <button type="submit" name="sendStart" class="btn btn-outline-success btn-block">
                                        <b><i class="fas fa-play"></i>&nbsp; Starten </b>
                                    </button>
                                    <button type="submit" style="cursor: not-allowed;" disabled class="btn btn-outline-primary btn-block" data-toggle="tooltip" title="Der Server ist bereits gestoppt">
                                        <b><i class="fas fa-stop"></i>&nbsp; Stoppen </b>
                                    </button>
                                    <br>
                                    <button type="submit" style="cursor: not-allowed;" disabled class="btn btn-outline-warning btn-block" data-toggle="tooltip" title="Der Server ist nicht gestartet">
                                        <b><i class="fas fa-power-off"></i>&nbsp; Neustarten </b>
                                    </button>
                                </form>
                            <?php } ?>

                            <br><hr><br>

                            <a class="btn btn-block btn-outline-warning" href="<?= $helper->url(); ?>renew/rootserver/<?= $id; ?>"><b><i class="fas fa-history"></i>&nbsp; Verlängern</b></a>
                            <button data-toggle="modal" data-target="#newRootpasswordModal" type="button" class="btn btn-outline-info btn-block"><b><i class="fas fa-key"></i>&nbsp; Neues Rootpasswort</b></button>

                        </div>
                    </div>
                </div>
                <?php } ?>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newRootpasswordModal" tabindex="-1" role="dialog" aria-labelledby="newRootpasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newRootpasswordModalLabel">Neues Rootpasswort setzen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <form method="post" id="resetPassword">

                    <label for="root_password">Rootpasswort</label>
                    <input disabled class="form-control" name="root_password" id="root_password" value="Es wird ein neues Passwort generiert">


                    <br>
                    <input hidden name="setNewRootpassword">
                </form>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary text-uppercase font-weight-bolder" data-dismiss="modal"><i class="fas fa-ban"></i> Nein lieber doch nicht</button>
                <button type="button" class="btn btn-outline-success text-uppercase font-weight-bolder"><i class="fas fa-share-square"></i> Ticket öffnen</button>
                <!--onclick="resetPassword();" -->
            </div>
        </div>
    </div>
</div>

<script>
    function resetPassword() {
        Swal.fire({
            title: 'Neues Rootpasswort setzen?',
            text: "Wenn du auf 'Ja' klickst wird dein Rootpasswort geändert",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ja',
            cancelButtonText: 'Nein'
        }).then((result) => {
            if (result.value) {
                document.getElementById('resetPassword').submit();
            }
        })
    }

    function stop() {
        Swal.fire({
            title: 'Server wirklich stoppen?',
            text: "Wenn du auf 'Ja' klickst wird der Server gestoppt",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ja',
            cancelButtonText: 'Nein'
        }).then((result) => {
            if (result.value) {
                document.getElementById('stopServer').submit();
            }
        })
    }

    function restart() {
        Swal.fire({
            title: 'Server wirklich neustarten?',
            text: "Wenn du auf 'Ja' klickst wird der Server neugestartet",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ja',
            cancelButtonText: 'Nein'
        }).then((result) => {
            if (result.value) {
                document.getElementById('restartServer').submit();
            }
        })
    }

    let rootserver = true;

    function passwordEye(type) {
        if(type == 'root'){
            if(rootserver){
                $('#root_password').html("<?=$serverInfos['password']?>");
                $('#root_icon').html('<i class="far fa-eye-slash"></i>');
                rootserver = false;
            } else {
                $('#root_password').html('************************');
                $('#root_icon').html('<i class="far fa-eye"></i>');
                rootserver = true;
            }
        }
    }
</script>
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