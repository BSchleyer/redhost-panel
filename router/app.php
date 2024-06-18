<?php

/*
 * page manager
 */
$resources = BASE_PATH.'resources/';
$sites = $resources.'sites/';
$auth = $resources.'auth/';
$customer = $resources.'customer/';
$team = $resources.'team/';
$page = $helper->protect($_GET['page']);

if(isset($_GET['page'])) {
    switch ($page) {

        default: include($sites . "404.php");  break;

        //auth
        case "auth_login": include($auth . "login.php");  break;
        case "auth_register": include($auth . "register.php"); break;
        case "auth_logout": setcookie('session_token', null, time(),'/'); header('Location: '.$helper->url().'login'); break;
        case "auth_activate": include($auth . "activate.php"); break;
        case "auth_forgot_password": include($auth . "forgot_password.php"); break;

        //index
        case "dashboard": include($customer . "dashboard.php");  break;
        case "profile": include($customer . "profile.php");  break;

        //accounting
        case "accounting_charge": include($customer . "accounting/charge.php");  break;
        case "accounting_donate": include($customer . "accounting/donate.php");  break;
        case "accounting_transactions": include($customer . "accounting/transactions.php");  break;
        case "accounting_invoice": include($customer . "accounting/invoice.php");  break;

        //support
        case "tickets": include($customer . "support/tickets.php");  break;
        case "ticket": include($customer . "support/ticket.php");  break;

        //webspace
        case "order_webspace": include($customer . "webspace/order.php");  break;
        case "manage_webspaces": include($customer . "webspace/index.php");  break;
        case "manage_webspace": include($customer . "webspace/manage.php");  break;
        case "renew_webspace": include($customer . "webspace/renew.php");  break;

        //teamspeak
        case "order_teamspeak": include($customer . "teamspeak/order.php");  break;
        case "manage_teamspeaks": include($customer . "teamspeak/index.php");  break;
        case "manage_teamspeak": include($customer . "teamspeak/manage.php");  break;
        case "reconfigure_teamspeak": include($customer . "teamspeak/reconfigure.php");  break;
        case "renew_teamspeak": include($customer . "teamspeak/renew.php");  break;

        //LXC Server
        case "order_vserver": include($customer . "vserver/order.php");  break;
        case "order_vserver_custom": include($customer . "vserver/order_custom.php");  break;
        case "order_vserver_packs": include($customer . "vserver/order_packs.php");  break;
        case "order_vserver_game": include($customer . "vserver/order_game.php");  break;
        case "manage_vservers": include($customer . "vserver/index.php");  break;
        case "manage_vserver": include($customer . "vserver/manage.php");  break;
        case "reconfigure_vserver": include($customer . "vserver/reconfigure.php");  break;
        case "renew_vserver": include($customer . "vserver/renew.php");  break;

        //KVM Rootserver
        case "order_rootserver": include($customer . "rootserver/order.php");  break;
        case "manage_rootservers": include($customer . "rootserver/index.php");  break;
        case "manage_rootserver": include($customer . "rootserver/manage.php");  break;
        case "renew_rootserver": include($customer . "rootserver/renew.php");  break;

        //gameserver
        case "order_gameserver": include($customer . "gameserver/order.php");  break;
        case "manage_gameservers": include($customer . "gameserver/index.php");  break;
        case "manage_gameserver": include($customer . "gameserver/manage.php");  break;
        case "renew_gameserver": include($customer . "gameserver/renew.php");  break;
        case "reconfigure_gameserver": include($customer . "gameserver/reconfigure.php");  break;

        //system
        case "worker_queue": include(BASE_PATH . "app/crone/work_queue.php");  break;
        case "runtime_queue": include(BASE_PATH . "app/crone/runtime_queue.php");  break;
        case "get_load": include(BASE_PATH . "app/ajax/get_load.php");  break;
        case "traffic_queue": include(BASE_PATH . "app/crone/traffic_queue.php");  break;
        case "dsgvo": include($resources . "customer/dsgvo.php");  break;

        //debug
        case "DEBUG": include(BASE_PATH . "DEBUG/index.php");  break;

        //
        case "impressum": include($sites."impressum.php");  break;
        case "datenschutz": include($sites."datenschutz.php");  break;
        case "agb": include($sites."agb.php");  break;

        //api
        case "api_v1_discord": include(BASE_PATH."resources/api/index_discord.php");  break;

        //team
        case "team_tickets": include($team."support/tickets.php");  break;
        case "team_ticket": include($team."support/ticket.php");  break;
        case "team_users": include($team."user/users.php");  break;
        case "team_user": include($team."user/user.php");  break;
        case "team_spin_login": include($team."user/s_pin_login.php");  break;
        case "team_login_back": include($team."login_back.php");  break;
        case "team_transactions": include($team."transactions.php");  break;
        case "team_system": include($team."system.php");  break;
        case "team_ipam": include($team."ip_manager.php");  break;
        case "team_orders": include($team."orders/list.php");  break;
        case "team_order": include($team."orders/manage.php");  break;
        case "team_code_list": include($team."codes/list.php");  break;

    }

    if(strpos($currPage,'system_') !== false || strpos($currPage,'_hidelayout') !== false) {} else {
        include BASE_PATH.'/resources/additional/footer.php';
    }

} else {
    die('please enable .htaccess on your server');
}