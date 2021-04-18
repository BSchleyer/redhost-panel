<?php
$currPage = 'back_Mein Profil';
include BASE_PATH.'app/controller/PageController.php';

$firstname = $user->getDataById($userid,'firstname');
$lastname = $user->getDataById($userid,'lastname');
$street = $user->getDataById($userid,'street');
$number = $user->getDataById($userid,'number');
$postcode = $user->getDataById($userid,'postcode');
$city = $user->getDataById($userid,'city');
$country = $user->getDataById($userid,'country');

if(isset($_POST['changeProfile'])){

    $SQL = $db->prepare("UPDATE `users` SET `firstname` = :firstname, `lastname` = :lastname, `street` = :street, `number` = :number, `postcode` = :postcode, `city` = :city, `country` = :country WHERE `id` = :id");
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $street = $_POST['street'];
    $number = $_POST['number'];
    $postcode = $_POST['postcode'];
    $city = $_POST['city'];
    $country = $_POST['country'];
    $SQL->execute(array(":firstname" => $firstname, ":lastname" => $lastname, ":street" => $street, ":number" => $number, ":postcode" => $postcode, ":city" => $city, ":country" => $country, ":id" => $userid));

    echo sendSuccess('Daten wurden gespeichert');
}

$clicks = $cashbox->countClicks($userid);
if(isset($_POST['saveCashbox'])){
    if(empty($_POST['projectname'])){
        $projectname = null;
    } else {
        $projectname = $_POST['projectname'];
    }

    if(empty($_POST['projectlogo'])){
        $projectlogo = null;
    } else {
        $projectlogo = $_POST['projectlogo'];
    }

    if(empty($_POST['cashbox'])){
        $cashbox = 'inactive';
    } else {
        $cashbox = $_POST['cashbox'];
    }

    $SQL = $db->prepare("UPDATE `users` SET `projectname` = :projectname, `projectlogo` = :projectlogo, `cashbox` = :cashbox WHERE `id` = :id");
    $SQL -> execute(array(":projectname" => $projectname, ":projectlogo" => $projectlogo, ":cashbox" => $cashbox, ":id" => $userid));

    echo sendSuccess('Daten wurden gespeichert');
}

if(isset($_POST['deleteLogs'])){

    $SQL = $db -> prepare("UPDATE `login_logs` SET `show`='0' WHERE `user_id` = :user_id");
    $SQL->execute(array(":user_id" => $userid));

    echo sendSuccess('Deine Logs Logs wurden gelöscht');
}

if(isset($_POST['changePassword'])){
    $error = null;

    if(empty($_POST['current_password'])){
        $error = 'Bitte gebe dein aktuelles Passwort an';
    }
    if(empty($_POST['new_password'])){
        $error = 'Bitte gebe dein neues Passwort an';
    }
    if(empty($_POST['new_password_repeat'])){
        $error = 'Bitte wiederhole dein neues Passwort';
    }

    if(!$user->verifyLogin($mail, $_POST['current_password'])){
        $error = 'Dein aktuelles Passwort stimmt nicht';
    }

    if($_POST['new_password'] != $_POST['new_password_repeat']){
        $error = 'Die neuen Passwörter müssen übereinstimmen';
    }

    if(empty($error)){

        $cost = 10;
        $hash = password_hash($_POST['new_password'],PASSWORD_BCRYPT, ['cost' => $cost]);

        $SQL = $db->prepare("UPDATE `users` SET `password` = :password WHERE `id` = :id");
        $SQL -> execute(array(":password" => $hash, ":id" => $userid));

        echo sendSuccess('Dein Passwort wurde geändert');

    } else {
        echo sendError($error);
    }
}

