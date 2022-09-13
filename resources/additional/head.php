<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2022 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel
 *  * @file        head.php
 *  * @author      BjörnSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        16.8.2022
 *  * @time        22:34
 *
 */

$datasavingmode = $user->getDataById($userid,'datasavingmode');
$darkmode = $user->getDataById($userid, 'darkmode');
$livechat = $user->getDataById($userid, 'livechat');


if(isset($_POST['saveSettings'])) {
    $error = null;

    if (isset($_POST['datasavingmode'])) {
        $datasavingmode = true;
    } else {
        $datasavingmode = false;
    }

    if (isset($_POST['livechat'])) {
        $livechat = true;
    } else {
        $livechat = false;
    }

    if (isset($_POST['darkmode'])) {
        $darkmode = true;
    } else {
        $darkmode = false;
    }


    $SQL = $db->prepare("UPDATE `users` SET `datasavingmode` = :datasavingmode, `darkmode` = :darkmode, `livechat` = :livechat WHERE `id` = :id");
    $SQL->execute(array(":datasavingmode" => $datasavingmode, ":darkmode" => $darkmode, ":livechat" => $livechat, ":id" => $userid));

    $_SESSION['success_msg'] = 'Einstellungen wurden gespeichert.';
}
?>

<!doctype html>
<html lang="en">

<head>
    <!--

    /*
     * Developed by Schleyer-EDV, Björn Schleyer - Backend from Schleyer-EDV Framework
     * For: RED-Host Panel
     * www.schleyer-edv.de
     * Copyright 2006-2021 Schleyer-EDV
    */

    -->
    <meta charset="utf-8">
    <meta name="viewpower" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="author" content="Schleyer-EDV, Bjoern Schleyer">
    <meta name="msapplication-tap-highlight" content="no">
    <meta name="theme-color" content="#0635c9">
    <meta name="keywords" content="<?= env('SEO_KEYWORDS'); ?>">
    <meta http-equiv="name" content="<?= env('SEO_HTTPEQUIV_NAME'); ?>">
    <meta name="name" content="<?= env('SEO_NAME'); ?>">

    <title><?= $currPageName; ?> | <?= env('APP_NAME'); ?></title>

    <!-- javascript (jquery) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>

    <!--begin::Fonts-->
    <link rel="stylesheet" href="<?= env('STYLE_URL'); ?>fonts/poppins/poppins.css" />
    <!--end::Fonts-->

    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= $helper->imageUrl(); ?>icon.png" />

    <?php if(strpos($currPage, '_auth')) { ?>
        <link href="<?= env('STYLE_URL'); ?>css/pages/login/classic/login-3.css" rel="stylesheet" type="text/css" />
        <link href="<?= env('STYLE_URL'); ?>plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
        <link href="<?= env('STYLE_URL'); ?>plugins/custom/prismjs/prismjs.bundle.css" rel="stylesheet" type="text/css" />
        <link href="<?= env('STYLE_URL'); ?>css/pages/login/classic/style.bundle.css" rel="stylesheet" type="text/css" />
        <link href="<?= env('STYLE_URL'); ?>css/themes/layout/brand/dark.css" rel="stylesheet" type="text/css" />
        <link href="<?= env('STYLE_URL'); ?>css/themes/layout/aside/dark.css" rel="stylesheet" type="text/css" />
        <link href="<?= env('STYLE_URL'); ?>css/themes/layout/header/base/light.css" rel="stylesheet" type="text/css" />
        <link href="<?= env('STYLE_URL'); ?>css/themes/layout/header/menu/light.css" rel="stylesheet" type="text/css" />
    <?php } else { ?>

        <?php if($darkmode) { ?>

            <link href="<?= env('STYLE_URL'); ?>plugins/global/plugins.dark.bundle.css" rel="stylesheet" type="text/css" />
            <link href="<?= env('STYLE_URL'); ?>css/style.dark.bundle.css" rel="stylesheet" type="text/css" />
            <link href="<?= env('STYLE_URL'); ?>plugins/custom/datatables/datatables.dark.bundle.css" rel="stylesheet" type="text/css" />

        <?php } else { ?>

            <link href="<?= env('STYLE_URL'); ?>plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
            <link href="<?= env('STYLE_URL'); ?>css/style.bundle.css" rel="stylesheet" type="text/css" />
            <link href="<?= env('STYLE_URL'); ?>plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />


        <?php } ?>

    <?php } ?>

    <link href="<?= env('STYLE_URL'); ?>plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <script src="<?= env('STYLE_URL'); ?>plugins/global/plugins.bundle.js"></script>
    <script src="<?= env('STYLE_URL'); ?>plugins/custom/datatables/datatables.bundle.js"></script>
    <link href="<?= env('STYLE_URL'); ?>css/paymentfont.css" rel="stylesheet" type="text/css"/>

    <!-- recaptcha -->
    <script src="https://hcaptcha.com/1/api.js" async defer></script>

    <!-- Fontawesome Stuff -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.1/css/all.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.1/js/all.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome-animation/0.3.0/font-awesome-animation.min.css" integrity="sha512-Po8rrCwchD03Wo+2ibHFielZ8luDAVoCyE9i6iFMPyn9+V1tIhGk5wl8iKC9/JfDah5Oe9nV8QzE8HHgjgzp3g==" crossorigin="anonymous" />

    <!-- Alert Stuff (Toastr & SweetAlert) --
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" integrity="sha256-ENFZrbVzylNbgnXx0n3I1g//2WeO47XxoPe0vkp3NC8=" crossorigin="anonymous" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js" integrity="sha256-3blsJd4Hli/7wCQ+bmgXfOdK7p/ZUMtPXY08jmxSSgk=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@8"></script-->

    <!-- Rotating Icon -->
    <style>
        .icon-rotate:hover {
            animation: fas-spin 2s infinite linear;
        }
        @-webkit-keyframes fas-spin{0%{-webkit-transform:rotate(0deg);transform:rotate(0deg)}100%{-webkit-transform:rotate(359deg);transform:rotate(359deg)}}
        @keyframes fas-spin{0%{-webkit-transform:rotate(0deg);transform:rotate(0deg)}100%{-webkit-transform:rotate(359deg);transform:rotate(359deg)}}
    </style>

    <!-- Cookie popup -->

    <link rel="stylesheet" type="text/css" href="//wpcc.io/lib/1.0.2/cookieconsent.min.css"/><script src="//wpcc.io/lib/1.0.2/cookieconsent.min.js"></script>
    <script>window.addEventListener("load", function() {
            window.wpcc.init( {
                    "border":"thin", "corners":"small", "colors": {
                        "popup": {
                            "background": "#cff5ff", "text": "#000000", "border": "#5e99c2"
                        }
                        , "button": {
                            "background": "#5e99c2", "text": "#fff"
                        }
                    }
                    , "position":"bottom-left", "content": {
                        "href": "<?= env('LEGAL_URL'); ?>privacy/", "link": "Weiter Informationen", "button": "Verstanden!", "message": "Diese Website verwendet Cookies, um sicherzustellen, dass Sie alle Funktionalitäten der Webseite nutzen zu können."
                    }
                }
            )
        });
    </script>
    <!-- Cookie popup END -->

</head>

<?php if(strpos($currPage, '_auth')) { ?>
    <body id="kt_body" class="header-fixed header-mobile-fixed subheader-enabled subheader-fixed aside-enabled aside-fixed aside-minimize-hoverable">
<?php } else { ?>
    <body id="kt_body" class="header-tablet-and-mobile-fixed aside-enabled<?php if($darkmode) { echo ' dark-mode'; } ?>">
<?php } ?>