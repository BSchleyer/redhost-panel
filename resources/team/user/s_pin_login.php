<?php
$currPage = 'team_Support Pin Login';
include BASE_PATH.'app/controller/PageController.php';

if(isset($_POST['login'])){
    $error = null;

    if(empty($_POST['spin'])){
        $error = 'Bitte gebe einen Pin ein';
    }

    $validateSpin = $user->validateSpin($_POST['spin']);
    if($validateSpin == 0){
        $error = 'Der Pin ist ungültig';
    }

    if($user->getDataById($validateSpin,'role') == 'admin' || $user->getDataById($validateSpin,'role') == 'support'){
        $error = 'Du kannst dich nicht in diesen Account einloggen';
    }

    if(empty($error)){

        //$discord->callWebhook('Soeben hat sich '.$username.' mit einem Support Pin in den Account von '.$user->getDataById($validateSpin,'username').' eingeloggt');

        $uspin = $user->renewSupportPin($validateSpin);
        $uspin = str_replace('=','',base64_encode($uspin));

        header('Location: '.env('URL').'team/user/'.$uspin);
    } else {
        echo sendError($error);
    }
}
?>
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

            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="card-header-title">
                                Support Pin Login
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post">

                        <label>Support-Pin</label>
                        <input class="form-control" name="spin">
                        <br>
                        <button type="submit" name="login" class="btn btn-primary btn-block">Einloggen</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>