<?php
$currPage = 'team_Benutzerverwaltung_admin';
include BASE_PATH.'app/controller/PageController.php';

if(isset($_POST['createUser'])){

    if($user->exists($_POST['email'])){
        $error = 'Die E-Mail ist bereits vergeben';
    }

    if(empty($error)){

        $password = $helper->generateRandomString('26');
        $user_id = $user->create($_POST['username'], $_POST['email'], $password,'active');

        $user->addMoney($_POST['amount'], $user_id);
        if($_POST['amount'] > 0){
            $user->addTransaction($user_id, $_POST['amount'],'Beta Startguthaben');
        }

        include BASE_PATH.'app/notifications/mail_templates/auth/beta_user.php';
        $mail_state = sendMail($_POST['email'], $_POST['username'], $mailContent, $mailSubject, $emailAltBody);

        echo sendSuccess('Benutzer wurde angelegt');

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
                                        E-Mail
                                    </th>
                                    <th scope="col">
                                        Guthaben
                                    </th>
                                    <th scope="col">
                                        Kunde seit
                                    </th>
                                    <th scope="col">

                                    </th>
                                </tr>
                                </thead>
                                <tbody class="list">
                                <?php
                                $SQL = $db -> prepare("SELECT * FROM `users`");
                                $SQL->execute();
                                if ($SQL->rowCount() != 0) {
                                    while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){
                                        $spin = str_replace('=','',base64_encode($row['s_pin']));
                                        ?>
                                        <tr>
                                            <th scope="row"><?= $row['id']; ?></th>
                                            <td><?= $row['username']; ?></td>
                                            <td><?= $row['email']; ?></td>
                                            <td><?= $row['amount']; ?>€</td>
                                            <td><?= $helper->formatDate($row['created_at']); ?></td>
                                            <td class="ticket-button"><a href="<?= $helper->url(); ?>team/user/<?= $spin; ?>" class="btn btn-outline-primary btn-sm font-weight-bolder"><i class="fas fa-eye"></i> Anschauen</a></td>
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