<div class="footer bg-white py-4 d-flex flex-lg-column" id="kt_footer">
    <div class="container-fluid d-flex flex-column flex-md-row align-items-center justify-content-between">
        <div class="text-dark order-2 order-md-1">
			<i class="fas fa-cog fa-spin"></i> <?= env('VERSION_DATE'); ?>
			• <font style="font-size: 110%;">2021©
            <a href="" class="text-dark-75 text-hover-primary">REDHost</a></font>
        </div>
        <div class="nav nav-dark">
            <a target="_blank" href="https://cp.red-host.eu/agb" class="nav-link pl-0 pr-5">AGB</a>
			<a target="_blank" href="https://cp.red-host.eu/datenschutz" class="nav-link pl-0 pr-5">Datenschutz</a>
            <a target="_blank" href="https://cp.red-host.eu/impressum" class="nav-link pl-0 pr-0">Impressum</a>
        </div>

        Support bis zum 03.01. eingeschränkt! Mehr dazu im Dashboard. Wir wünschen einen Guten Rutsch ins Jahr 2021

        <!-- TrustBox widget - Micro Review Count --><!--
        <?php if($user->getDataById($userid,'darkmode')){ ?>

            <div class="trustpilot-widget" data-locale="de-DE" data-template-id="5419b6a8b0d04a076446a9ad" data-businessunit-id="5ebbe8488b381c000123ca58" data-style-height="24px" data-style-width="110%" data-theme="dark">
                <a href="https://de.trustpilot.com/review/red-host.eu" target="_blank" rel="noopener" class="nav-link pl-0 pr-0">Trustpilot</a>
            </div>

        <?php } else { ?>
            
            <div class="trustpilot-widget" data-locale="de-DE" data-template-id="5419b6a8b0d04a076446a9ad" data-businessunit-id="5ebbe8488b381c000123ca58" data-style-height="24px" data-style-width="115%" data-theme="light">
                <a href="https://de.trustpilot.com/review/red-host.eu" target="_blank" rel="noopener" class="nav-link pl-0 pr-0">Trustpilot</a>
            </div>

        <link href="<?= $helper->cdnUrl(); ?>assets/css/themes/layout/aside/dark.css" rel="stylesheet" type="text/css" />
	    <?php } ?>
        -->
        <!-- End TrustBox widget -->

    </div>
</div>
</div>
</div>
</div>

<div id="kt_scrolltop" class="scrolltop">
    <span class="svg-icon">
        <!--begin::Svg Icon | path:assets/media/svg/icons/Navigation/Up-2.svg-->
        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                <polygon points="0 0 24 0 24 24 0 24" />
                <rect fill="#000000" opacity="0.3" x="11" y="10" width="2" height="10" rx="1" />
                <path d="M6.70710678,12.7071068 C6.31658249,13.0976311 5.68341751,13.0976311 5.29289322,12.7071068 C4.90236893,12.3165825 4.90236893,11.6834175 5.29289322,11.2928932 L11.2928932,5.29289322 C11.6714722,4.91431428 12.2810586,4.90106866 12.6757246,5.26284586 L18.6757246,10.7628459 C19.0828436,11.1360383 19.1103465,11.7686056 18.7371541,12.1757246 C18.3639617,12.5828436 17.7313944,12.6103465 17.3242754,12.2371541 L12.0300757,7.38413782 L6.70710678,12.7071068 Z" fill="#000000" fill-rule="nonzero" />
            </g>
        </svg>
        <!--end::Svg Icon-->
    </span>
</div>

