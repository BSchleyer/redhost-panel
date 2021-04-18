<?php
$currPage = 'front_Passwort vergessen_auth';
include BASE_PATH.'app/controller/PageController.php';
include BASE_PATH.'app/manager/customer/auth/forgot_password.php';
?>
<div class="d-flex flex-column flex-root">

    <div class="login login-4 wizard d-flex flex-column flex-lg-row flex-column-fluid">

        <div class="login-container order-2 order-lg-1 d-flex flex-center flex-row-fluid px-7 pt-lg-0 pb-lg-0 pt-4 pb-6 bg-white">

            <div class="login-content d-flex flex-column pt-lg-0 pt-12">

                <a href="<?= env('URL'); ?>" class="login-logo pb-xl-20 pb-15">
                    <img src="https://i.imgur.com/oqA3CbU.png" width="400" alt="" />
                </a>

                <div class="login-form" style="width: 400px;">

                    <?php if(isset($_GET['key']) && !empty($_GET['key'])){ $key = $_GET['key']; ?>
                        <form class="form" id="kt_login_singin_form" method="post">
                            <div class="pb-5 pb-lg-15">
                                <h3 class="font-weight-bolder text-dark font-size-h2 font-size-h1-lg"><?= $currPageName; ?></h3>
                            </div>
                            <input name="key" hidden="hidden" value="<?= $_GET['key']; ?>">
                            <div class="form-group">
                                <label class="font-size-h6 font-weight-bolder text-dark">Passwort</label>
                                <input class="form-control form-control-solid h-auto py-7 px-6 rounded-lg border-0" type="text" name="new_password" autocomplete="off" />
                            </div>

                            <div class="form-group">
                                <label class="font-size-h6 font-weight-bolder text-dark">Passwort wiederholen</label>
                                <input class="form-control form-control-solid h-auto py-7 px-6 rounded-lg border-0" type="text" name="new_password_repeat" autocomplete="off" />
                            </div>

                            <div class="form-group">
                                <div class="h-captcha" data-sitekey="<?= env('H_CAPTCHA_SITE_KEY'); ?>"></div>
                            </div>

                            <div class="pb-lg-0 pb-5">
                                <button type="submit" id="kt_login_singin_form_submit_button" class="btn btn-outline-primary font-weight-bolder font-size-h6 px-8 py-4 my-3 mr-3" name="resetPW">Passwort ändern</button>
                            </div>
                        </form>
                    <?php } else { ?>
                        <form class="form" id="kt_login_singin_form" method="post">
                            <div class="pb-5 pb-lg-15">
                                <h3 class="font-weight-bolder text-dark font-size-h2 font-size-h1-lg"><?= $currPageName; ?></h3>
                            </div>

                            <div class="form-group">
                                <label class="font-size-h6 font-weight-bolder text-dark">E-Mail / Benutzername</label>
                                <input class="form-control form-control-solid h-auto py-7 px-6 rounded-lg border-0" type="text" name="user_info" autocomplete="off" />
                            </div>

                            <div class="form-group">
                                <div class="h-captcha" data-sitekey="<?= env('H_CAPTCHA_SITE_KEY'); ?>"></div>
                            </div>

                            <div class="pb-lg-0 pb-5">
                                <button type="submit" id="kt_login_singin_form_submit_button" class="btn btn-outline-primary font-weight-bolder font-size-h6 px-8 py-4 my-3 mr-3" name="requestReset">Reset anfordern</button>
                            </div>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!--div class="login-aside order-1 order-lg-2 bgi-no-repeat bgi-position-x-right">
			<div class="login-conteiner bgi-no-repeat bgi-position-x-right bgi-position-y-bottom" style="background-image: url('https://i.imgur.com/vf9iR2U.png');">
				<h3 class="pt-lg-40 pl-lg-20 pb-lg-0 pl-10 py-20 m-0 d-flex justify-content-lg-start font-weight-boldest display5 display1-lg text-white"><font color="#dedede">Server
				<br />Lösungen
				<br />für Dich</font></h3>
			</div>
		</div-->
    </div>
</div>