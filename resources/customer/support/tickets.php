<?php
$currPage = 'back_Tickets';
include BASE_PATH.'app/controller/PageController.php';

if(isset($_POST['createTicket'])){
    $error = null;

    if(empty($_POST['title'])){
        $error = 'Bitte gebe einen Titel an';
    }

    if(empty($_POST['category'])){
        $error = 'Bitte wähle eine Kategorie';
    }

    if(empty($_POST['priority'])){
        $error = 'Bitte wähle eine Priorität';
    }

    if(empty($_POST['message'])){
        $error = 'Bitte gebe eine Nachricht an';
    }

    if($_POST['csrf_token'] != $_SESSION['csrf_token']){
        $error = 'Ungültige Anfrage bitte versuche es erneut!';
    }

    if(empty($error)){

        $DB_SQL = $db;
        $SQL = $DB_SQL->prepare("INSERT INTO `tickets`(`user_id`, `categorie`, `priority`, `title`, `state`, `last_msg`) VALUES (:user_id,:categorie,:priority,:title,:status,:last_msg)");
        $SQL->execute(array(":user_id" => $userid, ":categorie" => $_POST['category'], ":priority" => $_POST['priority'], ":title" => $_POST['title'], ":status" => 'OPEN', ":last_msg" => 'CUSTOMER'));
        $ticket_id = $DB_SQL->lastInsertId();

        $SQL = $db->prepare("INSERT INTO `ticket_message`(`ticket_id`, `writer_id`, `message`) VALUES (:ticket_id,:writer_id,:message)");
        $SQL->execute(array(":ticket_id" => $ticket_id, ":writer_id" => $userid, ":message" => $_POST['message']));

        //$discord->callWebhook('Neues Ticket: '.$_POST['title']);
        //$discord->callWebhook('Neues Ticket: '.$_POST['title'], 'https://discordapp.com/api/webhooks/767905875909541921/hIJK4Nf2seIWGIeCH8B-SOfwdcFVEZzAqJWkZtQpkkyP53-zgKF2Q40sXUQBflBxz5-K');

        $SQL = $db -> prepare("SELECT * FROM `users` WHERE `role` = 'support' OR `role` = 'admin'");
        $SQL->execute();
        if ($SQL->rowCount() != 0) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {

                include BASE_PATH.'app/notifications/mail_templates/support/new_ticket.php';
                $mail_state = sendMail($row['email'], $row['username'], $mailContent, $mailSubject);

            }
        }

        header('Location: '.env('URL').'ticket/'.$ticket_id);
        die();
    } else {
        echo sendError($error);
    }
}

//$s_pin = $user->renewSupportPin($userid);
?>
<form method="post">
    <input name="csrf_token" value="<?php $csrf_token = $site->generateCSRF(); echo $csrf_token; $_SESSION['csrf_token'] = $csrf_token; ?>" type="hidden">

    <div class="modal fade bd-example-modal-lg" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Neues Ticket erstellen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <label>Titel:</label>
                    <input class="form-control" name="title" required="required">

                    <br>

                    <div class="row">
                        <div class="col-md-6">
                            <label>Kategorie:</label>
                            <select class="form-control" name="category" required="required">
                                <option value="ALLGEMEIN">Allgemein</option>
                                <option value="TECHNIK">Technik</option>
                                <option value="BUCHHALTUNG">Buchhaltung</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Priorität:</label>
                            <select class="form-control" name="priority" required="required">
                                <option value="LOW">Niedrig</option>
                                <option value="MITTEL" selected>Mittel</option>
                                <option value="HOCH">Hoch</option>
                            </select>
                        </div>
                    </div>

                    <br>

                    <label>Beschreibung:</label>
                    <textarea class="form-control" name="message" rows="5" required="required"></textarea>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-dismiss="modal"><i class="fas fa-times"></i> <b>Schließen</b></button>
                    <button type="submit" class="btn btn-outline-success" name="createTicket"><i class="fas fa-share-square"></i> <b>Ticket erstellen</b></button>
                </div>
            </div>
        </div>
    </div>
</form>

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

            <a href="#" data-toggle="modal" data-target="#exampleModal" class="btn btn-outline-primary font-weight-bolder">
            <i class="fas fa-share-square"></i> Neues Ticket erstellen
            </a>

            <br>
            <br>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-custom card-stretch gutter-b shadow mb-5">
                        <div class="card-body d-flex flex-column">
                            <table class="table" id="dataTableLoad">
                                <thead>
                                <tr>
                                    <th scope="col">
                                        #
                                    </th>
                                    <th scope="col">
                                        Betreff
                                    </th>
                                    <th scope="col">
                                        Abteilung
                                    </th>
                                    <th scope="col">
                                        Priorität
                                    </th>
                                    <th scope="col">
                                        Status
                                    </th>
                                    <th scope="col">
                                        Letzte Antwort
                                    </th>
                                    <th scope="col">
                                        Erstellt am
                                    </th>
                                    <th scope="col">

                                    </th>
                                </tr>
                                </thead>
                                <tbody class="list">
                                <?php
                                $SQL = $db -> prepare("SELECT * FROM `tickets` WHERE `user_id` = :user_id");
                                $SQL->execute(array(":user_id" => $userid));
                                if ($SQL->rowCount() != 0) {
                                    while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){

                                        if($row['state'] == 'OPEN'){
                                            $status = '<span class="badge badge-success">Offen</span>';
                                        } elseif($row['state'] == 'CLOSED'){
                                            $status = '<span class="badge badge-danger">Geschlossen</span>';
                                        }

                                        if($row['last_msg'] == 'CUSTOMER'){
                                            $last_msg = '<span class="badge badge-warning">Kundenantwort</span>';
                                        } elseif($row['last_msg'] == 'SUPPORT'){
                                            $last_msg = '<span class="badge badge-success">Supportantwort</span>';
                                        }

                                        if($row['priority'] == 'LOW'){
                                            $priority = 'Niedrig';
                                        } elseif($row['priority'] == 'MITTEL'){
                                            $priority = 'Mittel';
                                        } elseif($row['priority'] == 'HOCH'){
                                            $priority = 'Hoch';
                                        }

                                        ?>
                                        <tr>
                                            <th scope="row"><?= $row['id']; ?></th>
                                            <td><?= $helper->xssFix($row['title']); ?></td>
                                            <td><?= ucfirst(strtolower($row['categorie'])); ?></td>
                                            <td><?= $priority; ?></td>
                                            <td><?= $status; ?></td>
                                            <td><?= $last_msg; ?></td>
                                            <td><?= $helper->formatDate($row['created_at']); ?></td>
                                            <td><a href="<?= $helper->url(); ?>ticket/<?= $row['id']; ?>" class="btn btn-outline-primary btn-sm font-weight-bolder"><i class="fas fa-eye"></i> Anschauen</a></td>
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