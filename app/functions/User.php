<?php

$user = new User();

class User extends Controller
{

    public function exists($data)
    {
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `email` = :email OR `username` = :username");
        $SQL->execute(array(":email" => $data, ":username" => $data));
        if($SQL->rowCount() == 1){
            return true;
        } else {
            return false;
        }
    }

    public function getState($data)
    {
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `email` = :email OR `username` = :username");
        $SQL->execute(array(":email" => $data, ":username" => $data));
        $data = $SQL->fetch(PDO::FETCH_ASSOC);

        return $data['state'];
    }

    public function getRole($data)
    {
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `email` = :email OR `username` = :username");
        $SQL->execute(array(":email" => $data, ":username" => $data));
        $data = $SQL->fetch(PDO::FETCH_ASSOC);

        if($data['role'] == 'customer'){
            return 'Kunde';
        }

        if($data['role'] == 'support'){
            return 'Supporter';
        }

        if($data['role'] == 'admin'){
            return 'Administrator';
        }
    }

    public function verifyLogin($data, $password)
    {
        $SQL = self::db()->prepare('SELECT * FROM `users` WHERE `email` = :email OR `username` = :username');
        $SQL->execute(array(":email" => $data, ":username" => $data));
        $data = $SQL->fetch(PDO::FETCH_ASSOC);

        if(password_verify($password, $data['password'])) {
            return true;
        } else {
            return false;
        }
    }

    public function generateSessionToken($data)
    {
        $session_token = (new Helper)->generateRandomString(30);

        $SQL = self::db()->prepare("UPDATE `users` SET `session_token` = :session_token WHERE `email` = :email OR `username` = :username");
        $SQL->execute(array(":session_token" => $session_token, ":email" => $data, ":username" => $data));

        return $session_token;
    }

    public function create($username, $email, $password, $state = 'pending', $role = 'customer')
    {
        $cost = 10;
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);

