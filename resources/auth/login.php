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
 *  * @date        16.8.2022
 *  * @time        22:50
 *
 */

$currPage = 'front_Einloggen_auth';
include BASE_PATH . 'software/controller/PageController.php';

// include login auth file
if($user->getState($_POST['email']) == 'pending') {
    include BASE_PATH . 'software/managing/auth/resend.php';
} else {
    include BASE_PATH . 'software/managing/auth/login.php';
}

?>


<div class="d-flex flex-column flex-root">
    <div class="login login-3 login-signin-on d-flex flex-row-fluid" id="kt_login">
        <div class="d-flex flex-center bgi-size-cover bgi-no-repeat flex-row-fluid" style="background-image: url(<?= $helper->url(); ?>assets/images/bg/bg-1.jpg);">
            <div class="login-form text-center text-white p-7 position-relative overflow-hidden">

                <div class="d-flex flex-center mb-15">
                    <a href="#">
                        <img src="<?= $helper->getSetting('logo_white'); ?>" class="max-h-140px" alt="" />
                    </a>
                </div>

                <div class="login-signin">
                    <div class="mb-20">
                        <h3>Einloggen</h3>
                        <p class="opacity-60 font-weight-bold">Bitte logge Dich ein.</p>
                    </div>

                    <?php if($user->getState($_POST['email']) == 'pending') { ?>

                        <form class="form" method="post" id="kt_login_signin_form">
                            <div class="form-group">
                                <div class="alert alert-warning" role="alert">
                                    <h4 class="alert-heading">Dein Account ist noch nicht bestätigt!</h4>
                                    <div class="alert-body">
                                        Bitte bestätige deine E-Mail, solltest Du noch keine von uns erhalten haben. Kannst Du über den Button eine neue E-Mail verschicken lassen.

                                        <br><br>

                                        <input hidden="hidden" name="email" value="<?= $_POST['email']; ?>">
                                        <button class="btn btn-outline-primary" type="submit" name="sendRegister">Erneut senden</button>
                                    </div>
                                </div>
                            </div>
                        </form>

                    <?php } elseif($user->getState($_POST['email']) == 'banned') { ?>

                        <div class="form-group">
                            <div class="alert alert-danger" role="alert">
                                <h4 class="alert-heading">Dein Account wurde gesperrt!</h4>
                                <div class="alert-body">
                                    Du wurdest aus unserem Interface ausgeschlossen.<br><br>

                                    <b>Begründung:</b><br>
                                    <?= $helper->xssFix($user->getReason($_POST['email'])); ?>
                                    <br><br>
                                    Bitte kontaktiere unseren Support für einen Klärungsversuch.
                                </div>
                            </div>
                        </div>

                    <?php } else { ?>

                        <form method="post">
                            <div class="form-group">
                                <input class="form-control h-auto text-white placeholder-white opacity-70 bg-dark-o-70 rounded-pill border-0 py-4 px-8 mb-5" type="text" placeholder="Benutzername / E-Mail" name="email">
                            </div>
                            <div class="form-group">
                                <input class="form-control h-auto text-white placeholder-white opacity-70 bg-dark-o-70 rounded-pill border-0 py-4 px-8 mb-5" type="password" placeholder="Dein Passwort" name="password" autocomplete="off">
                            </div>

                            <?php if(isset($_COOKIE['7apwy35m2budptd7'])){ ?>
                                <div class="form-group">
                                    <div class="h-captcha" data-sitekey="<?= env('H_CAPTCHA_SITE_KEY'); ?>"></div>
                                </div>
                            <?php } ?>

                            <div class="form-group d-flex flex-wrap justify-content-between align-items-center px-8">
                                <div class="checkbox-inline">
                                    <label class="checkbox checkbox-outline checkbox-white text-white m-0">
                                        <input type="checkbox" name="stayLogged">
                                        <span></span>Eingeloggt bleiben</label>
                                </div>
                                <a href="<?= env('URL'); ?>auth/forgot-password/" id="kt_login_forgot" class="text-white font-weight-bold">Passwort vergessen?</a>
                            </div>
                            <div class="form-group text-center mt-10">
                                <button type="submit" name="login_submit" class="btn btn-pill btn-outline-white font-weight-bold opacity-90 px-15 py-3">Einloggen</button>
                            </div>
                        </form>

                    <?php } ?>

                    <div class="mt-10">
                        <span class="opacity-70 mr-4">Du hast noch kein Kundenkonto?</span>
                        <a href="<?= $helper->url(); ?>auth/register/" id="kt_login_signup" class="text-white font-weight-bold">Kundenkonto anlegen</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!--end::Javascript-->
</body>
<!--end::Body-->
</html>