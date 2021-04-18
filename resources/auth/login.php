<?php
$currPage = 'front_Login_auth';
include BASE_PATH.'app/controller/PageController.php';
include BASE_PATH.'app/manager/customer/auth/login.php';
?>
<div class="d-flex flex-column flex-root">

<div class="login login-4 wizard d-flex flex-column flex-lg-row flex-column-fluid">

    <div class="login-container order-2 order-lg-1 d-flex flex-center flex-row-fluid px-7 pt-lg-0 pb-lg-0 pt-4 pb-6 bg-white">

        <div class="login-content d-flex flex-column pt-lg-0 pt-12">

            <a href="#" class="login-logo pb-xl-20 pb-15">
                <img src="https://i.imgur.com/oqA3CbU.png" width="300" alt="" />
            </a>

            <div class="login-form" style="width: 400px;">

                <form class="form" id="kt_login_singin_form" method="post">
                    <div class="pb-5 pb-lg-15">
                        <h3 class="font-weight-bolder text-dark font-size-h2 font-size-h1-lg">Logge dich ein</h3>
                        <div class="text-muted font-weight-bold font-size-h4">
							Neu hier?
                            <a href="<?= env('URL'); ?>register" class="text-primary font-weight-bolder">Account erstellen</a><br>
							oder Lieber die
                            <a href="https://cp.red-host.eu/order/webspace" class="text-primary font-weight-bolder">Produkte ansehen</a>
						</div>
					</div>

                    <div class="form-group">
                        <label class="font-size-h6 font-weight-bolder text-dark">E-Mail</label>
                        <input value="<?= $_POST['email']; ?>" class="form-control form-control-solid h-auto py-7 px-6 rounded-lg border-0" type="email" name="email" autocomplete="off" />
                    </div>

                    <div class="form-group">
                        <div class="d-flex justify-content-between mt-n5">
                            <label class="font-size-h6 font-weight-bolder text-dark pt-5">Passwort</label>
                            <a href="<?= env('URL'); ?>passwort_reset" class="text-primary font-size-h6 font-weight-bolder text-hover-primary pt-5">Passwort vergessen?</a>
                        </div>
                        <input class="form-control form-control-solid h-auto py-7 px-6 rounded-lg border-0" type="password" name="password" autocomplete="off" />
                    </div>

                    <label for="stayLogged" class="checkbox noselect">
                        <input type="checkbox" name="stayLogged" id="stayLogged">
                        <span></span>
                        Für 10 Tage eingeloggt bleiben
                    </label>

                    <br>
                    <br>

                    <?php if(isset($_COOKIE['7apwy35m2budptd7'])){ ?>
                        <div class="form-group">
                            <div class="h-captcha" data-sitekey="<?= env('H_CAPTCHA_SITE_KEY'); ?>"></div>
                        </div>
                    <?php } ?>

                    <div class="pb-lg-0 pb-5">
                        <button type="submit" id="kt_login_singin_form_submit_button" class="btn btn-outline-primary font-weight-bolder font-size-h6 px-8 py-4 my-3 mr-3" name="login">Einloggen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!--<div class="login-aside order-1 order-lg-2 bgi-no-repeat bgi-position-x-right">
        <div class="login-conteiner bgi-no-repeat bgi-position-x-right bgi-position-y-bottom" style="background-image: url('https://i.imgur.com/vf9iR2U.png');">
            <h3 class="pt-lg-40 pl-lg-20 pb-lg-0 pl-10 py-20 m-0 d-flex justify-content-lg-start font-weight-boldest display5 display1-lg text-white"><font color="#dedede">Server
            <br />Lösungen
            <br />für Dich</font></h3>
        </div>
    </div>--->
</div>