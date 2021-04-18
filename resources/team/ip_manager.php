<?php
$currPage = 'team_IPAM_admin';
include BASE_PATH.'app/controller/PageController.php';

if(isset($_POST['deleteIP'])){
    $error = null;

    if(empty($_POST['ip_id'])){
        $error = 'Die IP existiert nicht';
    }

    if(empty($error)){

        $SQL = $db->prepare("DELETE FROM `ip_addresses` WHERE `id` = :id");
        $SQL->execute(array(":id" => $_POST['ip_id']));

        echo sendSuccess('Die IP wurde aus dem System entfernt');

    } else {
        echo sendError($error);
    }
}

if(isset($_POST['addNewIP'])){
    $error = null;

    if(empty($_POST['node_id'])){
        $error = 'Bitte gebe eine Node ID an';
    }

    if(empty($_POST['ip'])){
        $error = 'Bitte gebe eine IP an';
    }

    if(empty($_POST['cidr'])){
        $error = 'Bitte gebe eine CIDR an';
    }

    if(empty($_POST['gateway'])){
        $error = 'Bitte gebe einen Gateway an';
    }

    if(empty($_POST['mac_address'])){
        $error = 'Bitte gebe eine MAC Adresse an';
    }

    if(empty($error)){

        $SQL = $db->prepare("INSERT INTO `ip_addresses`(`node_id`, `ip`, `cidr`, `gateway`, `mac_address`) VALUES (?,?,?,?,?)");
        $SQL->execute(array($_POST['node_id'], $_POST['ip'], $_POST['cidr'], $_POST['gateway'], $_POST['mac_address']));

        echo sendSuccess('Neue IP wurde hinzugefügt');

    } else {
        echo sendError($error);
    }
}

?>

<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Neue IP hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <label>Node ID</label>
                    <input class="form-control" name="node_id">
                    <br>

                    <label>IP-Adresse</label>
                    <input class="form-control" name="ip">
                    <br>

                    <label>CIDR</label>
                    <input class="form-control" name="cidr" value="32">
                    <br>

                    <label>Gateway</label>
                    <input class="form-control" name="gateway">
                    <br>

                    <label>MAC Adresse</label>
                    <input class="form-control" name="mac_address">
                    <br>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" name="addNewIP" class="btn btn-primary">IP hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="subheader py-2 py-lg-4 subheader-solid" id="kt_subheader">
        <div class="container-fluid d-flex align-items-center justify-content-between flex-wrap flex-sm-nowrap">
            <div class="d-flex align-items-center flex-wrap mr-2">
                <h5 class="text-dark font-weight-bold mt-2 mb-2 mr-5"><?= env('APP_NAME'); ?></h5>
                <div class="subheader-separator subheader-separator-ver mt-2 mb-2 mr-4 bg-gray-200"></div>
                <span class="text-muted font-weight-bold mr-4"><?= $currPageName; ?> <button data-toggle="modal" data-target="#exampleModal" type="button" class="btn btn-primary btn-sm"> Neue IP hinzufügen </button></span>
            </div>
        </div>
    </div>

    <div class="d-flex flex-column-fluid">
        <div class="container">

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-custom card-stretch gutter-b">
                        <div class="card-body d-flex flex-column">
                            <table class="table" id="dataTableLoad">
                                <thead>
                                <tr>
                                    <th scope="col">
                                        #
                                    </th>
                                    <th scope="col">
                                        Server
                                    </th>
                                    <th scope="col">
                                        Node ID
                                    </th>
                                    <th scope="col">
                                        IP
                                    </th>
                                    <th scope="col">
                                        rDNS
                                    </th>
                                    <th scope="col">
                                        Traffic in
                                    </th>
                                    <th scope="col">
                                        Traffic out
                                    </th>
                                    <th scope="col">

                                    </th>
                                </tr>
                                </thead>
                                <tbody class="list">
                                <?php
                                $SQL = $db -> prepare("SELECT * FROM `ip_addresses`");
                                $SQL->execute();
                                if ($SQL->rowCount() != 0) {
                                    while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){

                                        if(is_null($row['service_id'])){
                                            $product = '<span class="badge badge-success">IP FREI</span>';
                                        } else {
                                            $product = '<span class="badge badge-danger">VPS #'.$row['service_id'].'</span>';
                                        }

                                        $first = date("Y-m-d", strtotime("first day of this month"));
                                        $res = $venocix->getTraffic($row['ip'], $first,0);

                                        if(empty($res->data->bytes_in)){
                                            $traffic_in = $helper->human_filesize(0);
                                        } else {
                                            $traffic_in = $helper->human_filesize($res->data->bytes_in);
                                        }

                                        if(empty($res->data->bytes_in)){
                                            $traffic_out = $helper->human_filesize(0);
                                        } else {
                                            $traffic_out = $helper->human_filesize($res->data->bytes_out);
                                        }

                                        ?>
                                        <tr>
                                            <th scope="row"><?= $row['id']; ?></th>
                                            <td><?= $product; ?></td>
                                            <td><?= $row['node_id']; ?></td>
                                            <td><?= $row['ip']; ?></td>
                                            <td><?= $row['rdns']; ?></td>
                                            <td><?= $traffic_in; ?></td>
                                            <td><?= $traffic_out; ?></td>
                                            <td>
                                                <?php if(is_null($row['service_id'])){ ?>
                                                    <form method="post">
                                                        <input hidden name="ip_id" value="<?= $row['id']; ?>">
                                                        <button type="submit" name="deleteIP" class="btn btn-primary btn-sm"> <i class="fas fa-trash"></i> </button>
                                                    </form>
                                                <?php } else { ?>
                                                    <button style="cursor: not-allowed;" type="button" class="btn btn-primary btn-sm" disabled> <i class="fas fa-trash"></i> </button>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>