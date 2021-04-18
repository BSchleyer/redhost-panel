<?php
if(isset($_POST['login'])){
    $error = null;

    if(isset($_COOKIE['7apwy35m2budptd7']) && $_COOKIE['7apwy35m2budptd7'] == 'y'){
        $captcha_response = $site->validateCaptcha($_POST['h-captcha-response']);
        if($captcha_response == false){
            $error = 'Ungültige Anfrage bitte versuche es erneut (ERR-Captcha)';
        }
    }

    if(empty($_POST['email'])){
        $error = 'Bitte gib eine E-Mail an';
    }

    if(empty($_POST['password'])){
        $error = 'Bitte gib ein Passwort an';
    }

    if(filter_var($_POST['email'],FILTER_VALIDATE_EMAIL) == false){
        $error = 'Bitte gib eine gültige E-Mail an';
    }

    if(!$user->verifyLogin($_POST['email'], $_POST['password'])){
        $error = 'Das angegebene Passwort stimmt nicht';

        //setze cookie für 30 min nach fehlerhaftem login
        setcookie('7apwy35m2budptd7', 'y', time()+'1800','/');
    }

    if($helper->getSetting('login') == 0){
        if($user->getDataByEmail($_POST['email'],'role') == 'support' || $user->getDataByEmail($_POST['email'],'role') == 'admin'){
            //nothing atm
        } else {
            $error = 'Der Login ist derzeit deaktiviert';
        }
    }

    if($user->getState($_POST['email']) == 'pending'){
        $error = 'Bitte bestätige erst deine E-Mail';
    }

    if($user->getState($_POST['email']) == 'banned'){
        $error = 'Dein Account ist gesperrt, wende dich an den E-Mail Support.';
    }

    if(empty($error)){

        if($user->getDataByEmail($_POST['email'],'legal_accepted') == 1 || $_POST['legal_accepted'] == 1){

            if($_POST['legal_accepted'] == 1){
                $SQL = $db->prepare("UPDATE `users` SET `legal_accepted` = :legal_accepted WHERE `email` = :email");
                $SQL->execute(array(":legal_accepted" => '1', ":email" => $_POST['email']));
            }

            $SQL = $db->prepare("UPDATE `users` SET `user_addr` = :user_addr WHERE `email` = :email");
            $SQL->execute(array(":user_addr" => $user->getIP(), ":email" => $_POST['email']));

            $userid = $user->getDataByEmail($_POST['email'], 'id');

            $user->logLogin($userid, $user->getIP(), $user->getAgent());

            $sessionId = $user->generateSessionToken($_POST['email']);
            if(isset($_POST['stayLogged'])){
                setcookie('session_token', $sessionId,time()+'864000','/');
            } else {
                setcookie('session_token', $sessionId,time()+'86400','/');
            }
            $_SESSION['success_msg'] = 'Login Erfolgreich, Willkommen!';
//          echo sendSuccess('Login erfolgreich. Du wirst gleich weitergeleitet');
//          header('refresh:3;url='.$helper->url().'dashboard');
            header('Location: '.$helper->url().'dashboard');

        } else {

            setcookie('7apwy35m2budptd7', null, time(),'/');

            echo "<script>
    function accpept() {
        Swal.fire({
            title: 'AGBs und Datenschutzbestimmungen',
            text: 'Mit dem klick auf Ja bestätigst du das du Unsere AGBs & Datenschutzbestimmungen gelesen hast und diesen zustimmst.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ja ich akzeptiere die AGBs und Datenschutzbestimmungen',
            cancelButtonText: 'Nein'
        }).then((result) => {
            if (result.value) {
                document.getElementById('login_again').submit();
            }
        })
    } accpept();
</script>
<form method='post' id='login_again'>
    <input hidden name='login' value='1'>
    <input hidden name='legal_accepted' value='1'>
    <input hidden name='email' value='".$_POST[email]."'>
    <input hidden name='password' value='".$_POST[password]."'>
</form>";

        }

    } else {
        echo sendError($error);
    }
}