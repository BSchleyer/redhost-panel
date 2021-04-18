<?php
$currPage = 'system_DSGVO Auskunft';
include BASE_PATH.'app/controller/PageController.php';
if($user->loggedIn($_COOKIE['session_token']) == false){
    header('Location: '.$helper->url().'login');
    die();
}

$SQL = $db->prepare("SELECT * FROM `users` WHERE `id` = :id");
$SQL->execute(array(":id" => $userid));
$userInfo = $SQL->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <title>Datenauskunft</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
</head>
<body>
<div class="row justify-content-center">
    <div class="col-md-10">

        <img src="https://i.imgur.com/NVXW1Dc.png">
        <hr>
        <br>

        <h1>Kundendaten</h1>

        <div class="row">
            <div class="col-md-2">Name</div> <div class="col-md-9"> <?php echo $userInfo['firstname']; ?> <?php echo $userInfo['lastname']; ?></div>
            <div class="col-md-2">Anschrift</div> <div class="col-md-9"><?php echo $userInfo['street']; ?> <?php echo $userInfo['street_number']; ?> <?php echo $userInfo['postcode']; ?> <?php echo $userInfo['city']; ?></div>
            <br><br>
            <div class="col-md-2">User-ID</div> <div class="col-md-9"><?php echo $userInfo['id']; ?></div>
            <div class="col-md-2">Benutzername</div> <div class="col-md-9"><?php echo $userInfo['username']; ?></div>
            <div class="col-md-2">E-Mail</div> <div class="col-md-9"><?php echo $userInfo['email']; ?></div>
            <div class="col-md-2">Letzte änderung am Account</div> <div class="col-md-9"><?php echo $userInfo['updated_at']; ?></div>
            <div class="col-md-2">Registriert am</div> <div class="col-md-9"><?php echo $userInfo['created_at']; ?></div>
        </div>

        <br>

        <h1>Transaktionen</h1>
        <table class="table">
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Type</th>
                <th scope="col">Betrag</th>
                <th scope="col">Beschreibung</th>
                <th scope="col">Status</th>
                <th scope="col">Erstellt am</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $SQL = $db->prepare("SELECT * FROM `user_transactions` WHERE `user_id` = :user_id");
            $SQL->execute(array(":user_id" => $userid));
            if ($SQL->rowCount() != 0) {
                while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {
                    ?>
                    <tr>
                        <th scope="row"><?php echo $row['id']; ?></th>
                        <td><?php echo $row['type']; ?></td>
                        <td><?php echo $row['amount']; ?></td>
                        <td><?php echo $row['desc']; ?></td>
                        <td><?php echo $row['state']; ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                    </tr>
                <?php } } ?>
            </tbody>
        </table>

        <br>
        <h1>Zugriffslog</h1>
        <table class="table">
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">User-ID</th>
                <th scope="col">Datum / Uhrzeit</th>
                <th scope="col">IP-Adresse</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $SQL = $db->prepare("SELECT * FROM `login_logs` WHERE `user_id` = :user_id AND `show` = '1'");
            $SQL->execute(array(":user_id" => $userid));
            if ($SQL->rowCount() != 0) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {

                    $ip = preg_replace('~(\d+)\.(\d+)\.(\d+)\.(\d+)~', "$1.$2.$3.XXX", $row['user_addr']);
                    $ip6 = preg_replace('~(\d+)\.(\d+)\.(\d+)\.(\d+)\.(\d+)\.(\d+)\.(\d+)~', "$1.$2.$3.$4.$5.$6.$7.XXX", $row['user_addr']);

                ?>
                <tr>
                    <th scope="row"><?php echo $row['id']; ?></th>
                    <td><?php echo $row['user_id']; ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td><?php echo $ip; ?></td>
                </tr>
                <?php } } ?>
            </tbody>
        </table>

        <br>
        <h1>Dienste</h1>
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Art</th>
                    <th scope="col">Datum / Uhrzeit</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $SQL = $db->prepare("SELECT * FROM `webspace` WHERE `user_id` = :user_id");
            $SQL->execute(array(":user_id" => $userid));
            if ($SQL->rowCount() != 0) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {?>
                <tr>
                    <th scope="row"><?php echo $row['id']; ?></th>
                    <td>Webspace <?php if(!is_null($row['deleted_at'])){ echo '(Gelöscht)'; } ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                </tr>
            <?php } } ?>
            <?php
            $SQL = $db->prepare("SELECT * FROM `vm_servers` WHERE `user_id` = :user_id");
            $SQL->execute(array(":user_id" => $userid));
            if ($SQL->rowCount() != 0) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {?>
                <tr>
                    <th scope="row"><?php echo $row['id']; ?></th>
                    <td>Virtual Server <?php if(!is_null($row['deleted_at'])){ echo '(Gelöscht)'; } ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                </tr>
            <?php } } ?>
            <?php
            $SQL = $db->prepare("SELECT * FROM `teamspeaks` WHERE `user_id` = :user_id");
            $SQL->execute(array(":user_id" => $userid));
            if ($SQL->rowCount() != 0) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {?>
                <tr>
                    <th scope="row"><?php echo $row['id']; ?></th>
                    <td>Teamspeak <?php if(!is_null($row['deleted_at'])){ echo '(Gelöscht)'; } ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                </tr>
            <?php } } ?>
            </tbody>
        </table>

        <br>
        <h1>Chats</h1>
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Nachricht</th>
                    <th scope="col">Datum / Uhrzeit</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $SQL = $db->prepare("SELECT * FROM `ticket_message` WHERE `writer_id` = :user_id");
            $SQL->execute(array(":user_id" => $userid));
            if ($SQL->rowCount() != 0) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {?>
                <tr>
                    <th scope="row"><?php echo $row['id']; ?></th>
                    <td><?php echo $row['message']; ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                </tr>
            <?php } } ?>
            </tbody>
        </table>

    </div>
</div>
</body>
</html>
