<?php

if($user->sessionExists($_COOKIE['session_token'])){

    /*
     * set static values
     */
    $username = $user->getDataBySession($_COOKIE['session_token'],'username');
    $mail = $user->getDataBySession($_COOKIE['session_token'],'email');
    $amount = $user->getDataBySession($_COOKIE['session_token'],'amount');
    $userid = $user->getDataBySession($_COOKIE['session_token'],'id');
    $affiliate_id = $user->getDataBySession($_COOKIE['session_token'],'affiliate_id');
    $s_pin = $user->getDataBySession($_COOKIE['session_token'],'s_pin');

    $user_addr = $user->getDataBySession($_COOKIE['session_token'],'user_addr');
    if(is_null($user_addr)){
        $SQL = $db->prepare("UPDATE `users` SET `user_addr` = :user_addr WHERE `id` = :id");
        $SQL->execute(array(":user_addr" => $user->getIP(), ":id" => $userid));
        $user_addr = $user->getIP();
    }
    if($user->getIP() != $user_addr){
        if(isset($_COOKIE['old_session_token'])){
            if($user->isInTeam($_COOKIE['old_session_token'])){

            } else {
                $_SESSION['info_msg'] = 'Session Expired';
                setcookie('session_token', null, time(), '/'); header('Location: '.$helper->url().'login');
                die();
            }
        } else {
            $_SESSION['info_msg'] = 'Session Expired';
            setcookie('session_token', null, time(), '/'); header('Location: '.$helper->url().'login');
            die();
        }
    }

    if($user->getState($mail) == 'pending' && $currPage != 'back_Account aktivieren'){
        $_SESSION['info_msg'] = 'Bitte bestätige deinen Account';
        header('Location: '.env('URL').'logout');
        die();
    }

}

/*
 * logged in user check
 */
if(strpos($currPage,'_auth') !== false) {
    if($user->sessionExists($_COOKIE['session_token'])){
        die(header('Location: '.env('URL').'dashboard'));
    }
}

if (strpos($currPage,'back_') !== false || strpos($currPage,'team_') !== false) {

    /*
     * check if user is logged in
     */
    if($currPage != 'back_Spenden_hidehead'){
        if(!($user->loggedIn($_COOKIE['session_token']))){
            die(header('Location: '.env('URL').'login'));
        }
    }

    /*
     * check if user is on team page and is in team
     */
    if(strpos($currPage,'team_') !== false) {
        if(!$user->isInTeam($_COOKIE['session_token'])){
            die(header('Location: '.env('URL').'dashboard'));
        }
    }

    /*
     * check if user is on admin page and is admin
     */
    if(strpos($currPage,'_admin') !== false) {
        if(!$user->isAdmin($_COOKIE['session_token'])){
            die(header('Location: '.env('URL').'dashboard'));
        }
    }

}

$currPageName = explode('_',$currPage)[1];
if (strpos($currPage,'system_') !== false) {} else {
    include BASE_PATH.'resources/additional/head.php';
    if ($user->sessionExists($_COOKIE['session_token'])) {

        if (strpos($currPage,'_hidelayout') !== false) {} else {
            if (strpos($currPage,'_hidehead') !== false) {} else {

                include BASE_PATH . 'resources/additional/header_mobile.php';
                echo '<div class="d-flex flex-column flex-root"> <div class="d-flex flex-row flex-column-fluid page">';
                include BASE_PATH . 'resources/additional/a_side.php';
                echo '<div class="d-flex flex-column flex-row-fluid wrapper" id="kt_wrapper">';
                include BASE_PATH . 'resources/additional/header.php';

            }
        }
    } else {
        if (strpos($currPage,'_auth') !== false) {} else {
            echo '<div class="d-flex flex-column flex-root"> <div class="d-flex flex-row flex-column-fluid page">';
            include BASE_PATH . 'resources/additional/FRONT/a_side.php';
            echo '<div class="d-flex flex-column flex-row-fluid wrapper" id="kt_wrapper">';
        }
    }

    include BASE_PATH.'app/notifications/sendAlert.php';
    include BASE_PATH.'app/notifications/sendSweetAlert.php';

    /*
     * manage cookies
     */
    if(isset($_SESSION['error_msg']) && !empty($_SESSION['error_msg'])){
        echo sendError($_SESSION['error_msg']);
        $_SESSION['error_msg'] = '';
        unset($_SESSION['error_msg']);
    }

    if(isset($_SESSION['info_msg']) && !empty($_SESSION['info_msg'])){
        echo sendInfo($_SESSION['info_msg']);
        $_SESSION['info_msg'] = '';
        unset($_SESSION['info_msg']);
    }

    if(isset($_SESSION['success_msg']) && !empty($_SESSION['success_msg'])){
        echo sendSuccess($_SESSION['success_msg']);
        $_SESSION['success_msg'] = '';
        unset($_SESSION['success_msg']);
    }

}