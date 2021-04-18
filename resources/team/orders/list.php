<?php
$currPage = 'team_Rootserver Bestellungen_admin';
include BASE_PATH.'app/controller/PageController.php';
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

                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body d-flex flex-column">
                            <table class="table" id="dataTableLoad">
                                <thead>
                                <tr>
                                    <th scope="col">
                                        #
                                    </th>
                                    <th scope="col">
                                        Benutzername
                                    </th>
                                    <th scope="col">
                                        Bestellt am
                                    </th>
                                    <th scope="col">

                                    </th>
                                </tr>
                                </thead>
                                <tbody class="list">
                                <?php
                                $SQL = $db->prepare("SELECT * FROM `vm_servers` WHERE `state` = 'PENDING' AND `type` = 'KVM'");
                                $SQL->execute();
                                if ($SQL->rowCount() != 0) {
                                while ($row = $SQL->fetch(PDO::FETCH_ASSOC)){ ?>
                                    <tr>
                                        <th scope="row"><?= $row['id']; ?></th>
                                        <td><?= $user->getDataById($row['user_id'],'username'); ?></td>
                                        <td><?= $helper->formatDate($row['created_at']); ?></td>
                                        <td class="ticket-button"><a href="<?= $helper->url(); ?>team/order/<?= $row['id']; ?>" class="btn btn-primary btn-sm">Anschauen</a></td>
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