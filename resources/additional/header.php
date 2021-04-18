<div id="kt_header" class="header header-fixed">
    <div class="container-fluid d-flex align-items-stretch justify-content-between">
        <div class="header-menu-wrapper header-menu-wrapper-left" id="kt_header_menu_wrapper">
            <div id="kt_header_menu" class="header-menu header-menu-mobile  header-menu-layout-default ">
                <ul class="menu-nav ">
                    <li class="menu-item menu-item-submenu menu-item-rel menu-item-active" data-menu-toggle="click" aria-haspopup="true">
                        <font size="3">
                            <b><?= $site->getWelcomeText(date('H')); ?></b>
                        </font>
                    </li>
                </ul>
            </div>
        </div>

        <div class="topbar">
            <div class="dropdown">

                <?php
                $SQL = $db -> prepare("SELECT * FROM `tickets` WHERE `user_id` = :user_id AND `state` = 'OPEN' AND `last_msg` = 'SUPPORT'");
                $SQL->execute(array(":user_id" => $userid));
                if ($SQL->rowCount() != 0) {
                ?>
				<div class="topbar-item" data-toggle="dropdown" data-offset="10px,0px" onclick="window.open('https://cp.red-host.eu/tickets');">
                    <a href="#" class="btn btn-icon btn-light-primary pulse pulse-primary mr-5">
                        <i class="flaticon2-bell-5"></i>
                        <span class="pulse-ring"></span>
                    </a>
                </div>
                <?php } ?>

                <div class="topbar-item" data-toggle="dropdown" data-offset="10px,0px" onclick="window.open('https://uptime.red-host.eu');">
                    <div class="btn btn-icon btn-clean btn-dropdown btn-lg mr-1 pulse pulse-primary">
                        <i class="fas fa-chart-bar"></i>
                        <span class="pulse-ring"></span>
                    </div>
                </div>
			
                <div class="topbar-item" data-toggle="dropdown" data-offset="10px,0px" onclick="window.open('https://twitter.com/REDHostEU');">
                    <div class="btn btn-icon btn-clean btn-dropdown btn-lg mr-1 pulse pulse-primary">
                        <i class="fab fa-twitter"></i>
                        <span class="pulse-ring"></span>
                    </div>
                </div>

                <div class="topbar-item" data-toggle="dropdown" data-offset="10px,0px" onclick="window.open('https://dc.red-host.eu');">
                    <div class="btn btn-icon btn-clean btn-dropdown btn-lg mr-1 pulse pulse-primary">
                        <i class="fab fa-discord"></i>
                        <span class="pulse-ring"></span>
                    </div>	
                </div>
            </div>

            <div class="topbar-item">
                <div class="btn btn-icon w-auto btn-clean d-flex align-items-center btn-lg px-2" id="kt_quick_user_toggle">
                    <span class="text-muted font-weight-bold font-size-base d-none d-md-inline mr-1">Hi,</span>
                    <span class="text-dark-50 font-weight-bolder font-size-base d-none d-md-inline mr-3"><?= $username; ?></span>
                    <span class="symbol symbol-35 symbol-light-success">
                        <?php if($datasavingmode == 0){ ?>
                            <img src="https://api.cookiemc.de/50/<?= $username; ?>.png?ssl=1">
                        <?php } ?>
                    </span>
                </div>
            </div>
        </div>

    </div>
</div>