<div id="kt_quick_user" class="offcanvas offcanvas-right p-10">
    <div class="offcanvas-header d-flex align-items-center justify-content-between pb-5">
        <h3 class="font-weight-bold m-0">Mein Profil</h3>
        <a href="#" class="btn btn-xs btn-icon btn-light btn-hover-primary" id="kt_quick_user_close">
            <i class="ki ki-close icon-xs text-muted"></i>
        </a>
    </div>
    <div class="offcanvas-content pr-5 mr-n5">
        <div class="d-flex align-items-center mt-5">
            <div class="symbol symbol-100 mr-5">
                <div class="symbol-label" <?php if($datasavingmode == 0){ ?>style="background-image:url('https://api.cookiemc.de/200/<?= $username; ?>.png?ssl=1')"<?php } ?>></div>
                <i class="symbol-badge bg-success"></i>
            </div>
            <div class="d-flex flex-column">
                <a href="#" class="font-weight-bold font-size-h5 text-hover-primary"><?= $username; ?></a>
                <div class=""><b><?= $user->getRole($username); ?></b></div>
                <div class="navi mt-2">
                    <a href="#" class="navi-item">
                        <span class="navi-link p-0 pb-2">
                            <span class="navi-icon mr-1">
                                <span class="svg-icon svg-icon-lg svg-icon-primary">
                                    <!--begin::Svg Icon | path:assets/media/svg/icons/Communication/Mail-notification.svg-->
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <path d="M21,12.0829584 C20.6747915,12.0283988 20.3407122,12 20,12 C16.6862915,12 14,14.6862915 14,18 C14,18.3407122 14.0283988,18.6747915 14.0829584,19 L5,19 C3.8954305,19 3,18.1045695 3,17 L3,8 C3,6.8954305 3.8954305,6 5,6 L19,6 C20.1045695,6 21,6.8954305 21,8 L21,12.0829584 Z M18.1444251,7.83964668 L12,11.1481833 L5.85557487,7.83964668 C5.4908718,7.6432681 5.03602525,7.77972206 4.83964668,8.14442513 C4.6432681,8.5091282 4.77972206,8.96397475 5.14442513,9.16035332 L11.6444251,12.6603533 C11.8664074,12.7798822 12.1335926,12.7798822 12.3555749,12.6603533 L18.8555749,9.16035332 C19.2202779,8.96397475 19.3567319,8.5091282 19.1603533,8.14442513 C18.9639747,7.77972206 18.5091282,7.6432681 18.1444251,7.83964668 Z" fill="#000000" />
                                            <circle fill="#000000" opacity="0.3" cx="19.5" cy="17.5" r="2.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <span class="navi-text text-muted text-hover-primary"><?= $mail; ?></span>
                        </span>
                    </a>
                    <a href="<?= env('URL'); ?>logout" class="btn btn-sm btn-transparent-primary font-weight-bold mr-2"><b>Ausloggen</b></a>
                </div>
            </div>
        </div>
        <div class="separator separator-dashed mt-8 mb-5"></div>

        <form method="post">
            <div class="row noselect">

                <label class="col-9 col-form-label">
                    <span class="svg-icon svg-icon-primary svg-icon-2x">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <rect fill="#000000" opacity="0.3" x="2" y="3" width="20" height="18" rx="2"/>
                                <path d="M9.9486833,13.3162278 C9.81256925,13.7245699 9.43043041,14 9,14 L5,14 C4.44771525,14 4,13.5522847 4,13 C4,12.4477153 4.44771525,12 5,12 L8.27924078,12 L10.0513167,6.68377223 C10.367686,5.73466443 11.7274983,5.78688777 11.9701425,6.75746437 L13.8145063,14.1349195 L14.6055728,12.5527864 C14.7749648,12.2140024 15.1212279,12 15.5,12 L19,12 C19.5522847,12 20,12.4477153 20,13 C20,13.5522847 19.5522847,14 19,14 L16.118034,14 L14.3944272,17.4472136 C13.9792313,18.2776054 12.7550291,18.143222 12.5298575,17.2425356 L10.8627389,10.5740611 L9.9486833,13.3162278 Z" fill="#000000" fill-rule="nonzero"/>
                                <circle fill="#000000" opacity="0.3" cx="19" cy="6" r="1"/>
                            </g>
                        </svg><!--end::Svg Icon-->
                    </span>
                    Datensparmodus <i class="fas fa-question-circle" style="cursor: help" data-toggle="tooltip" data-placement="top" title="" aria-hidden="true" data-original-title="Der Datensparmodus bindet unnötiges Javascript &amp; Bilder aus, zudem werden alle API Anfragen reduziert"></i>
                </label>
                <div class="col-3">
                    <span class="checkbox-inline">
                        <label class="checkbox checkbox-lg">
                            <input type="checkbox" <?php if($datasavingmode){ echo 'checked="checked"'; } ?> name="datasavingmode"/>
                            <span></span>
                        </label>
                    </span>
                </div>

                <label class="col-9 col-form-label">
                    <span class="svg-icon svg-icon-primary svg-icon-2x">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <path d="M12.0700837,4.0003006 C11.3895108,5.17692613 11,6.54297551 11,8 C11,12.3948932 14.5439081,15.9620623 18.9299163,15.9996994 C17.5467214,18.3910707 14.9612535,20 12,20 C7.581722,20 4,16.418278 4,12 C4,7.581722 7.581722,4 12,4 C12.0233848,4 12.0467462,4.00010034 12.0700837,4.0003006 Z" fill="#000000"/>
                            </g>
                        </svg>
                    </span>
                    Darkmode
                </label>
                <div class="col-3">
                    <span class="checkbox-inline">
                        <label class="checkbox checkbox-lg">
                            <input type="checkbox" <?php if($user->getDataById($userid,'darkmode')){ echo 'checked="checked"'; } ?> name="darkmode"/>
                            <span></span>
                        </label>
                    </span>
                </div>

                <label class="col-9 col-form-label">
                    <span class="svg-icon svg-icon-primary svg-icon-2x">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <path d="M16,15.6315789 L16,12 C16,10.3431458 14.6568542,9 13,9 L6.16183229,9 L6.16183229,5.52631579 C6.16183229,4.13107011 7.29290239,3 8.68814808,3 L20.4776218,3 C21.8728674,3 23.0039375,4.13107011 23.0039375,5.52631579 L23.0039375,13.1052632 L23.0206157,17.786793 C23.0215995,18.0629336 22.7985408,18.2875874 22.5224001,18.2885711 C22.3891754,18.2890457 22.2612702,18.2363324 22.1670655,18.1421277 L19.6565168,15.6315789 L16,15.6315789 Z" fill="#000000"/>
                                <path d="M1.98505595,18 L1.98505595,13 C1.98505595,11.8954305 2.88048645,11 3.98505595,11 L11.9850559,11 C13.0896254,11 13.9850559,11.8954305 13.9850559,13 L13.9850559,18 C13.9850559,19.1045695 13.0896254,20 11.9850559,20 L4.10078614,20 L2.85693427,21.1905292 C2.65744295,21.3814685 2.34093638,21.3745358 2.14999706,21.1750444 C2.06092565,21.0819836 2.01120804,20.958136 2.01120804,20.8293182 L2.01120804,18.32426 C1.99400175,18.2187196 1.98505595,18.1104045 1.98505595,18 Z M6.5,14 C6.22385763,14 6,14.2238576 6,14.5 C6,14.7761424 6.22385763,15 6.5,15 L11.5,15 C11.7761424,15 12,14.7761424 12,14.5 C12,14.2238576 11.7761424,14 11.5,14 L6.5,14 Z M9.5,16 C9.22385763,16 9,16.2238576 9,16.5 C9,16.7761424 9.22385763,17 9.5,17 L11.5,17 C11.7761424,17 12,16.7761424 12,16.5 C12,16.2238576 11.7761424,16 11.5,16 L9.5,16 Z" fill="#000000" opacity="0.3"/>
                            </g>
                        </svg>
                    </span>
                    Livechat
                </label>
                <div class="col-3">
                    <span class="checkbox-inline">
                        <label class="checkbox checkbox-lg">
                            <input type="checkbox" <?php if($user->getDataById($userid,'livechat')){ echo 'checked="checked"'; } ?> name="livechat"/>
                            <span></span>
                        </label>
                    </span>
                </div>

                <label class="col-9 col-form-label">
                    <span class="svg-icon svg-icon-primary svg-icon-2x">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <path d="M12,21 C7.581722,21 4,17.418278 4,13 C4,8.581722 7.581722,5 12,5 C16.418278,5 20,8.581722 20,13 C20,17.418278 16.418278,21 12,21 Z" fill="#000000" opacity="0.3"/>
                                <path d="M13,5.06189375 C12.6724058,5.02104333 12.3386603,5 12,5 C11.6613397,5 11.3275942,5.02104333 11,5.06189375 L11,4 L10,4 C9.44771525,4 9,3.55228475 9,3 C9,2.44771525 9.44771525,2 10,2 L14,2 C14.5522847,2 15,2.44771525 15,3 C15,3.55228475 14.5522847,4 14,4 L13,4 L13,5.06189375 Z" fill="#000000"/>
                                <path d="M16.7099142,6.53272645 L17.5355339,5.70710678 C17.9260582,5.31658249 18.5592232,5.31658249 18.9497475,5.70710678 C19.3402718,6.09763107 19.3402718,6.73079605 18.9497475,7.12132034 L18.1671361,7.90393167 C17.7407802,7.38854954 17.251061,6.92750259 16.7099142,6.53272645 Z" fill="#000000"/>
                                <path d="M11.9630156,7.5 L12.0369844,7.5 C12.2982526,7.5 12.5154733,7.70115317 12.5355117,7.96165175 L12.9585886,13.4616518 C12.9797677,13.7369807 12.7737386,13.9773481 12.4984096,13.9985272 C12.4856504,13.9995087 12.4728582,14 12.4600614,14 L11.5399386,14 C11.2637963,14 11.0399386,13.7761424 11.0399386,13.5 C11.0399386,13.4872031 11.0404299,13.4744109 11.0414114,13.4616518 L11.4644883,7.96165175 C11.4845267,7.70115317 11.7017474,7.5 11.9630156,7.5 Z" fill="#000000"/>
                            </g>
                        </svg>
                    </span>
                    Preloader
                </label>
                <div class="col-3">
                    <span class="checkbox-inline">
                        <label class="checkbox checkbox-lg">
                            <input type="checkbox" <?php if($user->getDataById($userid,'preloader')){ echo 'checked="checked"'; } ?> name="preloader"/>
                            <span></span>
                        </label>
                    </span>
                </div>

                <div class="col-12">
                    <button type="submit" name="saveSettings" class="btn btn-transparent-primary btn-block btn-sm"><b>Speichern</b></button>
                </div>

            </div>
        </form>

        <div class="separator separator-dashed mt-5 mb-5"></div>

        <h5 class="mb-5">
            Informationen
        </h5>

        <?php
        $SQL = $db->prepare("SELECT * FROM `teamspeaks` WHERE `user_id` = :user_id AND `deleted_at` IS NULL ORDER BY `id` DESC");
        $SQL->execute(array(":user_id" => $userid));
        if ($SQL->rowCount() != 0) {
        while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){
        if($site->getDiffInDays($row['expire_at']) < 5){
        ?>
        <div class="d-flex align-items-center bg-light-warning rounded p-5 gutter-b">
            <div class="d-flex flex-column flex-grow-1 mr-2">
                <a href="<?= env('URL'); ?>manage/teamspeak/<?= $row['id']; ?>" class="font-weight-normal text-hover-primary font-size-lg mb-1"><b>Dein Teamspeak #<?= $row['id']; ?> läuft bald aus!</b></a>
                <span class="text-muted font-size-sm"><?= $helper->formatDate($row['expire_at']); ?></span>
            </div>
        </div>
        <?php } } } ?>

        <?php
        $SQL = $db->prepare("SELECT * FROM `webspace` WHERE `user_id` = :user_id AND `deleted_at` IS NULL ORDER BY `id` DESC");
        $SQL->execute(array(":user_id" => $userid));
        if ($SQL->rowCount() != 0) {
        while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){
        if($site->getDiffInDays($row['expire_at']) < 5){ ?>
            <div class="d-flex align-items-center bg-light-warning rounded p-5 gutter-b">
                <div class="d-flex flex-column flex-grow-1 mr-2">
                    <a href="<?= env('URL'); ?>manage/webspace/<?= $row['id']; ?>" class="font-weight-normal text-hover-primary font-size-lg mb-1"><b>Dein Webspace #<?= $row['id']; ?> läuft bald aus!</b></a>
                    <span class="text-muted font-size-sm"><?= $helper->formatDate($row['expire_at']); ?></span>
                </div>
            </div>
        <?php } } } ?>

        <?php
        $SQL = $db->prepare("SELECT * FROM `vm_servers` WHERE `user_id` = :user_id AND `deleted_at` IS NULL ORDER BY `id` DESC");
        $SQL->execute(array(":user_id" => $userid));
        if ($SQL->rowCount() != 0) {
        while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){
        if($site->getDiffInDays($row['expire_at']) < 5){ ?>
            <div class="d-flex align-items-center bg-light-warning rounded p-5 gutter-b">
                <div class="d-flex flex-column flex-grow-1 mr-2">
                    <a href="<?= env('URL'); ?>manage/vserver/<?= $row['id']; ?>" class="font-weight-normal text-hover-primary font-size-lg mb-1"><b>Dein vServer #<?= $row['id']; ?> läuft bald aus!</b></a>
                    <span class="text-muted font-size-sm"><?= $helper->formatDate($row['expire_at']); ?></span>
                </div>
            </div>
        <?php } } } ?>

        <?php
        $SQL = $db -> prepare("SELECT * FROM `tickets` WHERE `user_id` = :user_id AND `state` = 'OPEN' AND `last_msg` = 'SUPPORT'");
        $SQL->execute(array(":user_id" => $userid));
        if ($SQL->rowCount() != 0) {
        while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){
        ?>
        <div class="d-flex align-items-center bg-light-success rounded p-5 gutter-b">
            <div class="d-flex flex-column flex-grow-1 mr-2">
                <a style="color: black !important;" href="<?= env('URL'); ?>ticket/<?= $row['id']; ?>" class="font-weight-normal text-dark-75 text-hover-primary font-size-lg mb-1 black-text-custom"><b>Neue Antwort auf dein Ticket #<?= $row['id']; ?></b></a>
                <span class="text-muted font-size-sm"><?= $helper->formatDate($row['updated_at']); ?></span>
            </div>
        </div>
        <?php } } ?>

        <div class="separator separator-dashed mt-5 mb-5"></div>
    </div>