        $db = self::db();
        $SQL = $db->prepare("INSERT INTO `users`(`username`, `email`, `password`, `state`, `role`) VALUES (?,?,?,?,?)");
        $SQL->execute(array($username, $email, $hash, $state, $role));
        return $db->lastInsertId();
    }

    public function loggedIn($session_token = null)
    {
        if(is_null($session_token)){
            return false;
        } else {
            return $this->sessionExists($session_token);
        }
    }

    public function sessionExists($session_token)
    {
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `session_token` = :session_token");
        $SQL->execute(array(":session_token" => $session_token));
        if($SQL->rowCount() == 1){
            return true;
        } else {
            return false;
        }
    }

    public function getDataBySession($session_token, $data)
    {
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `session_token` = :session_token");
        $SQL->execute(array(":session_token" => $session_token));
        $response = $SQL->fetch(PDO::FETCH_ASSOC);

        return $response[$data];
    }

    public function getDataById($id, $data)
    {
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `id` = :id");
        $SQL->execute(array(":id" => $id));
        $response = $SQL->fetch(PDO::FETCH_ASSOC);

        return $response[$data];
    }

    public function getDataByUsername($username, $data)
    {
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `username` = :username");
        $SQL->execute(array(":username" => $username));
        $response = $SQL->fetch(PDO::FETCH_ASSOC);

        return $response[$data];
    }

    public function getDataByEmail($email, $data)
    {
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `email` = :email");
        $SQL->execute(array(":email" => $email));
        $response = $SQL->fetch(PDO::FETCH_ASSOC);

        return $response[$data];
    }

    public function isInTeam($session_token)
    {
        $role = User::getDataBySession($session_token,'role');

        if($role == 'admin'){
            return true;
        } elseif($role == 'support'){
            return true;
        } else {
            return false;
        }
    }

    public function isAdmin($session_token)
    {
        $role = User::getDataBySession($session_token,'role');

        if($role == 'admin'){
            return true;
        } else {
            return false;
        }
    }

    public function getOS($user_agent)
    {
        $os_platform  = "Unbekannt";

        $os_array = array(
            // Windows 11 Versionen
            '/windows nt 11\.0/i'      =>  'Windows 11',
            '/windows nt 11\.1/i'      =>  'Windows 11 Home',
            '/windows nt 11\.2/i'      =>  'Windows 11 Pro',
            '/windows nt 11\.3/i'      =>  'Windows 11 Enterprise',
            '/windows nt 11\.4/i'      =>  'Windows 11 Education',
            '/windows nt 11\.5/i'      =>  'Windows 11 IoT Enterprise',
            '/windows nt 11\.6/i'      =>  'Windows 11 IoT Enterprise (Evaluation)',
            '/windows nt 11\.7/i'      =>  'Windows 11 IoT Enterprise (LTSB)',
            '/windows nt 11\.8/i'      =>  'Windows 11 IoT Enterprise (LTSC)',
            '/windows nt 11\.9/i'      =>  'Windows 11 IoT Core',

            // Windows 10 Versionen
            '/windows nt 10\.0/i'      =>  'Windows 10',
            '/windows nt 10\.1/i'      =>  'Windows 10 Home',
            '/windows nt 10\.2/i'      =>  'Windows 10 Pro',
            '/windows nt 10\.3/i'      =>  'Windows 10 Enterprise',
            '/windows nt 10\.4/i'      =>  'Windows 10 Education',
            '/windows nt 10\.5/i'      =>  'Windows 10 IoT Enterprise',
            '/windows nt 10\.6/i'      =>  'Windows 10 IoT Enterprise (Evaluation)',
            '/windows nt 10\.7/i'      =>  'Windows 10 IoT Enterprise (LTSB)',
            '/windows nt 10\.8/i'      =>  'Windows 10 IoT Enterprise (LTSC)',
            '/windows nt 10\.9/i'      =>  'Windows 10 IoT Core',
            '/windows nt 10\.10/i'     =>  'Windows 10 S',
            '/windows nt 10\.11/i'     =>  'Windows 10 (Version 20H2)',
            '/windows nt 10\.12/i'     =>  'Windows 10 (Version 21H1)',
            '/windows nt 10\.13/i'     =>  'Windows 10 (Version 21H2)',
            '/windows nt 10\.14/i'     =>  'Windows 10 (Version 22H1)',
            '/windows nt 10\.15/i'     =>  'Windows 10 (Version 22H2)',
            '/windows nt 10\.16/i'     =>  'Windows 10 (Version 23H1)',
            '/windows nt 10\.17/i'     =>  'Windows 10 (Version 23H2)',
            '/windows nt 10\.18/i'     =>  'Windows 10 (Version 24H1)',
            '/windows nt 10\.19/i'     =>  'Windows 10 (Version 24H2)',
            '/windows nt 10\.20/i'     =>  'Windows 10 (Version 25H1)',
            '/windows nt 10\.21/i'     =>  'Windows 10 (Version 25H2)',
            '/windows nt 10\.22/i'     =>  'Windows 10 (Version 26H1)',

            // Mac OS Versionen
            '/macintosh|mac os x 10_15/i'   =>  'Mac OS X 10.15 Catalina',
            '/macintosh|mac os x 10_14/i'   =>  'Mac OS X 10.14 Mojave',
            '/macintosh|mac os x 10_13/i'   =>  'Mac OS X 10.13 High Sierra',
            '/macintosh|mac os x 10_12/i'   =>  'Mac OS X 10.12 Sierra',
            '/macintosh|mac os x 10_11/i'   =>  'Mac OS X 10.11 El Capitan',
            '/macintosh|mac os x 10_10/i'   =>  'Mac OS X 10.10 Yosemite',
            '/macintosh|mac os x 10_9/i'    =>  'Mac OS X 10.9 Mavericks',
            '/macintosh|mac os x 10_8/i'    =>  'Mac OS X 10.8 Mountain Lion',
            '/macintosh|mac os x 10_7/i'    =>  'Mac OS X 10.7 Lion',
            '/macintosh|mac os x 10_6/i'    =>  'Mac OS X 10.6 Snow Leopard',
            '/macintosh|mac os x 10_5/i'    =>  'Mac OS X 10.5 Leopard',
            '/macintosh|mac os x 10_4/i'    =>  'Mac OS X 10.4 Tiger',
            '/macintosh|mac os x 10_3/i'    =>  'Mac OS X 10.3 Panther',
            '/macintosh|mac os x 10_2/i'    =>  'Mac OS X 10.2 Jaguar',
            '/macintosh|mac os x 10_1/i'    =>  'Mac OS X 10.1 Puma',
            '/macintosh|mac os x/i'         =>  'Mac OS X',

            // Linux Versionen
            '/ubuntu/i'          =>  'Ubuntu',
            '/debian/i'          =>  'Debian 9',
            '/debian\/10/i'      =>  'Debian 10',
            '/fedora/i'          =>  'Fedora',
            '/redhat/i'          =>  'Red Hat',
            '/redhat\/8/i'       =>  'Red Hat 8',
            '/redhat\/9/i'       =>  'Red Hat 9',
            '/redhat\/10/i'      =>  'Red Hat 10',
            '/centos/i'          =>  'CentOS',
            '/centos\/7/i'       =>  'CentOS 7',
            '/centos\/8/i'       =>  'CentOS 8',
            '/centos\/9/i'       =>  'CentOS 9',
            '/suse/i'            =>  'openSUSE',
            '/suse\/15/i'        =>  'openSUSE 15',
            '/suse\/16/i'        =>  'openSUSE 16',
            '/slackware/i'       =>  'Slackware',
            '/slackware\/14/i'   =>  'Slackware 14',
            '/slackware\/15/i'   =>  'Slackware 15',
            '/gentoo/i'          =>  'Gentoo',
            '/gentoo\/3/i'       =>  'Gentoo 3',
            '/linux mint/i'      =>  'Linux Mint',
            '/linux mint\/20/i'  =>  'Linux Mint 20',
            '/linux mint\/21/i'  =>  'Linux Mint 21',
            '/mageia/i'          =>  'Mageia',
            '/mageia\/8/i'       =>  'Mageia 8',
            '/elementary os/i'   =>  'Elementary OS',
            '/elementary os\/6/i'=>  'Elementary OS 6',
            '/zorin os/i'        =>  'Zorin OS',
            '/zorin os\/16/i'    =>  'Zorin OS 16',
            '/manjaro/i'         =>  'Manjaro',
            '/manjaro\/21/i'     =>  'Manjaro 21',
            '/arch/i'            =>  'Arch Linux',
            '/arch\/2021/i'      =>  'Arch Linux 2021',
            '/alpine/i'          =>  'Alpine Linux',
            '/alpine\/3/i'       =>  'Alpine Linux 3',
            '/void/i'            =>  'Void Linux',
            '/void\/2022/i'      =>  'Void Linux 2022',
            '/deepin/i'          =>  'Deepin',
            '/deepin\/20/i'      =>  'Deepin 20',
            '/mx linux/i'        =>  'MX Linux',
            '/mx linux\/21/i'    =>  'MX Linux 21',
            '/kali linux/i'      =>  'Kali Linux',
            '/kali linux\/2021/i'=>  'Kali Linux 2021',
            '/xubuntu/i'         =>  'Xubuntu',
            '/xubuntu\/20/i'     =>  'Xubuntu 20',
            '/lubuntu/i'         =>  'Lubuntu',
            '/lubuntu\/20/i'     =>  'Lubuntu 20',
            '/kubuntu/i'         =>  'Kubuntu',
            '/kubuntu\/20/i'     =>  'Kubuntu 20',
            '/pop!_os/i'         =>  'Pop!_OS',
            '/pop!_os\/21/i'     =>  'Pop!_OS 21',
            '/solus/i'           =>  'Solus',
            '/solus\/4/i'        =>  'Solus 4',
            '/freebsd/i'         =>  'FreeBSD',
            '/freebsd\/12/i'     =>  'FreeBSD 12',
            '/openbsd/i'         =>  'OpenBSD',
            '/openbsd\/7/i'      =>  'OpenBSD 7',
            '/netbsd/i'          =>  'NetBSD',
            '/netbsd\/10/i'      =>  'NetBSD 10',
            '/sabayon/i'         =>  'Sabayon',
            '/sabayon\/20/i'     =>  'Sabayon 20',
            '/tux/i'              =>  'Tux',
            '/tux\/2/i'           =>  'Tux 2',
            '/linux/i'            =>  'Linux',
            '/linux\/5/i'         =>  'Linux 5',

            // Mobile Betriebssysteme
            '/android 1/i'               =>  'Android 1',
            '/android 2/i'               =>  'Android 2',
            '/android 3/i'               =>  'Android 3',
            '/android 4/i'               =>  'Android 4',
            '/android 5/i'               =>  'Android 5',
            '/android 6/i'               =>  'Android 6',
            '/android 7/i'               =>  'Android 7',
            '/android 8/i'               =>  'Android 8',
            '/android 9/i'               =>  'Android 9',
            '/android 10/i'              =>  'Android 10',
            '/android 11/i'              =>  'Android 11',
            '/android 12/i'              =>  'Android 12',
            '/iphone OS 1/i'             =>  'iOS 1',
            '/iphone OS 2/i'             =>  'iOS 2',
            '/iphone OS 3/i'             =>  'iOS 3',
            '/iphone OS 4/i'             =>  'iOS 4',
            '/iphone OS 5/i'             =>  'iOS 5',
            '/iphone OS 6/i'             =>  'iOS 6',
            '/iphone OS 7/i'             =>  'iOS 7',
            '/iphone OS 8/i'             =>  'iOS 8',
            '/iphone OS 9/i'             =>  'iOS 9',
            '/iphone OS 10/i'            =>  'iOS 10',
            '/iphone OS 11/i'            =>  'iOS 11',
            '/iphone OS 12/i'            =>  'iOS 12',
            '/iphone OS 13/i'            =>  'iOS 13',
            '/iphone OS 14/i'            =>  'iOS 14',
            '/iphone OS 15/i'            =>  'iOS 15',
            '/ipad OS 1/i'               =>  'iPad OS 1',
            '/ipad OS 2/i'               =>  'iPad OS 2',
            '/ipad OS 3/i'               =>  'iPad OS 3',
            '/ipad OS 4/i'               =>  'iPad OS 4',
            '/ipad OS 5/i'               =>  'iPad OS 5',
            '/ipad OS 6/i'               =>  'iPad OS 6',
            '/ipad OS 7/i'               =>  'iPad OS 7',
            '/ipad OS 8/i'               =>  'iPad OS 8',
            '/ipad OS 9/i'               =>  'iPad OS 9',
            '/ipad OS 10/i'              =>  'iPad OS 10',
            '/ipad OS 11/i'              =>  'iPad OS 11',
            '/ipad OS 12/i'              =>  'iPad OS 12',
            '/ipad OS 13/i'              =>  'iPad OS 13',
            '/ipad OS 14/i'              =>  'iPad OS 14',
            '/ipad OS 15/i'              =>  'iPad OS 15',
            '/blackberry 1/i'            =>  'BlackBerry 1',
            '/blackberry 2/i'            =>  'BlackBerry 2',
            '/blackberry 3/i'            =>  'BlackBerry 3',
            '/blackberry 4/i'            =>  'BlackBerry 4',
            '/blackberry 5/i'            =>  'BlackBerry 5',
            '/blackberry 6/i'            =>  'BlackBerry 6',
            '/blackberry 7/i'            =>  'BlackBerry 7',
            '/blackberry 10/i'           =>  'BlackBerry 10',
            '/webos 1/i'                 =>  'webOS 1',
            '/webos 2/i'                 =>  'webOS 2',
            '/webos 3/i'                 =>  'webOS 3',
            '/webos 4/i'                 =>  'webOS 4',
            '/webos 5/i'                 =>  'webOS 5',

            // Spielkonsolen
            '/playstation 3/i'           =>  'PlayStation 3',
            '/playstation 4/i'           =>  'PlayStation 4',
            '/playstation 5/i'           =>  'PlayStation 5',
            '/xbox/i'                    =>  'Xbox',
            '/xbox 360/i'                =>  'Xbox 360',
            '/xbox one/i'                =>  'Xbox One',
            '/xbox series x/i'           =>  'Xbox Series X',

            // Tragbare Geräte
            '/psp/i'                  =>  'PSP',
            '/nintendo 3ds/i'         =>  'Nintendo 3DS',
            '/nintendo ds/i'          =>  'Nintendo DS',
            '/nintendo switch/i'         =>  'Nintendo Switch',
            '/smartwatch/i'           =>  'Smartwatch',
            '/apple watch/i'       =>  'Apple Watch',
            '/fitbit/i'            =>  'Fitbit',
            '/garmin/i'            =>  'Garmin',

            // Smart-Home-Geräte
            '/alexa/i'                =>  'Amazon Alexa',
            '/google home/i'          =>  'Google Home',
            '/amazon echo/i'          =>  'Amazon Echo',
            '/apple homepod/i'        =>  'Apple HomePod',

            // Andere Betriebssysteme
            '/beos/i'                 =>  'BeOS',
            '/haiku/i'                =>  'Haiku',
            '/plan 9/i'               =>  'Plan 9 from Bell Labs',
            '/reactos/i'              =>  'ReactOS',
            '/syllable/i'             =>  'Syllable',
            '/unix/i'                 =>  'Unix',
            '/openvms/i'              =>  'OpenVMS',
            '/os\/2/i'                =>  'OS/2',
            '/symbian/i'              =>  'Symbian',
            '/z\/os/i'                =>  'z/OS',
            '/nintendo/i'             =>  'Nintendo OS',
            '/amigaos/i'              =>  'AmigaOS',
            '/morphos/i'              =>  'MorphOS',
            '/risc os/i'              =>  'RISC OS',
            '/palm os/i'              =>  'Palm OS',
            '/webtv/i'                =>  'WebTV OS',
            '/hurd/i'                 =>  'GNU Hurd',
            '/vru/i'                  =>  'VRU OS',
            '/midnight/i'             =>  'Midnight OS',
            '/honeycomb/i'            =>  'Honeycomb OS',
            '/newton/i'               =>  'Newton OS',
            '/microsoft/i'            =>  'Microsoft Research OS',
            '/riscix/i'               =>  'RISCiX OS',
            '/morphix/i'              =>  'Morphix OS',
            '/steam os/i'             =>  'Steam OS',
            '/vxworks/i'              =>  'VxWorks',
            '/qnx/i'                  =>  'QNX',
            '/tizen/i'                =>  'Tizen',
            '/raspbian/i'             =>  'Raspbian',
            '/oracle solaris/i'       =>  'Oracle Solaris',
            '/tails/i'                =>  'Tails',
            '/remix os/i'             =>  'Remix OS',
            '/fuchsia/i'              =>  'Fuchsia',
            '/harmonyos/i'            =>  'HarmonyOS',
            '/solaris/i'              =>  'Solaris',
            '/aix/i'                  =>  'AIX',
            '/hp-ux/i'                =>  'HP-UX',
            '/ibm i/i'                =>  'IBM i (AS/400)',
            '/windows phone/i'        =>  'Windows Phone',
            '/windows mobile/i'       =>  'Windows Mobile',
            '/embedded/i'             =>  'Embedded OS',
            '/other-os/i'             =>  'Anderes Betriebssystem',
        );

        foreach ($os_array as $regex => $value)
            if (preg_match($regex, $user_agent))
                $os_platform = $value;

        return $os_platform;
    }

    public function getBrowser($user_agent): string
    {
        $browser = "Unbekannt";

        $browser_array = array(
            '/msie/i'      => 'Internet Explorer',
            '/trident/i'   => 'Internet Explorer',
            '/edge/i'      => 'Microsoft Edge',
            '/firefox/i'   => 'Firefox',
            '/safari/i'    => 'Safari',
            '/chrome/i'    => 'Chrome',
            '/opera/i'     => 'Opera',
            '/netscape/i'  => 'Netscape',
            '/maxthon/i'   => 'Maxthon',
            '/konqueror/i' => 'Konqueror',
            '/mobile/i'    => 'Handy-Browser',
            '/brave/i'     => 'Brave',
            '/epiphany/i'  => 'Epiphany',
            '/ucbrowser/i' => 'UC Browser',
            '/yabrowser/i' => 'Yandex Browser',
            '/waterfox/i'  => 'Waterfox',
            '/sleipnir/i'  => 'Sleipnir',
            '/lunascape/i' => 'Lunascape',
            '/avant/i'     => 'Avant Browser',
            '/webpositive/i'=> 'WebPositive',
            '/aol/i'       => 'AOL Browser',
            '/k-meleon/i'  => 'K-Meleon',
            '/seamonkey/i' => 'SeaMonkey',
            '/galeon/i'    => 'Galeon',
            '/iron/i'      => 'SRWare Iron',
            '/comodo/i'    => 'Comodo Dragon',
            '/rockmelt/i'  => 'RockMelt',
            '/slimboat/i'  => 'SlimBrowser',
            '/flock/i'     => 'Flock',
            '/netsurf/i'   => 'NetSurf',
            '/epic/i'      => 'Epic Browser',
            '/dolphin/i'   => 'Dolphin',
            '/qupzilla/i'  => 'QupZilla',
            '/silk/i'      => 'Amazon Silk',
            '/otter/i'     => 'Otter Browser',
            '/gnome\-web/i'=> 'Epiphany (GNOME Web)',
            '/tizen/i'     => 'Tizen Browser',
            '/slimjet/i'   => 'Slimjet',
            '/cyberfox/i'  => 'Cyberfox',
            '/icedragon/i' => 'Comodo IceDragon',
            '/vivaldi/i'   => 'Vivaldi',
            '/coc_coc/i'   => 'Coc Coc',
            '/sogou/i'     => 'Sogou Browser',
            '/qihoo/i'     => '360 Secure Browser',
            '/huohou/i'    => 'Huohou Browser',
            '/miui/i'      => 'Mi Browser',
            '/huawei/i'    => 'Huawei Browser',
            '/oneplus/i'   => 'OnePlus Browser',
            '/meizu/i'     => 'Meizu Browser',
            '/nokia/i'     => 'Nokia Browser',
            '/lenovo/i'    => 'Lenovo Browser',
            '/zte/i'       => 'ZTE Browser',
            '/leeco/i'     => 'LeEco Browser',
            '/realme/i'    => 'Realme Browser',
            '/tecent/i'    => 'Tencent QQ Browser',
            '/baidu/i'     => 'Baidu Browser',
            '/vivo/i'      => 'Vivo Browser',
            '/mxios/i'     => 'Maxthon for iOS',
            '/baidubox/i'  => 'Baidu Box App',
            '/qqlive/i'    => 'Tencent QQ Live App',
            '/weibo/i'     => 'Sina Weibo App',
            '/wechat/i'    => 'WeChat App',
            '/alipay/i'    => 'Alipay Mini Program',
            '/taobao/i'    => 'Taobao Mini Program',
            '/palemoon/i'  => 'Pale Moon',
            '/odo/i'       => 'Opera Neon',
            '/midori/i'    => 'Midori',
            '/rekonq/i'    => 'Rekonq',
            '/uzbl/i'      => 'Uzbl',
            '/whale/i'     => 'Naver Whale',
            '/yandexsearch/i' => 'Yandex Search App',
            '/qqbrowser/i' => 'QQ Browser',
            '/baidubrowser/i' => 'Baidu Browser',
            '/liebaofast/i' => 'Liebao Browser (LBBrowser)',
            '/liebaomini/i' => 'Liebao Mini Browser',
            '/duckduckgo/i' => 'DuckDuckGo Browser',
            '/bb10/i'      => 'BlackBerry 10 Browser',
            '/edgeios/i'   => 'Microsoft Edge (iOS)',
            '/edgeandroid/i' => 'Microsoft Edge (Android)',
            '/focus/i'     => 'Firefox Focus',
            '/focusios/i'  => 'Firefox Focus (iOS)',
            '/adobeair/i'  => 'Adobe AIR Application',
            '/kindle/i'    => 'Amazon Kindle',
            '/iridium/i'   => 'Iridium Browser',
            '/vewd/i'      => 'Vewd (formerly Opera TV)',
            '/viera/i'     => 'Panasonic Viera TV Browser',
            '/snapchat/i'  => 'Snapchat App',
            '/puffin/i'    => 'Puffin Browser',
            '/webos/i'     => 'webOS Browser',
            '/maemo/i'     => 'Maemo Browser (Nokia N900)',
            '/meego/i'     => 'MeeGo Browser',
            '/wii/i'       => 'Nintendo Wii Browser',
            '/psp/i'       => 'PlayStation Portable Browser',
            '/camino/i'    => 'Camino',
            '/kazehakase/i'=> 'Kazehakase',
            '/gobrowser/i' => 'GO Browser',
            '/tor/i'       => 'TOR (The Onion Router)',
            // Browser, die von Regierungsbehörden oder militärischen Organisationen verwendet werden
            '/usgcb/i'     => 'U.S. Government Web Browser',
            '/qubes/i'     => 'Qubes OS Secure Browser',
            '/swb/i'       => 'Secure Web Browser (SWB)',
            '/iceweasel/i' => 'IceWeasel',
            '/gsb/i'       => 'Government Secure Browser (GSB)'
        );

        foreach ($browser_array as $regex => $value) {
            if (preg_match($regex, $user_agent)) {
                $browser = $value;
            }
        }

        return $browser;
    }

    public function getIP()
    {
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
            if (array_key_exists($key, $_SERVER) === true){
                foreach (explode(',', $_SERVER[$key]) as $ip){
                    $ip = trim($ip); // just to be safe

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                        return $ip;
                    }
                }
            }
        }
    }

    public function getAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    public function addMoney($money, $user_id){
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `id` = :id");
        $SQL->execute(array(':id' => $user_id));
        $userData = $SQL -> fetch(PDO::FETCH_ASSOC);

        $newUserMoney = $userData['amount'] + $money;
        $updateUserMoney = self::db()->prepare("UPDATE `users` SET `amount`=:newUserMoney WHERE `id` = :user_id");
        $updateUserMoney->execute(array(":newUserMoney" => $newUserMoney, ":user_id" => $user_id));
    }

    public function removeMoney($price, $user_id){
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `id` = :id");
        $SQL->execute(array(':id' => $user_id));
        $userData = $SQL -> fetch(PDO::FETCH_ASSOC);

        $newUserMoney = $userData['amount'] - $price;
        $updateUserMoney = self::db()->prepare("UPDATE `users` SET `amount`=:newUserMoney WHERE `id` = :user_id");
        $updateUserMoney->execute(array(":newUserMoney" => $newUserMoney, ":user_id" => $user_id));
    }

    public function addTransaction($user_id, $amount, $desc){
        $SQL = self::db()->prepare("INSERT INTO `user_transactions`(`user_id`, `amount`, `desc`) VALUES (?,?,?)");
        $SQL->execute(array($user_id, $amount, $desc));
    }

    public function serviceCount($user_id)
    {
        $count = 0;

        $SQL = self::db()->prepare('SELECT * FROM `teamspeaks` WHERE `user_id` = :user_id AND `deleted_at` IS NULL');
        $SQL->execute(array(":user_id" => $user_id));
        $count = $count + $SQL->rowCount();

        $SQL = self::db()->prepare('SELECT * FROM `vm_servers` WHERE `user_id` = :user_id AND `deleted_at` IS NULL');
        $SQL->execute(array(":user_id" => $user_id));
        $count = $count + $SQL->rowCount();

        $SQL = self::db()->prepare('SELECT * FROM `webspace` WHERE `user_id` = :user_id AND `deleted_at` IS NULL');
        $SQL->execute(array(":user_id" => $user_id));
        $count = $count + $SQL->rowCount();

        return $count;
    }
    
    public function activeCount($user_id)
    {
        $count = 0;

        $SQL = self::db()->prepare("SELECT * FROM `teamspeaks` WHERE `user_id` = :user_id AND `deleted_at` IS NULL AND `state` = 'ACTIVE'");
        $SQL->execute(array(":user_id" => $user_id));
        $count = $count + $SQL->rowCount();

        $SQL = self::db()->prepare("SELECT * FROM `vm_servers` WHERE `user_id` = :user_id AND `deleted_at` IS NULL' AND `state` = 'active'");
        $SQL->execute(array(":user_id" => $user_id));
        $count = $count + $SQL->rowCount();

        $SQL = self::db()->prepare("SELECT * FROM `webspace` WHERE `user_id` = :user_id AND `deleted_at` IS NULL AND `state` = 'active'");
        $SQL->execute(array(":user_id" => $user_id));
        $count = $count + $SQL->rowCount();

        return $count;
    }

    public function teamspeakCount($user_id)
    {
        $SQL = self::db()->prepare('SELECT * FROM `teamspeaks` WHERE `user_id` = :user_id AND `deleted_at` IS NULL');
        $SQL->execute(array(":user_id" => $user_id));
        return $SQL->rowCount();
    }

    public function domainCount($user_id)
    {
        $SQL = self::db()->prepare('SELECT * FROM `domains` WHERE `user_id` = :user_id AND `deleted_at` IS NULL');
        $SQL->execute(array(":user_id" => $user_id));
        return $SQL->rowCount();
    }

    public function webspaceCount($user_id)
    {
        $SQL = self::db()->prepare('SELECT * FROM `webspace` WHERE `user_id` = :user_id AND `deleted_at` IS NULL');
        $SQL->execute(array(":user_id" => $user_id));
        return $SQL->rowCount();
    }

    public function getOpenTickets($user_id)
    {
        $SQL = self::db()->prepare('SELECT * FROM `tickets` WHERE `user_id` = :user_id AND `state` = :state');
        $SQL->execute(array(":user_id" => $user_id, ":state" => 'OPEN'));
        return $SQL->rowCount();
    }

    public function getMontlyCosts($user_id)
    {
        $costs = 0.00;

        $SQL = self::db()->prepare("SELECT * FROM `teamspeaks` WHERE `user_id` = :user_id AND `deleted_at` IS NULL");
        $SQL->execute(array(":user_id" => $user_id));
        if ($SQL->rowCount() != 0) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {
                $costs = $costs + ($row['slots'] * Site::getProductPrice('TEAMSPEAK'));
            }
        }

        $SQL = self::db()->prepare("SELECT * FROM `vm_servers` WHERE `user_id` = :user_id AND `deleted_at` IS NULL");
        $SQL->execute(array(":user_id" => $user_id));
        if ($SQL->rowCount() != 0) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {
                $costs = $costs + $row['price'];
            }
        }

        $SQL = self::db()->prepare("SELECT * FROM `webspace` WHERE `user_id` = :user_id AND `deleted_at` IS NULL");
        $SQL->execute(array(":user_id" => $user_id));
        if ($SQL->rowCount() != 0) {
            while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {
                $costs = $costs + $row['price'];
            }
        }

        return number_format($costs,2);
    }

    public function getDataByAffiliateId($id, $data)
    {
        $SQL = self::db()->prepare("SELECT * FROM `users` WHERE `affiliate_id` = :affiliate_id");
        $SQL->execute(array(":affiliate_id" => $id));
        $response = $SQL->fetch(PDO::FETCH_ASSOC);

        return $response[$data];
    }

    public function renewSupportPin($userid, $token = null)
    {
        if(is_null($token)){
            $token = (new Helper)->generateRandomString(5,'1234567890');
        }

        $SQL = self::db()->prepare("UPDATE `users` SET `s_pin` = :s_pin WHERE `id` = :id");
        $SQL->execute(array(":id" => $userid, ":s_pin" => $token));

        return $token;
    }

    public function validateSpin($s_pin)
    {
        $SQL = self::db()->prepare('SELECT * FROM `users` WHERE `s_pin` = :s_pin');
        $SQL->execute(array(":s_pin" => $s_pin,));
        if ($SQL->rowCount() == 1) {
            $userData = $SQL -> fetch(PDO::FETCH_ASSOC);
            return $userData['id'];
        } else {
            return 0;
        }
    }

    public function logLogin($user_id, $user_addr, $user_agent)
    {
        $SQL = self::db()->prepare("INSERT INTO `login_logs`(`user_id`, `user_addr`, `user_agent`) VALUES (?,?,?)");
        $SQL->execute(array($user_id, $user_addr, $user_agent));
    }

}
