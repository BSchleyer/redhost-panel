<?php
$currPage = 'team_Rootserver Bestellung_admin';
include BASE_PATH.'app/controller/PageController.php';

$id = $helper->protect($_GET['id']);

$SQLGetServerInfos = $db->prepare("SELECT * FROM `vm_servers` WHERE `id` = :id");
$SQLGetServerInfos->execute(array(":id" => $id));
$serverInfos = $SQLGetServerInfos -> fetch(PDO::FETCH_ASSOC);

if(isset($_POST['acceptService'])){
    $SQL = $db->prepare("UPDATE `vm_servers` SET `notes` = :notes, `state` = 'ACTIVE' WHERE `id` = :id");
    $SQL->execute(array(":notes" => $_POST['notes'], ":id" => $id));

    echo sendSuccess('Rootserver wurde freigeschaltet');
}

if(isset($_POST['declineService'])){
    $SQL = $db->prepare("UPDATE `vm_servers` SET `state` = 'DELETED', `deleted_at` = :deleted_at WHERE `id` = :id");
    $SQL->execute(array(":deleted_at" => $datetime, ":id" => $id));

    $SQL = $db->prepare("UPDATE `ip_addresses` SET `service_id` = NULL, `service_type` = NULL WHERE `id` = :id");
    $SQL->execute(array(":id" => $id));

    $user->addMoney($serverInfos['price'], $serverInfos['user_id']);
    $user->addTransaction($serverInfos['user_id'], $serverInfos['price'],'Storno Rootserverbestellung #'.$id);

    echo sendSuccess('Rootserver wurde abgelehnt');
}

?>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="subheader py-2 py-lg-4 subheader-solid" id="kt_subheader">
        <div class="container-fluid d-flex align-items-center justify-content-between flex-wrap flex-sm-nowrap">
            <form method="post">
                <div class="d-flex align-items-center flex-wrap mr-2">
                    <h5 class="text-dark font-weight-bold mt-2 mb-2 mr-5"><?= env('APP_NAME'); ?></h5>
                    <div class="subheader-separator subheader-separator-ver mt-2 mb-2 mr-4 bg-gray-200"></div>
                    <span class="text-muted font-weight-bold mr-4"><?= $currPageName; ?></span>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="row">

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">

                            <label>UserID</label>
                            <input disabled value="<?= $serverInfos['user_id']; ?>" class="form-control">

                            <br>

                            <label>Benutzername</label>
                            <input disabled value="<?= $user->getDataById($serverInfos['user_id'],'username'); ?>" class="form-control">

                            <br>

                            <label>ID</label>
                            <input disabled value="<?= $id; ?>" class="form-control">

                            <br>

                            <label>Hostname</label>
                            <input disabled value="<?= $serverInfos['hostname']; ?>" class="form-control">

                            <br>

                            <label>Rootpasswort</label>
                            <input disabled value="<?= $serverInfos['password']; ?>" class="form-control">

                            <br>

                            <label>Betriebsystem</label>
                            <input disabled value="<?= $serverInfos['template_id']; ?>" class="form-control">

                            <br>

                            <label>NodeID</label>
                            <input disabled value="<?= $serverInfos['node_id']; ?>" class="form-control">

                            <br>

                            <label>Kerne</label>
                            <input disabled value="<?= $serverInfos['cores']; ?>" class="form-control">

                            <br>

                            <label>Arbeitsspeicher</label>
                            <input disabled value="<?= $serverInfos['memory']; ?>" class="form-control">

                            <br>

                            <label>Festplatte</label>
                            <input disabled value="<?= $serverInfos['disc']; ?>" class="form-control">

                            <br>

                            <label>IP-Adressen</label>
                            <br>
                            <?php foreach ($site->getAddressesFromServer($id) as $ip){ ?>
                                <input disabled value="<?= $ip['ip']; ?>" class="form-control">
                            <?php } ?>

                            <br>

                            <label>Preis</label>
                            <input disabled value="<?= $serverInfos['price']; ?>" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">

                            <form method="post">
                                <div class="row">
                                    <div class="col-md-12">
                                        <label>Notizen</label>
                                        <textarea rows="10" name="notes" class="form-control"></textarea>
                                    </div>

                                    <div class="col-md-12">
                                        <br>
                                        <button class="btn btn-outline-success font-weight-bolder" type="submit" name="acceptService"><i class="fas fa-unlock"></i> Server freischalten</button>
                                        &nbsp;
                                        <button class="btn btn-outline-primary font-weight-bolder" type="submit" name="declineService"><i class="fas fa-times"></i> Server ablehnen</button>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>