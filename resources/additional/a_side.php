<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2022 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel
 *  * @file        a_side.php
 *  * @author      BjörnSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        18.8.2022
 *  * @time        20:20
 *
 */

?>

<!--begin::Aside-->
<div id="kt_aside" class="aside" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_mobile_toggle">
    <!--begin::Aside Toolbarl-->
    <div class="aside-toolbar flex-column-auto" id="kt_aside_toolbar">
        <!--begin::Aside user-->
        <!--begin::User-->
        <div class="aside-user d-flex align-items-sm-center justify-content-center py-5">
            <!--begin::Symbol-->
            <div class="symbol symbol-50px">
                <img src="https://api.cookiemc.de/200/<?= $username; ?>.png?ssl=0" alt="User-Logo" />
            </div>
            <!--end::Symbol-->
            <!--begin::Wrapper-->
            <div class="aside-user-info flex-row-fluid flex-wrap ms-5">
                <!--begin::Section-->
                <div class="d-flex">
                    <!--begin::Info-->
                    <div class="flex-grow-1 me-2">
                        <!--begin::Username-->
                        <a href="javascript:;" class="text-white text-hover-primary fs-6 fw-bold">
                            <?php
                            if(!is_null($user->getDataById($userid, 'firstname')) || !empty($user->getDataById($userid, 'firstname'))) {
                                if(!is_null($user->getDataById($userid, 'lastname') || !empty($user->getDataById($userid, 'lastname')))) {
                                    echo $user->getDataById($userid, 'firstname') . ' ' . $user->getDataById($userid, 'lastname');
                                }
                            } else {
                                echo $username;
                            }
                            ?>
                        </a>
                        <!--end::Username-->
                        <!--begin::Description-->
                        <span class="text-gray-600 fw-bold d-block fs-8 mb-1"><?= $user->getRole($userid); ?></span>
                        <!--end::Description-->
                        <!--begin::Label-->
                        <div class="d-flex align-items-center text-success fs-9">
                            <span class="bullet bullet-dot bg-success me-1"></span> angemeldet
                        </div>
                        <!--end::Label-->
                    </div>
                    <!--end::Info-->
                    <!--begin::User menu-->
                    <div class="me-n2">
                        <!--begin::Action-->
                        <a href="javascript:;" class="btn btn-icon btn-sm btn-active-color-primary mt-n2" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-start" data-kt-menu-overflow="true">
                            <!--begin::Svg Icon | path: icons/duotune/coding/cod001.svg-->
                            <span class="svg-icon svg-icon-muted svg-icon-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path opacity="0.3" d="M22.1 11.5V12.6C22.1 13.2 21.7 13.6 21.2 13.7L19.9 13.9C19.7 14.7 19.4 15.5 18.9 16.2L19.7 17.2999C20 17.6999 20 18.3999 19.6 18.7999L18.8 19.6C18.4 20 17.8 20 17.3 19.7L16.2 18.9C15.5 19.3 14.7 19.7 13.9 19.9L13.7 21.2C13.6 21.7 13.1 22.1 12.6 22.1H11.5C10.9 22.1 10.5 21.7 10.4 21.2L10.2 19.9C9.4 19.7 8.6 19.4 7.9 18.9L6.8 19.7C6.4 20 5.7 20 5.3 19.6L4.5 18.7999C4.1 18.3999 4.1 17.7999 4.4 17.2999L5.2 16.2C4.8 15.5 4.4 14.7 4.2 13.9L2.9 13.7C2.4 13.6 2 13.1 2 12.6V11.5C2 10.9 2.4 10.5 2.9 10.4L4.2 10.2C4.4 9.39995 4.7 8.60002 5.2 7.90002L4.4 6.79993C4.1 6.39993 4.1 5.69993 4.5 5.29993L5.3 4.5C5.7 4.1 6.3 4.10002 6.8 4.40002L7.9 5.19995C8.6 4.79995 9.4 4.39995 10.2 4.19995L10.4 2.90002C10.5 2.40002 11 2 11.5 2H12.6C13.2 2 13.6 2.40002 13.7 2.90002L13.9 4.19995C14.7 4.39995 15.5 4.69995 16.2 5.19995L17.3 4.40002C17.7 4.10002 18.4 4.1 18.8 4.5L19.6 5.29993C20 5.69993 20 6.29993 19.7 6.79993L18.9 7.90002C19.3 8.60002 19.7 9.39995 19.9 10.2L21.2 10.4C21.7 10.5 22.1 11 22.1 11.5ZM12.1 8.59998C10.2 8.59998 8.6 10.2 8.6 12.1C8.6 14 10.2 15.6 12.1 15.6C14 15.6 15.6 14 15.6 12.1C15.6 10.2 14 8.59998 12.1 8.59998Z" fill="black" />
                                    <path d="M17.1 12.1C17.1 14.9 14.9 17.1 12.1 17.1C9.30001 17.1 7.10001 14.9 7.10001 12.1C7.10001 9.29998 9.30001 7.09998 12.1 7.09998C14.9 7.09998 17.1 9.29998 17.1 12.1ZM12.1 10.1C11 10.1 10.1 11 10.1 12.1C10.1 13.2 11 14.1 12.1 14.1C13.2 14.1 14.1 13.2 14.1 12.1C14.1 11 13.2 10.1 12.1 10.1Z" fill="black" />
                                </svg>
                            </span>
                            <!--end::Svg Icon-->
                        </a>
                        <!--begin::User account menu-->
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px" data-kt-menu="true">
                            <!--begin::Menu item-->
                            <div class="menu-item px-3">
                                <div class="menu-content d-flex align-items-center px-3">
                                    <!--begin::Avatar-->
                                    <div class="symbol symbol-50px me-5">
                                        <img alt="User-Logo" src="https://api.cookiemc.de/200/<?= $username; ?>.png?ssl=0" />
                                    </div>
                                    <!--end::Avatar-->
                                    <!--begin::Username-->
                                    <div class="d-flex flex-column">
                                        <div class="fw-bolder d-flex align-items-center fs-5"><?php
                                            if(!is_null($user->getDataById($userid, 'firstname')) || !empty($user->getDataById($userid, 'firstname'))) {
                                                if(!is_null($user->getDataById($userid, 'lastname') || !empty($user->getDataById($userid, 'lastname')))) {
                                                    echo $user->getDataById($userid, 'firstname') . ' ' . $user->getDataById($userid, 'lastname');
                                                }
                                            } else {
                                                echo $username;
                                            }
                                            ?>
                                            <span class="badge badge-light-success fw-bolder fs-8 px-2 py-1 ms-2">
                                                Pro
                                            </span>
                                        </div>
                                        <a href="email:<?= $email; ?>" class="fw-bold text-muted text-hover-primary fs-7"><?= $email; ?></a>
                                    </div>
                                    <!--end::Username-->
                                </div>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu separator-->
                            <div class="separator my-2"></div>
                            <!--end::Menu separator-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <a href="<?= env('URL') . 'settings/profile/'; ?>" class="menu-link px-5">Mein Profil</a>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->

                            <div class="menu-item px-5">
                                <a href="" class="menu-link px-5">
                                    <span class="menu-text">Meine Abonnements</span>
                                    <span class="menu-badge">
                                        <span class="badge badge-light-success badge-circle fw-bolder fs-7">
                                            0
                                        </span>
                                    </span>
                                </a>
                            </div>

                            <div class="menu-item px-5">
                                <a href="" class="menu-link px-5">
                                    <span class="menu-text">Meine Statistiken</span>
                                </a>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <a href="../../demo8/dist/account/statements.html" class="menu-link px-5">
                                    <span class="menu-text">
                                        E-Mail Verlauf
                                    </span>
                                    <span class="menu-badge">
                                        <span class="badge badge-light-danger badge-circle fw-bolder fs-7">
                                            0
                                        </span>
                                    </span>
                                </a>
                            </div>

                            <!--end::Menu item-->
                            <!--begin::Menu separator-->
                            <div class="separator my-2"></div>
                            <!--end::Menu separator-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5" data-kt-menu-trigger="hover" data-kt-menu-placement="right-start">
                                <a href="#" class="menu-link px-5">
                                    <span class="menu-title position-relative">Sprache
                                        <span class="fs-8 rounded bg-light px-3 py-2 position-absolute translate-middle-y top-50 end-0">German
                                            <img class="w-15px h-15px rounded-1 ms-2" src="<?= $helper->styleUrl(); ?>media/flags/germany.svg" alt="" />
                                        </span>
                                    </span>
                                </a>
                                <!--begin::Menu sub-->
                                <div class="menu-sub menu-sub-dropdown w-175px py-4">
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="javascript:;" class="menu-link d-flex px-5 active">
                                            <span class="symbol symbol-20px me-4">
                                                <img class="rounded-1" src="<?= $helper->styleUrl(); ?>media/flags/germany.svg" alt="" />
                                            </span>German</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="javascript:;" class="menu-link d-flex px-5">
                                            <span class="symbol symbol-20px me-4">
                                                <img class="rounded-1" src="<?= $helper->styleUrl(); ?>media/flags/united-states.svg" alt="" />
                                            </span>
                                            English
                                        </a>
                                    </div>
                                    <!--end::Menu item-->
                                </div>
                                <!--end::Menu sub-->
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <a href="<?= env('URL') . 'auth/logout/'; ?>" class="menu-link px-5">Abmelden</a>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu separator-->
                            <div class="separator my-2"></div>
                            <!--end::Menu separator-->
                            <!--begin::Menu item-->
                            <form method="post">
                                <div class="menu-item px-5">
                                    <div class="menu-content px-5">
                                        <label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success">
                                            <input class="form-check-input w-30px h-20px" type="checkbox" <?php if($user->getDataById($userid, 'darkmode')){ echo 'checked="checked"'; } ?> name="darkmode">
                                            <span class="pulse-ring ms-n1"></span>
                                            <span class="form-check-label text-gray-600 fs-7">Dark Mode</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="menu-item px-5">
                                    <div class="menu-content px-5">
                                        <label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success">
                                            <input class="form-check-input w-30px h-20px" type="checkbox" <?php if($user->getDataById($userid, 'datasavingmode')){ echo 'checked="checked"'; } ?> name="datasavingmode">
                                            <span class="pulse-ring ms-n1"></span>
                                            <span class="form-check-label text-gray-600 fs-7">Datensparmodus</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="menu-item px-5">
                                    <div class="menu-content px-5">
                                        <label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success">
                                            <input class="form-check-input w-30px h-20px" type="checkbox" <?php if($user->getDataById($userid, 'livechat')){ echo 'checked="checked"'; } ?> name="livechat">
                                            <span class="pulse-ring ms-n1"></span>
                                            <span class="form-check-label text-gray-600 fs-7">Live-Chat</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="menu-item px-5">
                                    <div class="menu-content px-5">
                                        <button type="submit" name="saveSettings" class="btn btn-outline-primary btn-block btn-sm"><b>Speichern</b></button>
                                    </div>
                                </div>
                            </form>
                            <!--end::Menu item-->
                        </div>
                        <!--end::User account menu-->
                        <!--end::Action-->
                    </div>
                    <!--end::User menu-->
                </div>
                <!--end::Section-->
            </div>
            <!--end::Wrapper-->
        </div>
    </div>
    <!--end::Aside Toolbarl-->
    <!--begin::Aside menu-->
    <div class="aside-menu flex-column-fluid">
        <!--begin::Aside Menu-->
        <div class="hover-scroll-overlay-y px-2 my-5 my-lg-5" id="kt_aside_menu_wrapper" data-kt-scroll="true" data-kt-scroll-height="auto" data-kt-scroll-dependencies="{default: '#kt_aside_toolbar, #kt_aside_footer', lg: '#kt_header, #kt_aside_toolbar, #kt_aside_footer'}" data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="5px">
            <!--begin::Menu-->
            <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500 menu-active-bg" id="#kt_aside_menu" data-kt-menu="true">
                <div class="menu-item">
                    <a class="menu-link <?php if($helper->protect($_GET['page']) == 'customer_index') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'index/'; ?>">
                        <span class="menu-icon">
							<span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <rect x="2" y="2" width="9" height="9" rx="2" fill="black" />
                                    <rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="black" />
                                    <rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="black" />
                                    <rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="black" />
                                </svg>
                            </span>
                        </span>
                        <span class="menu-title">Dashboard</span>
                    </a>
                </div>

                <div class="menu-item">
                    <div class="menu-content pt-8 pb-2">
                        <span class="menu-section text-muted text-uppercase fs-8 ls-1">Produkte</span>
                    </div>
                </div>

                <!-- begin:product teamspeak server -->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                    <span class="menu-link">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm007.svg-->
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path d="M12.9975507,17.929461 C12.9991745,17.9527631 13,17.9762852 13,18 L13,21 C13,21.5522847 12.5522847,22 12,22 C11.4477153,22 11,21.5522847 11,21 L11,18 C11,17.9762852 11.0008255,17.9527631 11.0024493,17.929461 C7.60896116,17.4452857 5,14.5273206 5,11 L7,11 C7,13.7614237 9.23857625,16 12,16 C14.7614237,16 17,13.7614237 17,11 L19,11 C19,14.5273206 16.3910388,17.4452857 12.9975507,17.929461 Z" fill="#000000" fill-rule="nonzero" />
                                    <rect fill="#000000" opacity="0.3" transform="translate(12.000000, 8.000000) rotate(-360.000000) translate(-12.000000, -8.000000)" x="9" y="2" width="6" height="12" rx="3" />
                                </g>
                            </svg>

                            </span>
                                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-title">TeamSpeak</span>
                        <span class="menu-arrow"></span>
                    </span>

                    <div class="menu-sub menu-sub-accordion menu-active-bg">
                        <div class="menu-item">
                            <a class="menu-link <?php if($helper->protect($_GET['page']) == 'p_teamspeak_order') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'order/teamspeak/'; ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Bestellung tätigen</span>
                            </a>
                        </div>

                        <?php
                        $SQL = $db->prepare("SELECT * FROM `teamspeak_servers` WHERE `user_id` = :user_id AND `deleted_at` IS NULL");
                        $SQL->execute(array(":user_id" => $userid));
                        if($SQL->rowCount() != 0) { ?>

                            <div class="menu-item">
                                <a class="menu-link <?php if($helper->protect($_GET['page']) == 'teamspeak_index' || $helper->protect($_GET['page']) == 'teamspeak_manage' || $helper->protect($_GET['page']) == 'teamspeak_renew' || $helper->protect($_GET['page']) == 'teamspeak_reconfigure') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'manage/teamspeaks/'; ?>">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Verwalten</span>
                                </a>
                            </div>
                        <?php } ?>

                    </div>
                </div>
                <!-- end:product teamspeak server -->

                <!-- begin:product kvm rootserver -->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                    <span class="menu-link">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm007.svg-->
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <rect x="0" y="0" width="24" height="24" />
                                        <path d="M5,2 L19,2 C20.1045695,2 21,2.8954305 21,4 L21,6 C21,7.1045695 20.1045695,8 19,8 L5,8 C3.8954305,8 3,7.1045695 3,6 L3,4 C3,2.8954305 3.8954305,2 5,2 Z M11,4 C10.4477153,4 10,4.44771525 10,5 C10,5.55228475 10.4477153,6 11,6 L16,6 C16.5522847,6 17,5.55228475 17,5 C17,4.44771525 16.5522847,4 16,4 L11,4 Z M7,6 C7.55228475,6 8,5.55228475 8,5 C8,4.44771525 7.55228475,4 7,4 C6.44771525,4 6,4.44771525 6,5 C6,5.55228475 6.44771525,6 7,6 Z" fill="#000000" opacity="0.3" />
                                        <path d="M5,9 L19,9 C20.1045695,9 21,9.8954305 21,11 L21,13 C21,14.1045695 20.1045695,15 19,15 L5,15 C3.8954305,15 3,14.1045695 3,13 L3,11 C3,9.8954305 3.8954305,9 5,9 Z M11,11 C10.4477153,11 10,11.4477153 10,12 C10,12.5522847 10.4477153,13 11,13 L16,13 C16.5522847,13 17,12.5522847 17,12 C17,11.4477153 16.5522847,11 16,11 L11,11 Z M7,13 C7.55228475,13 8,12.5522847 8,12 C8,11.4477153 7.55228475,11 7,11 C6.44771525,11 6,11.4477153 6,12 C6,12.5522847 6.44771525,13 7,13 Z" fill="#000000" />
                                        <path d="M5,16 L19,16 C20.1045695,16 21,16.8954305 21,18 L21,20 C21,21.1045695 20.1045695,22 19,22 L5,22 C3.8954305,22 3,21.1045695 3,20 L3,18 C3,16.8954305 3.8954305,16 5,16 Z M11,18 C10.4477153,18 10,18.4477153 10,19 C10,19.5522847 10.4477153,20 11,20 L16,20 C16.5522847,20 17,19.5522847 17,19 C17,18.4477153 16.5522847,18 16,18 L11,18 Z M7,20 C7.55228475,20 8,19.5522847 8,19 C8,18.4477153 7.55228475,18 7,18 C6.44771525,18 6,18.4477153 6,19 C6,19.5522847 6.44771525,20 7,20 Z" fill="#000000" />
                                    </g>
                                </svg>

                            </span>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-title">KVM-Rootserver</span>
                        <span class="menu-arrow"></span>
                    </span>

                    <div class="menu-sub menu-sub-accordion menu-active-bg">
                        <div class="menu-item">
                            <a class="menu-link <?php if($helper->protect($_GET['page']) == 'p_rootserver_order') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'order/rootserver/'; ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Bestellung tätigen</span>
                            </a>
                        </div>

                        <?php
                        $SQL = $db->prepare("SELECT * FROM `kvm_servers` WHERE `user_id` = :user_id AND `deleted_at` IS NULL");
                        $SQL->execute(array(":user_id" => $userid));
                        if($SQL->rowCount() != 0) { ?>

                            <div class="menu-item">
                                <a class="menu-link <?php if($helper->protect($_GET['page']) == 'rootserver_index' || $helper->protect($_GET['page']) == 'rootserver_manage' || $helper->protect($_GET['page']) == 'rootserver_renew' || $helper->protect($_GET['page']) == 'rootserver_reconfigure') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'index/rootserver/'; ?>">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Verwalten</span>
                                </a>
                            </div>
                        <?php } ?>

                    </div>
                </div>
                <!-- end:product kvm rootserver -->

                <!-- begin:product webspace -->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                    <span class="menu-link">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm007.svg-->
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <polygon points="0 0 24 0 24 24 0 24"/>
                                        <path d="M7.74714567,15.0425758 C6.09410362,13.9740356 5,12.1147886 5,10 C5,6.6862915 7.6862915,4 11,4 C13.7957591,4 16.1449096,5.91215918 16.8109738,8.5 L19.25,8.5 C21.3210678,8.5 23,10.1789322 23,12.25 C23,14.3210678 21.3210678,16 19.25,16 L10.25,16 C9.28817895,16 8.41093178,15.6378962 7.74714567,15.0425758 Z" fill="#000000" opacity="0.3"/>
                                        <path d="M3.74714567,19.0425758 C2.09410362,17.9740356 1,16.1147886 1,14 C1,10.6862915 3.6862915,8 7,8 C9.79575914,8 12.1449096,9.91215918 12.8109738,12.5 L15.25,12.5 C17.3210678,12.5 19,14.1789322 19,16.25 C19,18.3210678 17.3210678,20 15.25,20 L6.25,20 C5.28817895,20 4.41093178,19.6378962 3.74714567,19.0425758 Z" fill="#000000"/>
                                    </g>
                                </svg>

                            </span>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-title">Webspace</span>
                        <span class="menu-arrow"></span>
                    </span>

                    <div class="menu-sub menu-sub-accordion menu-active-bg">
                        <div class="menu-item">
                            <a class="menu-link <?php if($helper->protect($_GET['page']) == 'p_webspace_order') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'order/webspace/'; ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Bestellung tätigen</span>
                            </a>
                        </div>

                        <?php
                        $SQL = $db->prepare("SELECT * FROM `webspaces` WHERE `user_id` = :user_id AND `deleted_at` IS NULL");
                        $SQL->execute(array(":user_id" => $userid));
                        if($SQL->rowCount() != 0) { ?>

                            <div class="menu-item">
                                <a class="menu-link <?php if($helper->protect($_GET['page']) == 'webspace_index' || $helper->protect($_GET['page']) == 'webspace_manage' || $helper->protect($_GET['page']) == 'webspace_renew') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'manage/webspaces/'; ?>">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Verwalten</span>
                                </a>
                            </div>
                        <?php } ?>

                    </div>
                </div>

                <!-- end:product webspace -->

                <?php
                $SQL = $db->prepare("SELECT * FROM `services` WHERE `user_id` = :user_id AND `deleted_at` IS NULL");
                $SQL->execute(array(":user_id" => $userid));
                if($SQL->rowCount() != 0) { ?>
                <!-- begin:product services -->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                    <span class="menu-link">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm007.svg-->
                            <span class="svg-icon svg-icon-2">
                                <i class="fab fa-buffer"></i>
                            </span>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-title">Services</span>
                        <span class="menu-arrow"></span>
                    </span>

                    <div class="menu-sub menu-sub-accordion menu-active-bg">
                        <div class="menu-item">
                            <a class="menu-link <?php if($helper->protect($_GET['page']) == 'service_index' || $helper->protect($_GET['page']) == 'service_manage' || $helper->protect($_GET['page']) == 'service_renew') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'index/services/'; ?>">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">Verwalten</span>
                               </a>
                        </div>
                    </div>
                </div>
                <?php } ?>
                <!-- end:product services -->

                <div class="menu-item">
                    <div class="menu-content pt-8 pb-2">
                        <span class="menu-section text-muted text-uppercase fs-8 ls-1">Support</span>
                    </div>
                </div>

                <!-- begin:support index -->
                <div class="menu-item">
                    <a class="menu-link <?php if($helper->protect($_GET['page']) == 'support_index') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'support/index/'; ?>">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/general/gen019.svg-->
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path opacity="0.3" d="M5.78001 21.115L3.28001 21.949C3.10897 22.0059 2.92548 22.0141 2.75004 21.9727C2.57461 21.9312 2.41416 21.8418 2.28669 21.7144C2.15923 21.5869 2.06975 21.4264 2.0283 21.251C1.98685 21.0755 1.99507 20.892 2.05201 20.7209L2.886 18.2209L7.22801 13.879L10.128 16.774L5.78001 21.115Z" fill="black"/>
                                    <path d="M21.7 8.08899L15.911 2.30005C15.8161 2.2049 15.7033 2.12939 15.5792 2.07788C15.455 2.02637 15.3219 1.99988 15.1875 1.99988C15.0531 1.99988 14.92 2.02637 14.7958 2.07788C14.6717 2.12939 14.5589 2.2049 14.464 2.30005L13.74 3.02295C13.548 3.21498 13.4402 3.4754 13.4402 3.74695C13.4402 4.01849 13.548 4.27892 13.74 4.47095L14.464 5.19397L11.303 8.35498C10.1615 7.80702 8.87825 7.62639 7.62985 7.83789C6.38145 8.04939 5.2293 8.64265 4.332 9.53601C4.14026 9.72817 4.03256 9.98855 4.03256 10.26C4.03256 10.5315 4.14026 10.7918 4.332 10.984L13.016 19.667C13.208 19.859 13.4684 19.9668 13.74 19.9668C14.0115 19.9668 14.272 19.859 14.464 19.667C15.3575 18.77 15.9509 17.618 16.1624 16.3698C16.374 15.1215 16.1932 13.8383 15.645 12.697L18.806 9.53601L19.529 10.26C19.721 10.452 19.9814 10.5598 20.253 10.5598C20.5245 10.5598 20.785 10.452 20.977 10.26L21.7 9.53601C21.7952 9.44108 21.8706 9.32825 21.9221 9.2041C21.9737 9.07995 22.0002 8.94691 22.0002 8.8125C22.0002 8.67809 21.9737 8.54505 21.9221 8.4209C21.8706 8.29675 21.7952 8.18392 21.7 8.08899Z" fill="black"/>
                                </svg>
                            </span>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-title">Übersicht</span>
                    </a>
                </div>
                <!-- end:support index -->

                <!-- begin:ticket system -->
                <div class="menu-item">
                    <a class="menu-link <?php if($helper->protect($_GET['page']) == 'ticket_index' || $helper->protect($_GET['page']) == 'ticket') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'support/tickets/'; ?>">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm007.svg-->
                        <span class="svg-icon svg-icon-2">
                                <!--begin::Svg Icon | path: assets/media/icons/duotune/communication/com010.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path opacity="0.3" d="M8 8C8 7.4 8.4 7 9 7H16V3C16 2.4 15.6 2 15 2H3C2.4 2 2 2.4 2 3V13C2 13.6 2.4 14 3 14H5V16.1C5 16.8 5.79999 17.1 6.29999 16.6L8 14.9V8Z" fill="black"/>
                                <path d="M22 8V18C22 18.6 21.6 19 21 19H19V21.1C19 21.8 18.2 22.1 17.7 21.6L15 18.9H9C8.4 18.9 8 18.5 8 17.9V7.90002C8 7.30002 8.4 6.90002 9 6.90002H21C21.6 7.00002 22 7.4 22 8ZM19 11C19 10.4 18.6 10 18 10H12C11.4 10 11 10.4 11 11C11 11.6 11.4 12 12 12H18C18.6 12 19 11.6 19 11ZM17 15C17 14.4 16.6 14 16 14H12C11.4 14 11 14.4 11 15C11 15.6 11.4 16 12 16H16C16.6 16 17 15.6 17 15Z" fill="black"/>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                            <!--end::Svg Icon-->
                    </span>

                        <span class="menu-title">Support-Tickets</span>
                    </a>
                </div>
                <!-- end:ticket system -->


                <div class="menu-item">
                    <div class="menu-content pt-8 pb-2">
                        <span class="menu-section text-muted text-uppercase fs-8 ls-1">Buchhaltung</span>
                    </div>
                </div>

                <!-- begin:credit charge -->
                <div class="menu-item">
                    <a class="menu-link <?php if($helper->protect($_GET['page']) == 'payment_charge') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'payment/charge/'; ?>">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/general/gen019.svg-->
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <rect x="0" y="0" width="24" height="24" />
                                        <path d="M5,9 L19,9 C20.1045695,9 21,9.8954305 21,11 L21,20 C21,21.1045695 20.1045695,22 19,22 L5,22 C3.8954305,22 3,21.1045695 3,20 L3,11 C3,9.8954305 3.8954305,9 5,9 Z M18.1444251,10.8396467 L12,14.1481833 L5.85557487,10.8396467 C5.4908718,10.6432681 5.03602525,10.7797221 4.83964668,11.1444251 C4.6432681,11.5091282 4.77972206,11.9639747 5.14442513,12.1603533 L11.6444251,15.6603533 C11.8664074,15.7798822 12.1335926,15.7798822 12.3555749,15.6603533 L18.8555749,12.1603533 C19.2202779,11.9639747 19.3567319,11.5091282 19.1603533,11.1444251 C18.9639747,10.7797221 18.5091282,10.6432681 18.1444251,10.8396467 Z" fill="#000000" />
                                        <path d="M11.1288761,0.733697713 L11.1288761,2.69017121 L9.12120481,2.69017121 C8.84506244,2.69017121 8.62120481,2.91402884 8.62120481,3.19017121 L8.62120481,4.21346991 C8.62120481,4.48961229 8.84506244,4.71346991 9.12120481,4.71346991 L11.1288761,4.71346991 L11.1288761,6.66994341 C11.1288761,6.94608579 11.3527337,7.16994341 11.6288761,7.16994341 C11.7471877,7.16994341 11.8616664,7.12798964 11.951961,7.05154023 L15.4576222,4.08341738 C15.6683723,3.90498251 15.6945689,3.58948575 15.5161341,3.37873564 C15.4982803,3.35764848 15.4787093,3.33807751 15.4576222,3.32022374 L11.951961,0.352100892 C11.7412109,0.173666017 11.4257142,0.199862688 11.2472793,0.410612793 C11.1708299,0.500907473 11.1288761,0.615386087 11.1288761,0.733697713 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(11.959697, 3.661508) rotate(-270.000000) translate(-11.959697, -3.661508)" />
                                    </g>
                                </svg>
                            </span>
                                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-title">Guthaben aufladen</span>
                    </a>
                </div>
                <!-- end:credit charge -->

                <!-- begin:transactions -->
                <div class="menu-item">
                    <a class="menu-link <?php if($helper->protect($_GET['page']) == 'payment_transactions') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'payment/transactions/'; ?>">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/general/gen019.svg-->
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M20 19.725V18.725C20 18.125 19.6 17.725 19 17.725H5C4.4 17.725 4 18.125 4 18.725V19.725H3C2.4 19.725 2 20.125 2 20.725V21.725H22V20.725C22 20.125 21.6 19.725 21 19.725H20Z" fill="black"/>
                                    <path opacity="0.3" d="M22 6.725V7.725C22 8.325 21.6 8.725 21 8.725H18C18.6 8.725 19 9.125 19 9.725C19 10.325 18.6 10.725 18 10.725V15.725C18.6 15.725 19 16.125 19 16.725V17.725H15V16.725C15 16.125 15.4 15.725 16 15.725V10.725C15.4 10.725 15 10.325 15 9.725C15 9.125 15.4 8.725 16 8.725H13C13.6 8.725 14 9.125 14 9.725C14 10.325 13.6 10.725 13 10.725V15.725C13.6 15.725 14 16.125 14 16.725V17.725H10V16.725C10 16.125 10.4 15.725 11 15.725V10.725C10.4 10.725 10 10.325 10 9.725C10 9.125 10.4 8.725 11 8.725H8C8.6 8.725 9 9.125 9 9.725C9 10.325 8.6 10.725 8 10.725V15.725C8.6 15.725 9 16.125 9 16.725V17.725H5V16.725C5 16.125 5.4 15.725 6 15.725V10.725C5.4 10.725 5 10.325 5 9.725C5 9.125 5.4 8.725 6 8.725H3C2.4 8.725 2 8.325 2 7.725V6.725L11 2.225C11.6 1.925 12.4 1.925 13.1 2.225L22 6.725ZM12 3.725C11.2 3.725 10.5 4.425 10.5 5.225C10.5 6.025 11.2 6.725 12 6.725C12.8 6.725 13.5 6.025 13.5 5.225C13.5 4.425 12.8 3.725 12 3.725Z" fill="black"/>
                                </svg>
                            </span>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-title">Transaktionen</span>
                    </a>
                </div>
                <!-- end:transactions -->

                <?php if(isset($_COOKIE['old_session_token'])){ ?>

                    <div class="menu-item">
                        <a href="<?= env('URL'); ?>team/login_back/" class="menu-link">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/general/gen019.svg-->
                            <span class="svg-icon svg-icon-2">
                                <svg width="24px" height="24px" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                  <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <rect x="0" y="0" width="24" height="24" />
                                        <path d="M11.1669899,4.49941818 L2.82535718,19.5143571 C2.557144,19.9971408 2.7310878,20.6059441 3.21387153,20.8741573 C3.36242953,20.9566895 3.52957021,21 3.69951446,21 L21.2169432,21 C21.7692279,21 22.2169432,20.5522847 22.2169432,20 C22.2169432,19.8159952 22.1661743,19.6355579 22.070225,19.47855 L12.894429,4.4636111 C12.6064401,3.99235656 11.9909517,3.84379039 11.5196972,4.13177928 C11.3723594,4.22181902 11.2508468,4.34847583 11.1669899,4.49941818 Z" fill="#000000" opacity="0.3" />
                                        <rect fill="#000000" x="11" y="9" width="2" height="7" rx="1" />
                                        <rect fill="#000000" x="11" y="17" width="2" height="2" rx="1" />
                                    </g>
                                </svg>
                            </span>
                        </span>
                            <!--end::Svg Icon-->
                            <span class="menu-title">Zurück zum ACP</span>

                        </a>
                    </div>
                <?php } ?>

                <?php if($user->isInTeam($_COOKIE['session_token'])){ ?>

                    <div class="menu-item">
                        <div class="menu-content pt-8 pb-2">
                            <span class="menu-section text-muted text-uppercase fs-8 ls-1">Team</span>
                        </div>
                    </div>

                    <div class="menu-item">
                        <a class="menu-link <?php if($helper->protect($_GET['page']) == 'team_tickets' || $helper->protect($_GET['page']) == 'team_ticket') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'team/tickets/'; ?>">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm007.svg-->
                            <span class="svg-icon svg-icon-2">
                                    <!--begin::Svg Icon | path: assets/media/icons/duotune/communication/com010.svg-->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path opacity="0.3" d="M8 8C8 7.4 8.4 7 9 7H16V3C16 2.4 15.6 2 15 2H3C2.4 2 2 2.4 2 3V13C2 13.6 2.4 14 3 14H5V16.1C5 16.8 5.79999 17.1 6.29999 16.6L8 14.9V8Z" fill="black"/>
                                    <path d="M22 8V18C22 18.6 21.6 19 21 19H19V21.1C19 21.8 18.2 22.1 17.7 21.6L15 18.9H9C8.4 18.9 8 18.5 8 17.9V7.90002C8 7.30002 8.4 6.90002 9 6.90002H21C21.6 7.00002 22 7.4 22 8ZM19 11C19 10.4 18.6 10 18 10H12C11.4 10 11 10.4 11 11C11 11.6 11.4 12 12 12H18C18.6 12 19 11.6 19 11ZM17 15C17 14.4 16.6 14 16 14H12C11.4 14 11 14.4 11 15C11 15.6 11.4 16 12 16H16C16.6 16 17 15.6 17 15Z" fill="black"/>
                                </svg>
                                <!--end::Svg Icon-->
                            </span>
                            <!--end::Svg Icon-->
                        </span>

                            <span class="menu-title">Support-Tickets</span>
                        </a>
                    </div>

                <?php } ?>

                <?php if($user->isAdmin($_COOKIE['session_token'])){ ?>


                    <div class="menu-item">
                        <a class="menu-link <?php if($helper->protect($_GET['page']) == 'team_users' || $helper->protect($_GET['page']) == 'team_user') { echo 'active'; } else { echo ''; } ?>" href="<?= env('URL') . 'team/users/'; ?>">
                        <span class="menu-icon">
                            <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm007.svg-->
                            <span class="svg-icon svg-icon-2">
                                    <!--begin::Svg Icon | path: assets/media/icons/duotune/communication/com010.svg-->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path opacity="0.3" d="M8 8C8 7.4 8.4 7 9 7H16V3C16 2.4 15.6 2 15 2H3C2.4 2 2 2.4 2 3V13C2 13.6 2.4 14 3 14H5V16.1C5 16.8 5.79999 17.1 6.29999 16.6L8 14.9V8Z" fill="black"/>
                                    <path d="M22 8V18C22 18.6 21.6 19 21 19H19V21.1C19 21.8 18.2 22.1 17.7 21.6L15 18.9H9C8.4 18.9 8 18.5 8 17.9V7.90002C8 7.30002 8.4 6.90002 9 6.90002H21C21.6 7.00002 22 7.4 22 8ZM19 11C19 10.4 18.6 10 18 10H12C11.4 10 11 10.4 11 11C11 11.6 11.4 12 12 12H18C18.6 12 19 11.6 19 11ZM17 15C17 14.4 16.6 14 16 14H12C11.4 14 11 14.4 11 15C11 15.6 11.4 16 12 16H16C16.6 16 17 15.6 17 15Z" fill="black"/>
                                </svg>
                                <!--end::Svg Icon-->
                            </span>
                            <!--end::Svg Icon-->
                        </span>

                            <span class="menu-title">Kundenverwaltung</span>
                        </a>
                    </div>

                <?php } ?>

            </div>

            <!--end::Menu-->
        </div>
        <!--end::Aside Menu-->
    </div>
    <!--end::Aside menu-->
    <!--begin::Footer-->
    <div class="aside-footer flex-column-auto py-5" id="kt_aside_footer">
        <a href="<?= env('URL') . 'dev/changelog/'; ?>" class="btn btn-custom btn-primary w-100">
            <span class="btn-label">
                <i class="fas fa-cog fa-spin"></i> Version: <?= env('VERSION_DATE'); ?>
            </span>
            <!--begin::Svg Icon | path: icons/duotune/general/gen005.svg-->
            <span class="svg-icon btn-icon svg-icon-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path opacity="0.3" d="M19 22H5C4.4 22 4 21.6 4 21V3C4 2.4 4.4 2 5 2H14L20 8V21C20 21.6 19.6 22 19 22ZM12.5 18C12.5 17.4 12.6 17.5 12 17.5H8.5C7.9 17.5 8 17.4 8 18C8 18.6 7.9 18.5 8.5 18.5L12 18C12.6 18 12.5 18.6 12.5 18ZM16.5 13C16.5 12.4 16.6 12.5 16 12.5H8.5C7.9 12.5 8 12.4 8 13C8 13.6 7.9 13.5 8.5 13.5H15.5C16.1 13.5 16.5 13.6 16.5 13ZM12.5 8C12.5 7.4 12.6 7.5 12 7.5H8C7.4 7.5 7.5 7.4 7.5 8C7.5 8.6 7.4 8.5 8 8.5H12C12.6 8.5 12.5 8.6 12.5 8Z" fill="black" />
                    <rect x="7" y="17" width="6" height="2" rx="1" fill="black" />
                    <rect x="7" y="12" width="10" height="2" rx="1" fill="black" />
                    <rect x="7" y="7" width="6" height="2" rx="1" fill="black" />
                    <path d="M15 8H20L14 2V7C14 7.6 14.4 8 15 8Z" fill="black" />
                </svg>
            </span>
            <!--end::Svg Icon-->
        </a>
    </div>
    <!--end::Footer-->
</div>
<!--end::Aside-->