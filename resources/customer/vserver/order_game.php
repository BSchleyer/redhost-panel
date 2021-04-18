<?php
$currPage = 'front_OVH Game vServer bestellen';
include BASE_PATH.'app/controller/PageController.php';
include BASE_PATH.'app/manager/customer/vserver/order_game.php';
?>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <?php if($user->sessionExists($_COOKIE['session_token'])){ ?>
        <div class="subheader py-2 py-lg-4 subheader-solid" id="kt_subheader">
            <div class="container-fluid d-flex align-items-center justify-content-between flex-wrap flex-sm-nowrap">
                <div class="d-flex align-items-center flex-wrap mr-2">
                    <h5 class="text-dark font-weight-bold mt-2 mb-2 mr-5"><?= env('APP_NAME'); ?></h5>
                    <div class="subheader-separator subheader-separator-ver mt-2 mb-2 mr-4 bg-gray-200"></div>
                    <span class="text-muted font-weight-bold mr-4"><?= $currPageName; ?></span>
                </div>
            </div>
        </div>
        <div class="text-center" style="margin-top: 50px; margin-bottom: 50px;">
            <h1 style="font-size: 70px;">Game Anti <b style="color: #9e2033;">DDoS</b> vServer</h1>
            <hr style="width: 50%;">
            <h1>Miete dir jetzt Prepaid LXC vServer mit OVH Game Anti DDoS Protection</h1>
            <h1>Bestens geeignet für z.B. Teamspeak oder GameServer</h1>
        </div>

    <?php } ?>

    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="row">

                <?php
                $SQL = $db->prepare("SELECT * FROM `vm_server_packs` WHERE `type` = 'game'");
                $SQL->execute();
                if ($SQL->rowCount() != 0) {
                while ($row = $SQL -> fetch(PDO::FETCH_ASSOC)){
                ?>
                <div class="col-md-4">
                    <div class="card shadow mb-5">
                        <div class="card-header text-center">
                            <span class="svg-icon svg-icon-primary svg-icon-8x">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <rect x="0" y="0" width="24" height="24"/>
                                        <path d="M20.4061385,6.73606154 C20.7672665,6.89656288 21,7.25468437 21,7.64987309 L21,16.4115967 C21,16.7747638 20.8031081,17.1093844 20.4856429,17.2857539 L12.4856429,21.7301984 C12.1836204,21.8979887 11.8163796,21.8979887 11.5143571,21.7301984 L3.51435707,17.2857539 C3.19689188,17.1093844 3,16.7747638 3,16.4115967 L3,7.64987309 C3,7.25468437 3.23273352,6.89656288 3.59386153,6.73606154 L11.5938615,3.18050598 C11.8524269,3.06558805 12.1475731,3.06558805 12.4061385,3.18050598 L20.4061385,6.73606154 Z" fill="#000000" opacity="0.3"/>
                                        <polygon fill="#000000" points="14.9671522 4.22441676 7.5999999 8.31727912 7.5999999 12.9056825 9.5999999 13.9056825 9.5999999 9.49408582 17.25507 5.24126912"/>
                                    </g>
                                </svg>
                            </span>
                            <br><br>
                            <h1 style="font-size: 30px;"><?= $row['name']; ?></h1>
                            <b><span style="font-size: 28px;"><?= $row['price']; ?>€</span> / 30 Tage</b>
                        </div>
                        <div class="card-body">
                            <form method="post">
                            <span class="svg-icon svg-icon-primary svg-icon-2x"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <rect fill="#000000" opacity="0.3" x="4" y="4" width="16" height="16" rx="2"/>
                                <rect fill="#000000" opacity="0.3" x="9" y="9" width="6" height="6"/>
                                <path d="M20,7 L21,7 C21.5522847,7 22,7.44771525 22,8 L22,8 C22,8.55228475 21.5522847,9 21,9 L20,9 L20,7 Z" fill="#000000"/>
                                <path d="M20,11 L21,11 C21.5522847,11 22,11.4477153 22,12 L22,12 C22,12.5522847 21.5522847,13 21,13 L20,13 L20,11 Z" fill="#000000"/>
                                <path d="M20,15 L21,15 C21.5522847,15 22,15.4477153 22,16 L22,16 C22,16.5522847 21.5522847,17 21,17 L20,17 L20,15 Z" fill="#000000"/>
                                <path d="M3,7 L4,7 L4,9 L3,9 C2.44771525,9 2,8.55228475 2,8 L2,8 C2,7.44771525 2.44771525,7 3,7 Z" fill="#000000"/>
                                <path d="M3,11 L4,11 L4,13 L3,13 C2.44771525,13 2,12.5522847 2,12 L2,12 C2,11.4477153 2.44771525,11 3,11 Z" fill="#000000"/>
                                <path d="M3,15 L4,15 L4,17 L3,17 C2.44771525,17 2,16.5522847 2,16 L2,16 C2,15.4477153 2.44771525,15 3,15 Z" fill="#000000"/>
                            </g>
                        </svg><!--end::Svg Icon--></span> <?= $row['cores']; ?> Kerne<br>
                        <span class="svg-icon svg-icon-primary svg-icon-2x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Devices/SD-card.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <path d="M5,5 C5,4.44771525 5.44771525,4 6,4 L15.75,4 C15.9073787,4 16.0555728,4.07409708 16.15,4.2 L18.9,7.86666667 C18.9649111,7.95321475 19,8.05848156 19,8.16666667 L19,19 C19,19.5522847 18.5522847,20 18,20 L6,20 C5.44771525,20 5,19.5522847 5,19 L5,5 Z M7,6 L7,9 L9,9 L9,6 L7,6 Z M10,6 L10,9 L12,9 L12,6 L10,6 Z M13,6 L13,9 L15,9 L15,6 L13,6 Z" fill="#000000"/>
                            </g>
                        </svg><!--end::Svg Icon--></span> <?= $row['memory']/1024; ?>GB Ram<br>
                        <span class="svg-icon svg-icon-primary svg-icon-2x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Devices/Hard-drive.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <path d="M2,13 L22,13 L22,18 C22,19.1045695 21.1045695,20 20,20 L4,20 C2.8954305,20 2,19.1045695 2,18 L2,13 Z M18.5,18 C19.3284271,18 20,17.3284271 20,16.5 C20,15.6715729 19.3284271,15 18.5,15 C17.6715729,15 17,15.6715729 17,16.5 C17,17.3284271 17.6715729,18 18.5,18 Z M13.5,18 C14.3284271,18 15,17.3284271 15,16.5 C15,15.6715729 14.3284271,15 13.5,15 C12.6715729,15 12,15.6715729 12,16.5 C12,17.3284271 12.6715729,18 13.5,18 Z" fill="#000000"/>
                                <path d="M5.79268604,8 L18.207314,8 C18.5457897,8 18.8612922,8.17121884 19.0457576,8.45501165 L22,13 L2,13 L4.95424243,8.45501165 C5.13870775,8.17121884 5.45421032,8 5.79268604,8 Z" fill="#000000" opacity="0.3"/>
                            </g>
                        </svg><!--end::Svg Icon--></span> <?= $row['disk']; ?>GB SSD Speicher<br>
						<span class="svg-icon svg-icon-primary svg-icon-2x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Map/Marker1.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <path d="M5,10.5 C5,6 8,3 12.5,3 C17,3 20,6.75 20,10.5 C20,12.8325623 17.8236613,16.03566 13.470984,20.1092932 C12.9154018,20.6292577 12.0585054,20.6508331 11.4774555,20.1594925 C7.15915182,16.5078313 5,13.2880005 5,10.5 Z M12.5,12 C13.8807119,12 15,10.8807119 15,9.5 C15,8.11928813 13.8807119,7 12.5,7 C11.1192881,7 10,8.11928813 10,9.5 C10,10.8807119 11.1192881,12 12.5,12 Z" fill="#000000" fill-rule="nonzero"/>
                            </g>
                        </svg><!--end::Svg Icon--></span> Standort Frankreich (OVH) <i class="fas fa-check text-success"></i><br>
						<span class="svg-icon svg-icon-primary svg-icon-2x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Communication/RSS.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <circle fill="#000000" cx="6" cy="18" r="3"/>
                                <path d="M16.5,21 L13.5,21 C13.5,15.2010101 8.79898987,10.5 3,10.5 L3,7.5 C10.4558441,7.5 16.5,13.5441559 16.5,21 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"/>
                                <path d="M22.5,21 L19.5,21 C19.5,12.163444 11.836556,4.5 3,4.5 L3,1.5 C13.4934102,1.5 22.5,10.5065898 22.5,21 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"/>
                            </g>
                        </svg><!--end::Svg Icon--></span> 500 MBit/s Uplink <i class="fas fa-check text-success"></i><br>
                                                <span class="svg-icon svg-icon-primary svg-icon-2x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Shopping/Chart-bar2.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <rect fill="#000000" opacity="0.3" x="17" y="4" width="3" height="13" rx="1.5"/>
                                <rect fill="#000000" opacity="0.3" x="12" y="9" width="3" height="8" rx="1.5"/>
                                <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero"/>
                                <rect fill="#000000" opacity="0.3" x="7" y="11" width="3" height="6" rx="1.5"/>
                            </g>
                        </svg><!--end::Svg Icon--></span> Unlimited Traffic <i class="fas fa-check text-success"></i><br>
						<span class="svg-icon svg-icon-primary svg-icon-2x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Communication/Shield-thunder.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <path d="M4,4 L11.6314229,2.5691082 C11.8750185,2.52343403 12.1249815,2.52343403 12.3685771,2.5691082 L20,4 L20,13.2830094 C20,16.2173861 18.4883464,18.9447835 16,20.5 L12.5299989,22.6687507 C12.2057287,22.8714196 11.7942713,22.8714196 11.4700011,22.6687507 L8,20.5 C5.51165358,18.9447835 4,16.2173861 4,13.2830094 L4,4 Z" fill="#000000" opacity="0.3"/>
                                <polygon fill="#000000" opacity="0.3" points="11.3333333 18 16 11.4 13.6666667 11.4 13.6666667 7 9 13.6 11.3333333 13.6"/>
                            </g>
                        </svg><!--end::Svg Icon--></span> OVH-Game Anti DDoS <i class="fas fa-check text-success"></i><br>
						<span class="svg-icon svg-icon-primary svg-icon-2x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Files/File.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <polygon points="0 0 24 0 24 24 0 24"/>
                                <path d="M5.85714286,2 L13.7364114,2 C14.0910962,2 14.4343066,2.12568431 14.7051108,2.35473959 L19.4686994,6.3839416 C19.8056532,6.66894833 20,7.08787823 20,7.52920201 L20,20.0833333 C20,21.8738751 19.9795521,22 18.1428571,22 L5.85714286,22 C4.02044787,22 4,21.8738751 4,20.0833333 L4,3.91666667 C4,2.12612489 4.02044787,2 5.85714286,2 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"/>
                                <rect fill="#000000" x="6" y="11" width="9" height="2" rx="1"/>
                                <rect fill="#000000" x="6" y="15" width="5" height="2" rx="1"/>
                            </g>
                        </svg><!--end::Svg Icon--></span> Keine Vertragsbindung	<i class="fas fa-check text-success"></i><br>
                        <span class="svg-icon svg-icon-primary svg-icon-2x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Devices/Gamepad2.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <path d="M12.9486833,8.31622777 L11.0513167,7.68377223 C11.8160243,5.38964935 13.0426772,3.95855428 14.7574644,3.5298575 C15.650287,3.30665184 17,1.86951059 17,1 L19,1 C19,2.79715607 17.0163797,5.02668149 15.2425356,5.4701425 C14.2906561,5.70811238 13.517309,6.61035065 12.9486833,8.31622777 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"/>
                                <path d="M7.81798367,7 L16.2025685,7 C17.5586976,6.72948613 18.9494633,7.42723712 19.526457,8.72046451 L22.9501217,16.3939916 C23.4665806,17.5515411 23.1998005,18.9087734 22.2836331,19.7847233 L21.8392372,20.2096113 C21.8188115,20.2291404 21.7980738,20.2483405 21.7770321,20.2672042 C20.6904893,21.2412841 19.0200246,21.1501149 18.0459447,20.0635721 L15.2994684,17 L8.86456314,17 L6.11808685,20.0635721 C5.14400696,21.1501149 3.47354224,21.2412841 2.38699945,20.2672042 C2.36595778,20.2483405 2.34522009,20.2291404 2.3247944,20.2096113 L1.85770343,19.7630245 C0.952705301,18.8977536 0.680264004,17.5614241 1.17430192,16.4109277 L4.46146538,8.75590867 C5.02845054,7.43553556 6.44099515,6.71763226 7.81798367,7 Z M8,14 C9.1045695,14 10,13.1045695 10,12 C10,10.8954305 9.1045695,10 8,10 C6.8954305,10 6,10.8954305 6,12 C6,13.1045695 6.8954305,14 8,14 Z M17,12 C17.5522847,12 18,11.5522847 18,11 C18,10.4477153 17.5522847,10 17,10 C16.4477153,10 16,10.4477153 16,11 C16,11.5522847 16.4477153,12 17,12 Z M15,14 C15.5522847,14 16,13.5522847 16,13 C16,12.4477153 15.5522847,12 15,12 C14.4477153,12 14,12.4477153 14,13 C14,13.5522847 14.4477153,14 15,14 Z" fill="#000000"/>
                            </g>
                        </svg><!--end::Svg Icon--></span> OneClick-Installer (Mehr dazu <a target="_blank" href="https://red-host.eu/faq">hier</a>) <i class="fas fa-check text-success"></i><br>
                        <span class="svg-icon svg-icon-primary svg-icon-2x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Code/Warning-1-circle.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <circle fill="#000000" opacity="0.3" cx="12" cy="12" r="10"/>
                                <rect fill="#000000" x="11" y="7" width="2" height="8" rx="1"/>
                                <rect fill="#000000" x="11" y="16" width="2" height="2" rx="1"/>
                            </g>
                        </svg><!--end::Svg Icon--></span> Kein Up/Downgrade möglich

                                <br>
                                <br>
                                <hr>
                                <br>

                                <i class="fab fa-linux"></i> <b><label style="font-weight: bold;" for="serverOS">Betriebssystem</label></b>
                                <select class="form-control" id="serverOS" name="serverOS">
                                    <?php
                                    $SQL3 = $db->prepare("SELECT * FROM `vm_server_os` WHERE `type` = 'LXC'");
                                    $SQL3->execute();
                                    if ($SQL3->rowCount() != 0) { while ($row3 = $SQL3->fetch(PDO::FETCH_ASSOC)) { ?>
                                        <option value="<?= $row3['id']; ?>"><?= $row3['name']; ?></option>
                                    <?php } } ?>
                                </select>

                                <br>
                                <hr>
                                <br>

                                <input hidden value="<?= $row['id']; ?>" name="pack_id">

                                <label for="agb<?= $row['id']; ?>" class="checkbox noselect">
                                    <input type="checkbox" name="agb" id="agb<?= $row['id']; ?>">
                                    <span></span>
                                    Ich habe die <a href="<?= $helper->url(); ?>agb">AGB</a> und <a href="<?= $helper->url(); ?>datenschutz">Datenschutzerklärung</a> gelesen und akzeptiere diese.
                                </label>
                                <br>
                                <br>
                                <label for="wiederruf<?= $row['id']; ?>" class="checkbox noselect">
                                    <input type="checkbox" name="wiederruf" id="wiederruf<?= $row['id']; ?>">
                                    <span></span>
                                    Ich wünsche die vollständige Ausführung der Dienstleistung vor Fristablauf des Widerufsrechts gemäß Fernabsatzgesetz. Die automatische Einrichtung und Erbringung der Dienstleistung führt zum Erlöschen des Widerrufsrechts.
                                </label>

                                <br>
                                <br>

                                <button type="submit" name="order" class="btn btn-block btn-outline-primary mb-4 pulse-red">
                                    <i class="fas fa-shopping-cart"></i> <b>Kostenpflichtig bestellen</b>
                                </button>

                            </form>
                        </div>
                    </div>
                </div>
                <?php } } ?>

            </div>
        </div>
    </div>
</div>