if(isset($_POST['saveMailSettings'])){
    if(isset($_POST['mail_ticket'])){
        $mail_ticket = 1;
    } else {
        $mail_ticket = 0;
    }

    if(isset($_POST['mail_runtime'])){
        $mail_runtime = 1;
    } else {
        $mail_runtime = 0;
    }

    if(isset($_POST['mail_suspend'])){
        $mail_suspend = 1;
    } else {
        $mail_suspend = 0;
    }

    if(isset($_POST['mail_order'])){
        $mail_order = 1;
    } else {
        $mail_order = 0;
    }

    $SQL = $db->prepare("UPDATE `users` SET `mail_ticket` = :mail_ticket, `mail_runtime` = :mail_runtime, `mail_suspend` = :mail_suspend, `mail_order` = :mail_order WHERE `id` = :id");
    $SQL -> execute(array(":mail_ticket" => $mail_ticket, ":mail_runtime" => $mail_runtime, ":mail_suspend" => $mail_suspend, ":mail_order" => $mail_order, ":id" => $userid));

    echo sendSuccess('E-Mail einstellung wurden gespeichert');

}

?>

<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Passwort ändern</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">

                    <label>Aktuelles Passwort</label>
                    <input name="current_password" type="password" class="form-control">

                    <br>

                    <label>Neues Passwort</label>
                    <input name="new_password" type="password" class="form-control">

                    <br>

                    <label>Neues Passwort wiederholen</label>
                    <input name="new_password_repeat" type="password" class="form-control">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary font-weight-bolder" data-dismiss="modal"><i class="fas fa-trash"></i> Abbrechen</button>
                    <button type="submit" name="changePassword" class="btn btn-outline-success font-weight-bolder"><i class="fas fa-exchange-alt"></i> Passwort ändern</button>
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
                <span class="text-muted font-weight-bold mr-4"><?= $currPageName; ?></span>
            </div>
        </div>
    </div>

    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="row">

                <div class="col-md-6">
                    <div class="card shadow mb-5">
                        <div class="card-header">
                            <h3 style="margin-bottom: 0px;">Persönliche Angaben</h3>
                        </div>
                        <div class="card-body">
                            <form method="post">

                                <div class="row">
                                    <div class="col-md-6">
                                        <label>Vorname</label>
                                        <input name="firstname" class="form-control" value="<?= $firstname; ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label>Nachname</label>
                                        <input name="lastname" class="form-control" value="<?= $lastname; ?>">
                                    </div>
                                </div>
                                <br>
                                <div class="row">
                                    <div class="col-md-5">
                                        <label>Straße</label>
                                        <input name="street" class="form-control" value="<?= $street; ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <label>Haus Nr.</label>
                                        <input name="number" class="form-control" value="<?= $number; ?>">
                                    </div>

                                    <div class="col-md-5">
                                        <label>Postleitzahl</label>
                                        <input name="postcode" class="form-control" value="<?= $postcode; ?>">
                                    </div>
                                </div>
                                <br>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label>Stadt</label>
                                        <input name="city" class="form-control" value="<?= $city; ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label>Land</label>
                                        <input name="country" class="form-control" value="<?= $country; ?>">
                                    </div>
                                </div>

                                <br>
                                <button type="submit" name="changeProfile" class="btn btn-outline-primary font-weight-bolder"><i class="fas fa-share-square"></i> Speichern</button>
                                &nbsp;
                                <button type="button" data-toggle="modal" data-target="#exampleModal" class="btn btn-outline-warning font-weight-bolder"><i class="fas fa-exchange-alt"></i> Passwort ändern</button>
                                &nbsp;
                                <a href="<?= $helper->url(); ?>dsgvo" class="btn btn-outline-primary font-weight-bolder">Datenauskunft</a>

                            </form>
                        </div>
                    </div>

                    <br>

                    <div class="card shadow mb-5">
                        <div class="card-body">

                            <form method="post">
                                <label>Cashbox ohne Account benutzbar?</label>
                                <select class="form-control" name="cashbox">
                                    <option value="active" <?php if($user->getDataById($userid,'cashbox') == 'active'){ echo 'selected'; } ?>>Nein</option>
                                    <option value="inactive" <?php if($user->getDataById($userid,'cashbox') == 'inactive'){ echo 'selected'; } ?>>Ja</option>
                                </select>

                                <br>
                                <label>Cashbox Link <small>(<?= $clicks; ?> Klicks bisher)</small></label>
                                <input style="background-color: #292929;" class="form-control" readonly value="<?= env('URL'); ?>d/<?= $userid; ?>">

                                <br>

                                <label>Projektname</label>
                                <input name="projectname" class="form-control" value="<?= $helper->protect($user->getDataById($userid,'projectname')); ?>">

                                <br>

                                <label>Link zum Logo</label>
                                <input name="projectlogo" class="form-control" value="<?= $helper->protect($user->getDataById($userid,'projectlogo')); ?>">

                                <br>

                                <button type="submit" name="saveCashbox" class="btn btn-outline-primary font-weight-bolder"><i class="fas fa-share-square"></i> Speichern</button>
                            </form>

                        </div>
                    </div>

                </div>

                <div class="col-md-6">
                    <div class="card shadow mb-5">
                        <form method="post">
                            <div class="card-header">
                                <h3 style="margin-bottom: 0px;">
                                    Login Logs
                                    <button type="submit" name="deleteLogs" style="float: right; margin-top: -5px;" class="btn btn-outline-primary font-weight-bolder btn-sm"><i class="fas fa-trash"></i> Logs löschen</button>
                                </h3>
                            </div>
                        </form>
                        <div class="card-body">

                            <table class="table" id="dataTableLoad">
                                <thead>
                                <tr>
                                    <th scope="col">IP-Adresse</th>
                                    <th scope="col">Betriebsystem</th>
                                    <th scope="col">Datum</th>
                                </tr>
                                </thead>
                                <tbody class="list">
                                <?php
                                $SQL = $db -> prepare("SELECT * FROM `login_logs` WHERE `user_id` = :user_id AND `show` = '1'");
                                $SQL->execute(array(":user_id" => $userid));
                                if ($SQL->rowCount() != 0) {
                                while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){ ?>
                                    <tr>
                                        <td><?= $row['user_addr']; ?></td>
                                        <td><?= $user->getOS($row['user_agent']); ?></td>
                                        <td><?= $helper->formatDate($row['created_at']); ?></td>
                                    </tr>
                                <?php } } ?>
                                </tbody>
                            </table>

                        </div>
                    </div>

                    <div class="card shadow mb-5">
                        <div class="card-header">
                            <h3 style="margin-bottom: 0px;">
                                E-Mail Einstellungen
                            </h3>
                        </div>
                        <div class="card-body">
                            <form method="post">

                                <label for="mail_ticket" class="checkbox noselect">
                                    <input type="checkbox" <?php if($user->getDataById($userid,'mail_ticket')){ echo 'checked'; } ?> name="mail_ticket" id="mail_ticket">
                                    <span></span>
                                    Support E-Mails
                                </label>
                                <br><br>
                                <label for="mail_runtime" class="checkbox noselect">
                                    <input type="checkbox" <?php if($user->getDataById($userid,'mail_runtime')){ echo 'checked'; } ?> name="mail_runtime" id="mail_runtime">
                                    <span></span>
                                    Produkt Ablauf
                                </label>
                                <br><br>
                                <label for="mail_suspend" class="checkbox noselect">
                                    <input type="checkbox" <?php if($user->getDataById($userid,'mail_suspend')){ echo 'checked'; } ?> name="mail_suspend" id="mail_suspend">
                                    <span></span>
                                    Produkt Suspendierung
                                </label>
                                <br><br>
                                <label for="mail_order" class="checkbox noselect">
                                    <input type="checkbox" <?php if($user->getDataById($userid,'mail_order')){ echo 'checked'; } ?> name="mail_order" id="mail_order">
                                    <span></span>
                                    Produkt Bestellung
                                </label>

                                <br>
                                <br>

                                <button type="submit" name="saveMailSettings" class="btn btn-outline-primary font-weight-bolder"><i class="fas fa-share-square"></i> Einstellungen Speichern</button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>