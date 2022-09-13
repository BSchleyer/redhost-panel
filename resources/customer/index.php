<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2022 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel
 *  * @file        index.php
 *  * @author      BjörnSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        16.8.2022
 *  * @time        22:50
 *
 */

$currPage = 'customer_Dashboard';
include BASE_PATH . 'software/controller/PageController.php';

// renew support pin
if(isset($_POST['renewPin'])) {
    $support_pin = $user->renewSupportPin($userid);

    header('Refresh: 0.15');
    echo sendSuccess('Neue Support-PIN generiert.');
}

// check if support pin null, if is true generate a new pin
if(is_null($user->getDataById($userid, 'support_pin')) OR empty($user->getDataById($userid, 'support_pin'))) {
    $user->renewSupportPin($userid);
}

?>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="post d-flex flex-column-fluid" id="kt_post">
        <div id="kt_content_container" class="container-xxl">
            <div class="container">
                <div class="row">
                    <h4 class="card-title">
                        <span id="welcome" class="fs-1 fw-bolder">
                            <!-- javascript for text loading -->
                        </span>
                    </h4>

                    <p class="card-title-description">
                        <?= env('DASHBOARD_DESCRIPTION'); ?>
                    </p>

                    <div class="col-xl-3">
                        <!--begin::Statistics Widget 5-->
                        <a href="javascript:;" class="card bg-primary hoverable card-xl-stretch mb-xl-8">
                            <!--begin::Body-->
                            <div class="card-body">
                                <!--begin::Svg Icon | path: assets/media/icons/duotune/finance/fin008.svg-->
                                <span class="svg-icon svg-icon-white svg-icon-3x ms-n1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path opacity="0.3" d="M3.20001 5.91897L16.9 3.01895C17.4 2.91895 18 3.219 18.1 3.819L19.2 9.01895L3.20001 5.91897Z" fill="black"/>
                                            <path opacity="0.3" d="M13 13.9189C13 12.2189 14.3 10.9189 16 10.9189H21C21.6 10.9189 22 11.3189 22 11.9189V15.9189C22 16.5189 21.6 16.9189 21 16.9189H16C14.3 16.9189 13 15.6189 13 13.9189ZM16 12.4189C15.2 12.4189 14.5 13.1189 14.5 13.9189C14.5 14.7189 15.2 15.4189 16 15.4189C16.8 15.4189 17.5 14.7189 17.5 13.9189C17.5 13.1189 16.8 12.4189 16 12.4189Z" fill="black"/>
                                            <path d="M13 13.9189C13 12.2189 14.3 10.9189 16 10.9189H21V7.91895C21 6.81895 20.1 5.91895 19 5.91895H3C2.4 5.91895 2 6.31895 2 6.91895V20.9189C2 21.5189 2.4 21.9189 3 21.9189H19C20.1 21.9189 21 21.0189 21 19.9189V16.9189H16C14.3 16.9189 13 15.6189 13 13.9189Z" fill="black"/>
                                        </svg>
                                    </span>
                                <!--end::Svg Icon-->
                                <div class="text-white fw-bolder fs-2 mb-2 mt-5">
                                    <?php
                                    if($user->getDataById($userid, 'amount') < 0.00) {
                                        echo '<span style="color: red;">' . $amount . '€</span>';
                                    } else {
                                        echo $amount . '€';
                                    }
                                    ?>
                                </div>
                                <div class="fw-bold text-white">Dein Guthaben</div>
                            </div>
                            <!--end::Body-->
                        </a>
                        <!--end::Statistics Widget 5-->
                    </div>

                    <div class="col-xl-3">
                        <!--begin::Statistics Widget 5-->
                        <a href="javascript:;" class="card bg-primary hoverable card-xl-stretch mb-xl-8">
                            <!--begin::Body-->
                            <div class="card-body">
                                <!--begin::Svg Icon | path: assets/media/icons/duotune/communication/com010.svg-->
                                <span class="svg-icon svg-icon-white svg-icon-3x ms-n1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M6 8.725C6 8.125 6.4 7.725 7 7.725H14L18 11.725V12.925L22 9.725L12.6 2.225C12.2 1.925 11.7 1.925 11.4 2.225L2 9.725L6 12.925V8.725Z" fill="black"/>
                                    <path opacity="0.3" d="M22 9.72498V20.725C22 21.325 21.6 21.725 21 21.725H3C2.4 21.725 2 21.325 2 20.725V9.72498L11.4 17.225C11.8 17.525 12.3 17.525 12.6 17.225L22 9.72498ZM15 11.725H18L14 7.72498V10.725C14 11.325 14.4 11.725 15 11.725Z" fill="black"/>
                                    </svg></span>
                                <!--end::Svg Icon-->
                                <!--end::Svg Icon-->
                                <div class="text-white fw-bolder fs-2 mb-2 mt-5">
                                    <?= $user->getOpenTickets($userid); ?>
                                </div>
                                <div class="fw-bold text-white">Offene Tickets</div>
                            </div>
                            <!--end::Body-->
                        </a>
                        <!--end::Statistics Widget 5-->
                    </div>

                    <div class="col-xl-3">
                        <!--begin::Statistics Widget 5-->
                        <a href="javascript:;" class="card bg-primary hoverable card-xl-stretch mb-xl-8">
                            <!--begin::Body-->
                            <div class="card-body">
                                <!--begin::Svg Icon | path: assets/media/icons/duotune/finance/fin006.svg-->
                                <span class="svg-icon svg-icon-white svg-icon-3x ms-n1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path opacity="0.3" d="M20 15H4C2.9 15 2 14.1 2 13V7C2 6.4 2.4 6 3 6H21C21.6 6 22 6.4 22 7V13C22 14.1 21.1 15 20 15ZM13 12H11C10.5 12 10 12.4 10 13V16C10 16.5 10.4 17 11 17H13C13.6 17 14 16.6 14 16V13C14 12.4 13.6 12 13 12Z" fill="black"/>
                                    <path d="M14 6V5H10V6H8V5C8 3.9 8.9 3 10 3H14C15.1 3 16 3.9 16 5V6H14ZM20 15H14V16C14 16.6 13.5 17 13 17H11C10.5 17 10 16.6 10 16V15H4C3.6 15 3.3 14.9 3 14.7V18C3 19.1 3.9 20 5 20H19C20.1 20 21 19.1 21 18V14.7C20.7 14.9 20.4 15 20 15Z" fill="black"/>
                                    </svg></span>
                                <!--end::Svg Icon-->
                                <!--end::Svg Icon-->
                                <!--end::Svg Icon-->
                                <div class="text-white fw-bolder fs-2 mb-2 mt-5">
                                    <?= $user->getMonthlyCosts($userid); ?>€
                                </div>
                                <div class="fw-bold text-white">Monatliche Kosten</div>
                            </div>
                            <!--end::Body-->
                        </a>
                        <!--end::Statistics Widget 5-->
                    </div>

                    <div class="col-xl-3">
                        <!--begin::Statistics Widget 5-->
                        <a href="javascript:;" class="card bg-primary hoverable card-xl-stretch mb-xl-8">
                            <!--begin::Body-->
                            <div class="card-body">

                                    <span class="svg-icon svg-icon-white svg-icon-3x ms-n1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path opacity="0.3" d="M3 6C2.4 6 2 5.6 2 5V3C2 2.4 2.4 2 3 2H5C5.6 2 6 2.4 6 3C6 3.6 5.6 4 5 4H4V5C4 5.6 3.6 6 3 6ZM22 5V3C22 2.4 21.6 2 21 2H19C18.4 2 18 2.4 18 3C18 3.6 18.4 4 19 4H20V5C20 5.6 20.4 6 21 6C21.6 6 22 5.6 22 5ZM6 21C6 20.4 5.6 20 5 20H4V19C4 18.4 3.6 18 3 18C2.4 18 2 18.4 2 19V21C2 21.6 2.4 22 3 22H5C5.6 22 6 21.6 6 21ZM22 21V19C22 18.4 21.6 18 21 18C20.4 18 20 18.4 20 19V20H19C18.4 20 18 20.4 18 21C18 21.6 18.4 22 19 22H21C21.6 22 22 21.6 22 21ZM16 11V9C16 6.8 14.2 5 12 5C9.8 5 8 6.8 8 9V11C7.2 11 6.5 11.7 6.5 12.5C6.5 13.3 7.2 14 8 14V15C8 17.2 9.8 19 12 19C14.2 19 16 17.2 16 15V14C16.8 14 17.5 13.3 17.5 12.5C17.5 11.7 16.8 11 16 11ZM13.4 15C13.7 15 14 15.3 13.9 15.6C13.6 16.4 12.9 17 12 17C11.1 17 10.4 16.5 10.1 15.7C10 15.4 10.2 15 10.6 15H13.4Z" fill="black"/>
                                    <path d="M9.2 12.9C9.1 12.8 9.10001 12.7 9.10001 12.6C9.00001 12.2 9.3 11.7 9.7 11.6C10.1 11.5 10.6 11.8 10.7 12.2C10.7 12.3 10.7 12.4 10.7 12.5L9.2 12.9ZM14.8 12.9C14.9 12.8 14.9 12.7 14.9 12.6C15 12.2 14.7 11.7 14.3 11.6C13.9 11.5 13.4 11.8 13.3 12.2C13.3 12.3 13.3 12.4 13.3 12.5L14.8 12.9ZM16 7.29998C16.3 6.99998 16.5 6.69998 16.7 6.29998C16.3 6.29998 15.8 6.30001 15.4 6.20001C15 6.10001 14.7 5.90001 14.4 5.70001C13.8 5.20001 13 5.00002 12.2 4.90002C9.9 4.80002 8.10001 6.79997 8.10001 9.09997V11.4C8.90001 10.7 9.40001 9.8 9.60001 9C11 9.1 13.4 8.69998 14.5 8.29998C14.7 9.39998 15.3 10.5 16.1 11.4V9C16.1 8.5 16 8 15.8 7.5C15.8 7.5 15.9 7.39998 16 7.29998Z" fill="black"/>
                                    </svg></span>

                                <div class="text-white fw-bolder fs-2 mb-2 mt-5">
                                    <?= $support_pin; ?>
                                    <i style="cursor: pointer;" class="fas fa-copy copy-btn" data-clipboard-text="<?= $support_pin; ?>" data-toggle="tooltip" title="Support-PIN kopieren"></i>
                                    <i style="cursor: pointer;" onclick="renew();" class="fas fa-sync-alt icon-rotate" data-clipboard-text="<?= $support_pin; ?>" data-toggle="tooltip" title="Neuen Support-PIN generieren"></i>

                                    <form method="post" id="renewPin">
                                        <input hidden name="renewPin">
                                    </form>

                                    <script type="text/javascript">
                                        function renew() {
                                            document.getElementById('renewPin').submit();
                                        }
                                    </script>
                                </div>
                                <div class="fw-bold text-white">Deine Support-PIN</div>

                            </div>
                            <!--end::Body-->
                        </a>
                        <!--end::Statistics Widget 5-->
                    </div>
                </div>


                <?php
                $SQL = $db->prepare("SELECT * FROM `news` WHERE `deleted_at` IS NULL");
                $SQL->execute();
                if($SQL->rowCount() != 0) {
                    $row = $SQL->fetch(PDO::FETCH_ASSOC);

                    ?>

                    <div class="row">
                        <br>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-5 mb-xl-8">
                                <!--begin::Body-->
                                <div class="card-body pb-0">
                                    <!--begin::Header-->
                                    <div class="d-flex align-items-center mb-5">
                                        <!--begin::User-->
                                        <div class="d-flex align-items-center flex-grow-1">
                                            <!--begin::Avatar-->
                                            <div class="symbol symbol-45px me-5">
                                                <img src="<?= $helper->imageUrl(); ?>logos/profile.jpg" alt="" />
                                            </div>
                                            <!--end::Avatar-->

                                            <?php
                                            $session_token = $user->getDataById($row['user_id'],'session_token');
                                            if($user->isInTeam($session_token) == true) {
                                                ?>
                                                <!--begin::Info-->
                                                <div class="d-flex flex-column">
                                                    <a href="#" class="text-gray-900 text-hover-primary fs-6 fw-bolder"><?= $user->getDataById($row['user_id'], 'firstname'); ?> <?= $user->getDataById($row['user_id'], 'lastname'); ?></a>
                                                    <span class="text-gray-400 fw-bold"><?= $user->getRoleById($row['user_id']); ?></span>
                                                </div>
                                                <!--end::Info-->
                                            <?php } else { ?>
                                                <!--begin::Info-->
                                                <div class="d-flex flex-column">
                                                    <a href="#" class="text-gray-900 text-hover-primary fs-6 fw-bolder"><?= env('APP_NAME'); ?></a>
                                                    <span class="text-gray-400 fw-bold">Posted by Schleyer-EDV Internal-API</span>
                                                </div>
                                                <!--end::Info-->
                                            <?php } ?>
                                        </div>
                                        <!--end::User-->
                                    </div>
                                    <!--end::Header-->
                                    <!--begin::Post-->
                                    <div class="mb-5">

                                        <strong><?= $row['title']; ?></strong>
                                        <!--begin::Text-->
                                        <p class="text-gray-800 fw-normal mb-5">
                                            <?= $helper->nl2br2($row['text']); ?>
                                        </p>
                                        <!--end::Text-->
                                    </div>
                                    <!--end::Post-->
                                </div>
                                <!--end::Body-->
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script src="<?= $helper->styleUrl(); ?>plugins/custom/typedjs/typedjs.bundle.js"></script>
<script type="text/javascript">
    <?php
        if(is_null($user->getDataById($userid, 'firstname')) || is_null($user->getDataById($userid, 'lastname'))) {
            $name = $username;
        } else {
            $name = $user->getDataById($userid, 'firstname') . ' ' . $user->getDataById($userid, 'lastname');
        }
    ?>

    var typed = new Typed("#welcome", {
        strings: ["<?= $site->getWelcomeText(date('H')); ?>, <?= $name; ?>.", "Herzlich Willkommen in unserem Kunden-Portal!", "Du hast Fragen? Dann frag uns einfach. ;)"],
        startDelay: 5,
        typeSpeed: 45,
        backSpeed: 15,
        smartBackspace: true,
        loop: true,
        loopCount: Infinity,
        showCursor: true,
        cursorChar: '|',
        autoInsertCss: true
    });
</script>