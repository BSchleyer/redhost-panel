<?php

/*
 * manage all pages
 */
$resources = BASE_PATH . 'resources/';

$auth = $resources . 'auth/';
$customer = $resources . 'customer/';
$sites = $resources . 'sites/';
$team = $resources . 'team/';

$errors = $sites . 'errors/';
$legal = $sites . 'legal/';

$page = $helper->protect($_GET['page']);
if(isset($page)) {
    switch ($page) {
        # default and error pages
        default: include($errors . '404.php'); break;
        case "404": include($errors . '404.php'); break;

        # debug mode
        case "debug": include(BASE_PATH . 'debug/index.php'); break;

        # auth module
        case "auth_login": include($auth . 'login.php'); break;
        case "auth_register": include($auth . 'register.php'); break;
        case "auth_register_conform": include($auth . 'register_confirm.php'); break;
        case "auth_logout": setcookie('session_token', null, time(),'/'); $_SESSION['success_msg'] = 'Ausloggen war erfolgreich.'; header('Location: ' . env('URL') . 'auth/login/'); break;
        case "auth_forgot_password": include($auth . 'forgot_password.php'); break;


        # customer pages
        case "customer_index": include($customer . 'index.php'); break;


    }

    if(strpos($currPage, 'system_') !== false || strpos($currPage, '_auth') !== false) {} else {
        include BASE_PATH . 'resources/additional/footer.php';
    }
} else {
    die('esel.');
}