</div>

<script>var HOST_URL = "<?= env('URL'); ?>";</script>
<script>var KTAppSettings = { "breakpoints": { "sm": 576, "md": 768, "lg": 992, "xl": 1200, "xxl": 1200 }, "colors": { "theme": { "base": { "white": "#ffffff", "primary": "#3699FF", "secondary": "#E5EAEE", "success": "#1BC5BD", "info": "#8950FC", "warning": "#FFA800", "danger": "#F64E60", "light": "#F3F6F9", "dark": "#212121" }, "light": { "white": "#ffffff", "primary": "#E1F0FF", "secondary": "#ECF0F3", "success": "#C9F7F5", "info": "#EEE5FF", "warning": "#FFF4DE", "danger": "#FFE2E5", "light": "#F3F6F9", "dark": "#D6D6E0" }, "inverse": { "white": "#ffffff", "primary": "#ffffff", "secondary": "#212121", "success": "#ffffff", "info": "#ffffff", "warning": "#ffffff", "danger": "#ffffff", "light": "#464E5F", "dark": "#ffffff" } }, "gray": { "gray-100": "#F3F6F9", "gray-200": "#ECF0F3", "gray-300": "#E5EAEE", "gray-400": "#D6D6E0", "gray-500": "#B5B5C3", "gray-600": "#80808F", "gray-700": "#464E5F", "gray-800": "#1B283F", "gray-900": "#212121" } }, "font-family": "Poppins" };</script>

