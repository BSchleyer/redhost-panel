<?php
$currPage = 'team_Codeverwaltung_admin';
include BASE_PATH . 'app/controller/PageController.php';


if (isset($_POST['createCode'])) {

    $code = $helper->xssFix($_POST['code']);
    $useable = $helper->xssFix($_POST['useable']);
    $amount = $helper->xssFix($_POST['amount']);

    if (empty($code)) {
        $error = 'Bitte gib einen Code an.';
    }

    if (empty($useable)) {
        $error = 'Bitte gib an, wie oft der Code benutzt werden kann.';
    }

    if (empty($amount)) {
        $error = 'Bitte gib einen Wert für den Code ein.';
    }


    if (empty($error)) {

        $SQL = $db->prepare("INSERT INTO `codes` SET `code` = :code, `useable` = :useable, `amount` = :amount");
        $SQL->execute(array(":code" => $_POST['code'], ":useable" => $_POST['useable'], ":amount" => $_POST['amount']));

        echo sendSuccess('Code hinzugefügt');
    } else {
        echo sendError($error);
    }
}
?>

<form method="post">

    <div class="modal fade bd-example-modal-lg" id="exampleModal" tabindex="-1" role="dialog"
         aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Neuen Code erstellen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <label>Code:</label>
                    <input class="form-control" name="code" required="required">

                    <br>

                    <label>Nutzbar:</label>
                    <input class="form-control" name="useable" type="number" required="required">

                    <br>

                    <label>Wert:</label>
                    <input class="form-control" name="amount" type="text" required="required">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-dismiss="modal"><i
                                class="fas fa-times"></i> <b>Schließen</b></button>
                    <button type="submit" class="btn btn-outline-success" name="createCode"><i
                                class="fas fa-share-square"></i> <b>Code erstellen</b></button>
                </div>
            </div>
        </div>
    </div>
</form>


<div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    <div class="d-flex flex-column-fluid">
        <div class="container">

            <a href="#" data-toggle="modal" data-target="#exampleModal"
               class="btn btn-outline-primary font-weight-bolder mb-4">
                <i class="fas fa-share-square"></i> Neuen Code hinzufügen
            </a>

            <div class="row">

                <div class="col-md-12">
                    <div class="card card-custom card-stretch gutter-b">
                        <div class="card-body d-flex flex-column">
                            <table id="table1" class="table dt-responsive nowrap">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Code</th>
                                    <th>Belohnung</th>
                                    <th>Nutzbar</th>
                                    <th>Genutzt</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $SQL = $db->prepare("SELECT * FROM `codes`");
                                $SQL->execute();
                                if ($SQL->rowCount() != 0) {
                                    while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) { ?>
                                        <tr>
                                            <td><?= $row['id']; ?></td>
                                            <td><?= $row['code']; ?></td>
                                            <td><?= $row['amount']; ?>€</td>
                                            <td><?= $row['useable']; ?></td>

                                            <?php
                                            $code = $row['code'];
                                            ?>


                                            <?php
                                            $SQL2 = $db->prepare("SELECT * FROM `code_used` WHERE `code` = :code");
                                            $SQL2->execute(array(":code" => $code));
                                            if ($SQL2->rowCount() != 0) {
                                                while ($row2 = $SQL2->fetch(PDO::FETCH_ASSOC)) { ?>
                                                <?php }} ?>

                                            <td data-toggle="tooltip"
                                                title="">
                                                <?= $SQL2->rowCount(); ?>
                                            </td>

                                        </tr>
                                    <?php }} ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>