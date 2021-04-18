<!--begin::Aside-->
<div class="aside aside-left aside-fixed d-flex flex-column flex-row-auto" id="kt_aside">
    <!--begin::Brand-->
    <div class="brand flex-column-auto" id="kt_brand">
        <!--begin::Logo-->
        <a href="<?= env('URL'); ?>" class="brand-logo">
            <?php if($datasavingmode == 0){ ?>

                <!-- DARKMODE -->
            <?php if($user->getDataById($userid,'darkmode')){ ?>
                <img alt="Logo" src="https://i.imgur.com/LC26Ag9.png" width="160">
            <?php } else { ?>
                <img alt="Logo" src="https://i.imgur.com/NVXW1Dc.png" width="160">
            <?php } ?>

            <?php } else { ?>
                <?= env('APP_DOMAIN'); ?>
            <?php } ?>
        </a>

        <button class="brand-toggle btn btn-sm px-0" id="kt_aside_toggle">
            <span class="svg-icon svg-icon svg-icon-xl">
                <!--begin::Svg Icon | path:assets/media/svg/icons/Navigation/Angle-double-left.svg-->
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <polygon points="0 0 24 0 24 24 0 24" />
                        <path d="M5.29288961,6.70710318 C4.90236532,6.31657888 4.90236532,5.68341391 5.29288961,5.29288961 C5.68341391,4.90236532 6.31657888,4.90236532 6.70710318,5.29288961 L12.7071032,11.2928896 C13.0856821,11.6714686 13.0989277,12.281055 12.7371505,12.675721 L7.23715054,18.675721 C6.86395813,19.08284 6.23139076,19.1103429 5.82427177,18.7371505 C5.41715278,18.3639581 5.38964985,17.7313908 5.76284226,17.3242718 L10.6158586,12.0300721 L5.29288961,6.70710318 Z" fill="#000000" fill-rule="nonzero" transform="translate(8.999997, 11.999999) scale(-1, 1) translate(-8.999997, -11.999999)" />
                        <path d="M10.7071009,15.7071068 C10.3165766,16.0976311 9.68341162,16.0976311 9.29288733,15.7071068 C8.90236304,15.3165825 8.90236304,14.6834175 9.29288733,14.2928932 L15.2928873,8.29289322 C15.6714663,7.91431428 16.2810527,7.90106866 16.6757187,8.26284586 L22.6757187,13.7628459 C23.0828377,14.1360383 23.1103407,14.7686056 22.7371482,15.1757246 C22.3639558,15.5828436 21.7313885,15.6103465 21.3242695,15.2371541 L16.0300699,10.3841378 L10.7071009,15.7071068 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(15.999997, 11.999999) scale(-1, 1) rotate(-270.000000) translate(-15.999997, -11.999999)" />
                    </g>
                </svg>
                <!--end::Svg Icon-->
            </span>
        </button>
        <!--end::Toolbar-->
    </div>
    <!--end::Brand-->
    <!--begin::Aside Menu-->
    <div class="aside-menu-wrapper flex-column-fluid" id="kt_aside_menu_wrapper">
        <!--begin::Menu Container-->
        <div id="kt_aside_menu" class="aside-menu my-4" data-menu-vertical="1" data-menu-scroll="1" data-menu-dropdown-timeout="500">
            <!--begin::Menu Nav-->
            <ul class="menu-nav">

                <li class="menu-item" aria-haspopup="true"> <!-- menu-item-active -->
                    <a target="_blank" href="https://red-host.eu" class="menu-link">
                        <span class="svg-icon menu-icon">
                          <!--begin::Svg Icon | path:C:\wamp64\www\keenthemes\themes\metronic\theme\html\demo1\dist/../src/media/svg/icons\Layout\Layout-top-panel-3.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path d="M3,4 L20,4 C20.5522847,4 21,4.44771525 21,5 L21,7 C21,7.55228475 20.5522847,8 20,8 L3,8 C2.44771525,8 2,7.55228475 2,7 L2,5 C2,4.44771525 2.44771525,4 3,4 Z M3,10 L13,10 C13.5522847,10 14,10.4477153 14,11 L14,19 C14,19.5522847 13.5522847,20 13,20 L3,20 C2.44771525,20 2,19.5522847 2,19 L2,11 C2,10.4477153 2.44771525,10 3,10 Z" fill="#000000" />
                                    <rect fill="#000000" opacity="0.3" x="16" y="10" width="5" height="10" rx="1" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-text">Startseite</span>
                    </a>
                </li>

                <li class="menu-item" aria-haspopup="true"> <!-- menu-item-active -->
                    <a href="<?= env('URL'); ?>dashboard" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Design/Layers.svg-->
                            <svg width="24px" height="24px" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <g id="Stockholm-icons-/-Shopping-/-Chart-line1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect id="bound" x="0" y="0" width="24" height="24"></rect>
                                    <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" id="Path-95" fill="#000000" fill-rule="nonzero"></path>
                                    <path d="M8.7295372,14.6839411 C8.35180695,15.0868534 7.71897114,15.1072675 7.31605887,14.7295372 C6.9131466,14.3518069 6.89273254,13.7189711 7.2704628,13.3160589 L11.0204628,9.31605887 C11.3857725,8.92639521 11.9928179,8.89260288 12.3991193,9.23931335 L15.358855,11.7649545 L19.2151172,6.88035571 C19.5573373,6.44687693 20.1861655,6.37289714 20.6196443,6.71511723 C21.0531231,7.05733733 21.1271029,7.68616551 20.7848828,8.11964429 L16.2848828,13.8196443 C15.9333973,14.2648593 15.2823707,14.3288915 14.8508807,13.9606866 L11.8268294,11.3801628 L8.7295372,14.6839411 Z" id="Path-97" fill="#000000" fill-rule="nonzero" opacity="0.3"></path>
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-text">Dashboard</span>
                    </a>
                </li>

                <li class="menu-section">
                    <h4 class="menu-text">Produkte</h4>
                    <i class="menu-icon ki ki-bold-more-hor icon-md"></i>
                </li>
                <li class="menu-item menu-item-submenu" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:;" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Design/Bucket.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path d="M5,2 L19,2 C20.1045695,2 21,2.8954305 21,4 L21,6 C21,7.1045695 20.1045695,8 19,8 L5,8 C3.8954305,8 3,7.1045695 3,6 L3,4 C3,2.8954305 3.8954305,2 5,2 Z M11,4 C10.4477153,4 10,4.44771525 10,5 C10,5.55228475 10.4477153,6 11,6 L16,6 C16.5522847,6 17,5.55228475 17,5 C17,4.44771525 16.5522847,4 16,4 L11,4 Z M7,6 C7.55228475,6 8,5.55228475 8,5 C8,4.44771525 7.55228475,4 7,4 C6.44771525,4 6,4.44771525 6,5 C6,5.55228475 6.44771525,6 7,6 Z" fill="#000000" opacity="0.3" />
                                    <path d="M5,9 L19,9 C20.1045695,9 21,9.8954305 21,11 L21,13 C21,14.1045695 20.1045695,15 19,15 L5,15 C3.8954305,15 3,14.1045695 3,13 L3,11 C3,9.8954305 3.8954305,9 5,9 Z M11,11 C10.4477153,11 10,11.4477153 10,12 C10,12.5522847 10.4477153,13 11,13 L16,13 C16.5522847,13 17,12.5522847 17,12 C17,11.4477153 16.5522847,11 16,11 L11,11 Z M7,13 C7.55228475,13 8,12.5522847 8,12 C8,11.4477153 7.55228475,11 7,11 C6.44771525,11 6,11.4477153 6,12 C6,12.5522847 6.44771525,13 7,13 Z" fill="#000000" />
                                    <path d="M5,16 L19,16 C20.1045695,16 21,16.8954305 21,18 L21,20 C21,21.1045695 20.1045695,22 19,22 L5,22 C3.8954305,22 3,21.1045695 3,20 L3,18 C3,16.8954305 3.8954305,16 5,16 Z M11,18 C10.4477153,18 10,18.4477153 10,19 C10,19.5522847 10.4477153,20 11,20 L16,20 C16.5522847,20 17,19.5522847 17,19 C17,18.4477153 16.5522847,18 16,18 L11,18 Z M7,20 C7.55228475,20 8,19.5522847 8,19 C8,18.4477153 7.55228475,18 7,18 C6.44771525,18 6,18.4477153 6,19 C6,19.5522847 6.44771525,20 7,20 Z" fill="#000000" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-text">vServer</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item menu-item-parent" aria-haspopup="true">
                                <span class="menu-link">
                                    <span class="menu-text">vServer</span>
                                </span>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>order/vserver" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Bestellen</span>
                                </a>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>manage/vserver" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Verwalten</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="menu-item menu-item-submenu" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:;" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Design/Bucket.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path d="M5,2 L19,2 C20.1045695,2 21,2.8954305 21,4 L21,6 C21,7.1045695 20.1045695,8 19,8 L5,8 C3.8954305,8 3,7.1045695 3,6 L3,4 C3,2.8954305 3.8954305,2 5,2 Z M11,4 C10.4477153,4 10,4.44771525 10,5 C10,5.55228475 10.4477153,6 11,6 L16,6 C16.5522847,6 17,5.55228475 17,5 C17,4.44771525 16.5522847,4 16,4 L11,4 Z M7,6 C7.55228475,6 8,5.55228475 8,5 C8,4.44771525 7.55228475,4 7,4 C6.44771525,4 6,4.44771525 6,5 C6,5.55228475 6.44771525,6 7,6 Z" fill="#000000" opacity="0.3" />
                                    <path d="M5,9 L19,9 C20.1045695,9 21,9.8954305 21,11 L21,13 C21,14.1045695 20.1045695,15 19,15 L5,15 C3.8954305,15 3,14.1045695 3,13 L3,11 C3,9.8954305 3.8954305,9 5,9 Z M11,11 C10.4477153,11 10,11.4477153 10,12 C10,12.5522847 10.4477153,13 11,13 L16,13 C16.5522847,13 17,12.5522847 17,12 C17,11.4477153 16.5522847,11 16,11 L11,11 Z M7,13 C7.55228475,13 8,12.5522847 8,12 C8,11.4477153 7.55228475,11 7,11 C6.44771525,11 6,11.4477153 6,12 C6,12.5522847 6.44771525,13 7,13 Z" fill="#000000" />
                                    <path d="M5,16 L19,16 C20.1045695,16 21,16.8954305 21,18 L21,20 C21,21.1045695 20.1045695,22 19,22 L5,22 C3.8954305,22 3,21.1045695 3,20 L3,18 C3,16.8954305 3.8954305,16 5,16 Z M11,18 C10.4477153,18 10,18.4477153 10,19 C10,19.5522847 10.4477153,20 11,20 L16,20 C16.5522847,20 17,19.5522847 17,19 C17,18.4477153 16.5522847,18 16,18 L11,18 Z M7,20 C7.55228475,20 8,19.5522847 8,19 C8,18.4477153 7.55228475,18 7,18 C6.44771525,18 6,18.4477153 6,19 C6,19.5522847 6.44771525,20 7,20 Z" fill="#000000" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-text">Rootserver</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item menu-item-parent" aria-haspopup="true">
                                <span class="menu-link">
                                    <span class="menu-text">Rootserver</span>
                                </span>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>order/rootserver" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Bestellen</span>
                                </a>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>manage/rootserver" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Verwalten</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="menu-item menu-item-submenu" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:;" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-10-29-133027/theme/html/demo1/dist/../src/media/svg/icons/Weather/Cloudy.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <polygon points="0 0 24 0 24 24 0 24"/>
                                <path d="M7.74714567,15.0425758 C6.09410362,13.9740356 5,12.1147886 5,10 C5,6.6862915 7.6862915,4 11,4 C13.7957591,4 16.1449096,5.91215918 16.8109738,8.5 L19.25,8.5 C21.3210678,8.5 23,10.1789322 23,12.25 C23,14.3210678 21.3210678,16 19.25,16 L10.25,16 C9.28817895,16 8.41093178,15.6378962 7.74714567,15.0425758 Z" fill="#000000" opacity="0.3"/>
                                <path d="M3.74714567,19.0425758 C2.09410362,17.9740356 1,16.1147886 1,14 C1,10.6862915 3.6862915,8 7,8 C9.79575914,8 12.1449096,9.91215918 12.8109738,12.5 L15.25,12.5 C17.3210678,12.5 19,14.1789322 19,16.25 C19,18.3210678 17.3210678,20 15.25,20 L6.25,20 C5.28817895,20 4.41093178,19.6378962 3.74714567,19.0425758 Z" fill="#000000"/>
                            </g>
                        </svg><!--end::Svg Icon--></span>
                        <span class="menu-text">Webspace</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item menu-item-parent" aria-haspopup="true">
                                <span class="menu-link">
                                    <span class="menu-text">Webspace</span>
                                </span>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>order/webspace" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Bestellen</span>
                                </a>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>manage/webspace" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Verwalten</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="menu-item menu-item-submenu" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:;" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Design/Bucket.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path d="M12.9975507,17.929461 C12.9991745,17.9527631 13,17.9762852 13,18 L13,21 C13,21.5522847 12.5522847,22 12,22 C11.4477153,22 11,21.5522847 11,21 L11,18 C11,17.9762852 11.0008255,17.9527631 11.0024493,17.929461 C7.60896116,17.4452857 5,14.5273206 5,11 L7,11 C7,13.7614237 9.23857625,16 12,16 C14.7614237,16 17,13.7614237 17,11 L19,11 C19,14.5273206 16.3910388,17.4452857 12.9975507,17.929461 Z" fill="#000000" fill-rule="nonzero" />
                                    <rect fill="#000000" opacity="0.3" transform="translate(12.000000, 8.000000) rotate(-360.000000) translate(-12.000000, -8.000000)" x="9" y="2" width="6" height="12" rx="3" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-text">Teamspeak</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item menu-item-parent" aria-haspopup="true">
                                <span class="menu-link">
                                    <span class="menu-text">Teamspeak</span>
                                </span>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>order/teamspeak" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Bestellen</span>
                                </a>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>manage/teamspeak" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Verwalten</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="menu-item menu-item-submenu" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:;" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                        <span class="svg-icon svg-icon-primary svg-icon-2x"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2020-12-10-081610/theme/html/demo1/dist/../src/media/svg/icons/Devices/Gamepad2.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"/>
                                <path d="M12.9486833,8.31622777 L11.0513167,7.68377223 C11.8160243,5.38964935 13.0426772,3.95855428 14.7574644,3.5298575 C15.650287,3.30665184 17,1.86951059 17,1 L19,1 C19,2.79715607 17.0163797,5.02668149 15.2425356,5.4701425 C14.2906561,5.70811238 13.517309,6.61035065 12.9486833,8.31622777 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"/>
                                <path d="M7.81798367,7 L16.2025685,7 C17.5586976,6.72948613 18.9494633,7.42723712 19.526457,8.72046451 L22.9501217,16.3939916 C23.4665806,17.5515411 23.1998005,18.9087734 22.2836331,19.7847233 L21.8392372,20.2096113 C21.8188115,20.2291404 21.7980738,20.2483405 21.7770321,20.2672042 C20.6904893,21.2412841 19.0200246,21.1501149 18.0459447,20.0635721 L15.2994684,17 L8.86456314,17 L6.11808685,20.0635721 C5.14400696,21.1501149 3.47354224,21.2412841 2.38699945,20.2672042 C2.36595778,20.2483405 2.34522009,20.2291404 2.3247944,20.2096113 L1.85770343,19.7630245 C0.952705301,18.8977536 0.680264004,17.5614241 1.17430192,16.4109277 L4.46146538,8.75590867 C5.02845054,7.43553556 6.44099515,6.71763226 7.81798367,7 Z M8,14 C9.1045695,14 10,13.1045695 10,12 C10,10.8954305 9.1045695,10 8,10 C6.8954305,10 6,10.8954305 6,12 C6,13.1045695 6.8954305,14 8,14 Z M17,12 C17.5522847,12 18,11.5522847 18,11 C18,10.4477153 17.5522847,10 17,10 C16.4477153,10 16,10.4477153 16,11 C16,11.5522847 16.4477153,12 17,12 Z M15,14 C15.5522847,14 16,13.5522847 16,13 C16,12.4477153 15.5522847,12 15,12 C14.4477153,12 14,12.4477153 14,13 C14,13.5522847 14.4477153,14 15,14 Z" fill="#000000"/>
                            </g>
                        </svg><!--end::Svg Icon--></span>
                        </span>
                        <span class="menu-text">Gameserver</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item menu-item-parent" aria-haspopup="true">
                                <span class="menu-link">
                                    <span class="menu-text">Gameserver</span>
                                </span>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>order/gameserver" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Bestellen</span>
                                </a>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>manage/gameserver" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Verwalten</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li class="menu-section">
                    <h4 class="menu-text">Account</h4>
                    <i class="menu-icon ki ki-bold-more-hor icon-md"></i>
                </li>
                <li class="menu-item" aria-haspopup="true"> <!-- menu-item-active -->
                    <a href="<?= env('URL'); ?>profile" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Design/Layers.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24"/>
                                    <path d="M18,2 L20,2 C21.6568542,2 23,3.34314575 23,5 L23,19 C23,20.6568542 21.6568542,22 20,22 L18,22 L18,2 Z" fill="#000000" opacity="0.3"/>
                                    <path d="M5,2 L17,2 C18.6568542,2 20,3.34314575 20,5 L20,19 C20,20.6568542 18.6568542,22 17,22 L5,22 C4.44771525,22 4,21.5522847 4,21 L4,3 C4,2.44771525 4.44771525,2 5,2 Z M12,11 C13.1045695,11 14,10.1045695 14,9 C14,7.8954305 13.1045695,7 12,7 C10.8954305,7 10,7.8954305 10,9 C10,10.1045695 10.8954305,11 12,11 Z M7.00036205,16.4995035 C6.98863236,16.6619875 7.26484009,17 7.4041679,17 C11.463736,17 14.5228466,17 16.5815,17 C16.9988413,17 17.0053266,16.6221713 16.9988413,16.5 C16.8360465,13.4332455 14.6506758,12 11.9907452,12 C9.36772908,12 7.21569918,13.5165724 7.00036205,16.4995035 Z" fill="#000000"/>
                                </g>
                            </svg><!--end::Svg Icon--></span>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-text">Mein Profil</span>
                    </a>
                </li>

                <li class="menu-item menu-item-submenu" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:;" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Design/Bucket.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path d="M5,9 L19,9 C20.1045695,9 21,9.8954305 21,11 L21,20 C21,21.1045695 20.1045695,22 19,22 L5,22 C3.8954305,22 3,21.1045695 3,20 L3,11 C3,9.8954305 3.8954305,9 5,9 Z M18.1444251,10.8396467 L12,14.1481833 L5.85557487,10.8396467 C5.4908718,10.6432681 5.03602525,10.7797221 4.83964668,11.1444251 C4.6432681,11.5091282 4.77972206,11.9639747 5.14442513,12.1603533 L11.6444251,15.6603533 C11.8664074,15.7798822 12.1335926,15.7798822 12.3555749,15.6603533 L18.8555749,12.1603533 C19.2202779,11.9639747 19.3567319,11.5091282 19.1603533,11.1444251 C18.9639747,10.7797221 18.5091282,10.6432681 18.1444251,10.8396467 Z" fill="#000000" />
                                    <path d="M11.1288761,0.733697713 L11.1288761,2.69017121 L9.12120481,2.69017121 C8.84506244,2.69017121 8.62120481,2.91402884 8.62120481,3.19017121 L8.62120481,4.21346991 C8.62120481,4.48961229 8.84506244,4.71346991 9.12120481,4.71346991 L11.1288761,4.71346991 L11.1288761,6.66994341 C11.1288761,6.94608579 11.3527337,7.16994341 11.6288761,7.16994341 C11.7471877,7.16994341 11.8616664,7.12798964 11.951961,7.05154023 L15.4576222,4.08341738 C15.6683723,3.90498251 15.6945689,3.58948575 15.5161341,3.37873564 C15.4982803,3.35764848 15.4787093,3.33807751 15.4576222,3.32022374 L11.951961,0.352100892 C11.7412109,0.173666017 11.4257142,0.199862688 11.2472793,0.410612793 C11.1708299,0.500907473 11.1288761,0.615386087 11.1288761,0.733697713 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(11.959697, 3.661508) rotate(-270.000000) translate(-11.959697, -3.661508)" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-text">Support</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item menu-item-parent" aria-haspopup="true">
                                <span class="menu-link">
                                    <span class="menu-text">Support</span>
                                </span>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>tickets" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Ticket Support</span>
                                </a>
                            </li>
                            <!--li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>accounting" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Transaktionen</span>
                                </a>
                            </li-->
                        </ul>
                    </div>
                </li>

                <li class="menu-item menu-item-submenu" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:;" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Design/Bucket.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path d="M5,9 L19,9 C20.1045695,9 21,9.8954305 21,11 L21,20 C21,21.1045695 20.1045695,22 19,22 L5,22 C3.8954305,22 3,21.1045695 3,20 L3,11 C3,9.8954305 3.8954305,9 5,9 Z M18.1444251,10.8396467 L12,14.1481833 L5.85557487,10.8396467 C5.4908718,10.6432681 5.03602525,10.7797221 4.83964668,11.1444251 C4.6432681,11.5091282 4.77972206,11.9639747 5.14442513,12.1603533 L11.6444251,15.6603533 C11.8664074,15.7798822 12.1335926,15.7798822 12.3555749,15.6603533 L18.8555749,12.1603533 C19.2202779,11.9639747 19.3567319,11.5091282 19.1603533,11.1444251 C18.9639747,10.7797221 18.5091282,10.6432681 18.1444251,10.8396467 Z" fill="#000000" />
                                    <path d="M11.1288761,0.733697713 L11.1288761,2.69017121 L9.12120481,2.69017121 C8.84506244,2.69017121 8.62120481,2.91402884 8.62120481,3.19017121 L8.62120481,4.21346991 C8.62120481,4.48961229 8.84506244,4.71346991 9.12120481,4.71346991 L11.1288761,4.71346991 L11.1288761,6.66994341 C11.1288761,6.94608579 11.3527337,7.16994341 11.6288761,7.16994341 C11.7471877,7.16994341 11.8616664,7.12798964 11.951961,7.05154023 L15.4576222,4.08341738 C15.6683723,3.90498251 15.6945689,3.58948575 15.5161341,3.37873564 C15.4982803,3.35764848 15.4787093,3.33807751 15.4576222,3.32022374 L11.951961,0.352100892 C11.7412109,0.173666017 11.4257142,0.199862688 11.2472793,0.410612793 C11.1708299,0.500907473 11.1288761,0.615386087 11.1288761,0.733697713 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(11.959697, 3.661508) rotate(-270.000000) translate(-11.959697, -3.661508)" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-text">Buchhaltung</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item menu-item-parent" aria-haspopup="true">
                                <span class="menu-link">
                                    <span class="menu-text">Buchhaltung</span>
                                </span>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>accounting/charge" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Guthaben aufladen</span>
                                </a>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>accounting/transactions" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Transaktionen</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <?php if(isset($_COOKIE['old_session_token'])){ ?>
                <li class="menu-section">
                    <h4 class="menu-text">Administration</h4>
                    <i class="menu-icon ki ki-bold-more-hor icon-md"></i>
                </li>
                <li class="menu-item" aria-haspopup="true"> <!-- menu-item-active -->
                    <a href="<?= env('URL'); ?>team/login_back" class="menu-link">
                    <span class="svg-icon menu-icon">
                        <!--begin::Svg Icon | path:assets/media/svg/icons/Design/Layers.svg-->
                        <svg width="24px" height="24px" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                          <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24" />
                                <path d="M11.1669899,4.49941818 L2.82535718,19.5143571 C2.557144,19.9971408 2.7310878,20.6059441 3.21387153,20.8741573 C3.36242953,20.9566895 3.52957021,21 3.69951446,21 L21.2169432,21 C21.7692279,21 22.2169432,20.5522847 22.2169432,20 C22.2169432,19.8159952 22.1661743,19.6355579 22.070225,19.47855 L12.894429,4.4636111 C12.6064401,3.99235656 11.9909517,3.84379039 11.5196972,4.13177928 C11.3723594,4.22181902 11.2508468,4.34847583 11.1669899,4.49941818 Z" fill="#000000" opacity="0.3" />
                                <rect fill="#000000" x="11" y="9" width="2" height="7" rx="1" />
                                <rect fill="#000000" x="11" y="17" width="2" height="2" rx="1" />
                            </g>
                        </svg>
                        <!--end::Svg Icon-->
                    </span>
                        <span class="menu-text">Zurück zum ACP</span>
                    </a>
                </li>
                <?php } ?>

                <?php if($user->isInTeam($_COOKIE['session_token'])){ ?>
                    <li class="menu-section">
                        <h4 class="menu-text">Administration</h4>
                        <i class="menu-icon ki ki-bold-more-hor icon-md"></i>
                    </li>
                    <li class="menu-item menu-item-submenu" aria-haspopup="true" data-menu-toggle="hover">
                        <a href="javascript:;" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Design/Bucket.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path d="M11.1669899,4.49941818 L2.82535718,19.5143571 C2.557144,19.9971408 2.7310878,20.6059441 3.21387153,20.8741573 C3.36242953,20.9566895 3.52957021,21 3.69951446,21 L21.2169432,21 C21.7692279,21 22.2169432,20.5522847 22.2169432,20 C22.2169432,19.8159952 22.1661743,19.6355579 22.070225,19.47855 L12.894429,4.4636111 C12.6064401,3.99235656 11.9909517,3.84379039 11.5196972,4.13177928 C11.3723594,4.22181902 11.2508468,4.34847583 11.1669899,4.49941818 Z" fill="#000000" opacity="0.3" />
                                    <rect fill="#000000" x="11" y="9" width="2" height="7" rx="1" />
                                    <rect fill="#000000" x="11" y="17" width="2" height="2" rx="1" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                            <span class="menu-text">Support</span>
                            <i class="menu-arrow"></i>
                        </a>
                        <div class="menu-submenu">
                            <i class="menu-arrow"></i>
                            <ul class="menu-subnav">
                                <li class="menu-item menu-item-parent" aria-haspopup="true">
                                    <span class="menu-link">
                                        <span class="menu-text">Support</span>
                                    </span>
                                </li>
                                <li class="menu-item" aria-haspopup="true">
                                    <a href="<?= env('URL'); ?>team/spin_login" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">S-Pin Login</span>
                                    </a>
                                </li>
                                <li class="menu-item" aria-haspopup="true">
                                    <a href="<?= env('URL'); ?>team/tickets" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Tickets</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                <?php } ?>

                <?php if($user->isAdmin($_COOKIE['session_token'])){ ?>
                <li class="menu-item menu-item-submenu" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:;" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Design/Bucket.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path d="M11.1669899,4.49941818 L2.82535718,19.5143571 C2.557144,19.9971408 2.7310878,20.6059441 3.21387153,20.8741573 C3.36242953,20.9566895 3.52957021,21 3.69951446,21 L21.2169432,21 C21.7692279,21 22.2169432,20.5522847 22.2169432,20 C22.2169432,19.8159952 22.1661743,19.6355579 22.070225,19.47855 L12.894429,4.4636111 C12.6064401,3.99235656 11.9909517,3.84379039 11.5196972,4.13177928 C11.3723594,4.22181902 11.2508468,4.34847583 11.1669899,4.49941818 Z" fill="#000000" opacity="0.3" />
                                    <rect fill="#000000" x="11" y="9" width="2" height="7" rx="1" />
                                    <rect fill="#000000" x="11" y="17" width="2" height="2" rx="1" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <span class="menu-text">Administration</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item menu-item-parent" aria-haspopup="true">
                                <span class="menu-link">
                                    <span class="menu-text">Administration</span>
                                </span>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>team/users" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Kundenverwaltung</span>
                                </a>
                            </li>
                            <!--
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>team/transactions" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Zahlungsverwaltung</span>
                                </a>
                            </li>
                            -->
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>team/system" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Systemverwaltung</span>
                                </a>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>team/ipam" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">IPAM</span>
                                </a>
                            </li>
                            <li class="menu-item" aria-haspopup="true">
                                <a href="<?= env('URL'); ?>team/orders" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Offene Bestellungen</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <?php } ?>

            </ul>
            <!--end::Menu Nav-->
        </div>
        <!--end::Menu Container-->
    </div>
    <!--end::Aside Menu-->
</div>