<script src="<?= $helper->cdnUrl(); ?>assets/plugins/custom/prismjs/prismjs.bundle.js?v=7.0.4"></script>
<script src="<?= $helper->cdnUrl(); ?>assets/js/scripts.bundle.js?v=7.0.4"></script>
<script src="<?= $helper->cdnUrl(); ?>assets/plugins/custom/fullcalendar/fullcalendar.bundle.js?v=7.0.4"></script>
<script src="<?= $helper->cdnUrl(); ?>assets/js/pages/widgets.js?v=7.0.4"></script>

<?php if($vmsoftware->getOpenInstalls($serverInfos['id'])) { ?>
<script>
    function blockpageload(){
        KTApp.block('#kt_blockui_card', {
            overlayColor: '#000000',
            state: 'primary',
            message: 'Bitte warten...'
        });

        setTimeout(function() {
            KTApp.unblock('#kt_blockui_card');
        }, 120000);
    }

    blockpageload();
</script>
<?php } ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.4/clipboard.min.js"></script>
<script>
    var clipboard = new ClipboardJS('.copy-btn');
    clipboard.on('success', function(e){
        toastr.options = { 
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false, 
            "onclick": null, 
            "showDuration": "300", 
            "hideDuration": "1000", 
            "timeOut": "5000", 
            "extendedTimeOut": "1000", 
            "showEasing": "swing", 
            "hideEasing": "linear", 
            "showMethod": "fadeIn", 
            "hideMethod": "fadeOut" 
        };
        toastr.success(
            "Wurde Kopiert",    //Message
            "Erfolgreich"       //Titel
        );
    });
