<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2022 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel
 *  * @file        login.php
 *  * @author      BjörnSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        18.8.2022
 *  * @time        20:23
 *
 */


if(isset($_POST['login_submit'])) {
    $error = null;

    if(isset($_COOKIE['7apwy35m2budptd7']) && $_COOKIE['7apwy35m2budptd7'] == 'y') {
        $captcha_response = $site->validateCaptcha($_POST['h-captcha-response']);

        if($captcha_response == false) {
            $error = 'Ungültige Anfrage, bitte versuche es erneut. (ERR-Captcha)';
        }
    }

    if(empty($_POST['email'])) {
        $error = 'Bitte gib einen Benutzernamen / eine E-Mail ein.';
    }

    if(empty($_POST['password'])) {
        $error = 'Bitte gib ein Passwort ein.';
    }

    if(!$user->verifyLogin($_POST['email'], $_POST['password'])) {
        $error = 'Das angegebene Passwort stimmt nicht.';

        setcookie('7apwy35m2budptd7', 'y', time() + '1800', '/');
    }

    if($helper->getSetting('login') == 0) {
        if($user->getDataByLogin($_POST['email'], 'support' || 'admin')) {
            // nothing to do
        } else {
            $error = 'Der Login ist zurzeit deaktiviert.';
        }
    }

    if($user->verify($_POST['email']) == false) {
        $error = 'Zugangsdaten nicht gefunden.';
    }

    if($user->getState($_POST['email']) == 'pending') {
        $error = 'Bitte bestätige erst dein Kundenkonto.';
    }

    if($user->getState($_POST['email']) == 'banned') {
        $error = 'Dein Account ist gesperrt, bitte wende Dich an den Support.';
    }

    if(empty($error)) {
        // get user id
        $userid = $user->getDataByLogin($_POST['email'], 'id');

        if($user->getDataByLogin($_POST['email'], 'legal_accepted') == 1 || $_POST['legal_accepted'] == 1) {
            if($_POST['legal_accepted'] == 1) {
                $SQL = Helper::db()->prepare("UPDATE `users` SET `legal_accepted` = :legal_accepted WHERE `email` = :email OR `username` = :username");
                $SQL->execute(array(":legal_accepted" => '1', ":email" => $_POST['email'], ":username" => $_POST['email']));
            }

            $SQL = Helper::db()->prepare("UPDATE `users` SET `user_addr` = :user_addr WHERE `email` = :email OR `username` = :username");
            $SQL->execute(array(":user_addr" => '172.19.0.1' /*$user->getIP()*/, ":email" => $_POST['email'], ":username" => $_POST['email']));

            $user->logLogin($userid, '172.19.0.1' /*$user->getIP()*/, $user->getAgent(), $user->getLocation());

            $sessionId = $user->generateSessionToken($_POST['email']);

            if(isset($_POST['stayLogged'])){
                setcookie('session_token', $sessionId, time()+'864000','/');
            } else {
                setcookie('session_token', $sessionId, time()+'86400','/');
            }


            $_SESSION['success_msg'] = 'Dein Login war erfolgreich.';
            header('Location: ' . env('URL') . 'index/');
        } else {
            setcookie('7apwy35m2budptd7', null, time(), '/');

            echo "<script>
                   function accept() {
                       Swal.fire({
                           title: 'AGBs und Datenschutzbestimmungen',
                           text: 'Mit dem Klick auf Ja bestätigst du das du unsere AGBs & Datenschutzbestimmungen gelesen hast und diesen zustimmst.',
                           icon: 'warning',
                           showCancelButton: true,
                           confirmButtonColor: '#28a745',
                           cancelButtonColor: '#d33',
                           confirmButtonText: 'Ja, ich akzeptiere die AGBs und Datenschutzbestimmungen.',
                           cancelButtonText: 'Nein'
                       }).then((result) => {
                           if (result.value) {
                               document.getElementById('login_again').submit();
                           }
                       })
                   } accept();
                   </script>
                   <form method='post' id='login_again'>
                       <input hidden name='login_submit' value='1'>
                       <input hidden name='legal_accepted' value='1'>
                       <input hidden name='email' value='" . $_POST[email] . "'>
                       <input hidden name='password' value='" . $_POST[password] . "'>
                   </form>";
        }

    } else {
        echo sendError($error);
    }
}