</script>

<!-- Preloader -->
<script src="<?= $helper->cdnUrl(); ?>assets/js/app.js"></script>
<!-- Preloader END -->


<script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $('#dataTableLoad').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/German.json"
            }
        });
    } );

    function humanFileSize(bytes, si) {
        var thresh = si ? 1000 : 1024;
        if(Math.abs(bytes) < thresh) {
            return bytes + ' B';
        }
        var units = si
            ? ['kB','MB','GB','TB','PB','EB','ZB','YB']
            : ['KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB'];
        var u = -1;
        do {
            bytes /= thresh;
            ++u;
        } while(Math.abs(bytes) >= thresh && u < units.length - 1);
        return bytes.toFixed(2)+' '+units[u];
    }

    function number_format (number, decimals, dec_point, thousands_sep) {
        // Strip all characters but numerical ones.
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number,
            prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
            sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
            dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
            s = '',
            toFixedFix = function (n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
        // Fix for IE parseFloat(0.55).toFixed(0) = 0;
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
    }

</script>

<?php

if(isset($_SESSION['success_sweet_msg']) && !empty($_SESSION['success_sweet_msg'])){
    echo sendSweetSuccess($_SESSION['success_sweet_msg']);
    $_SESSION['success_sweet_msg'] = '';
    unset($_SESSION['success_sweet_msg']);
}

if(isset($_SESSION['product_locked_msg']) && !empty($_SESSION['product_locked_msg'])){
    echo sendSweetError($_SESSION['product_locked_msg'],'Dein Produkt ist gesperrt');
    $_SESSION['product_locked_msg'] = '';
    unset($_SESSION['product_locked_msg']);
}

?>

<?php if($user->getDataById($userid,'livechat')){ ?>
    <script type='text/javascript' data-cfasync='false'>window.purechatApi = { l: [], t: [], on: function () { this.l.push(arguments); } }; (function () { var done = false; var script = document.createElement('script'); script.async = true; script.type = 'text/javascript'; script.src = 'https://app.purechat.com/VisitorWidget/WidgetScript'; document.getElementsByTagName('HEAD').item(0).appendChild(script); script.onreadystatechange = script.onload = function (e) { if (!done && (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete')) { var w = new PCWidget({c: '9c22763e-eb8c-46cb-b2d9-e940fa151d38', f: true }); done = true; } }; })();</script>
<?php } ?>

</body>
</html>