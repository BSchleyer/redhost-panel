<?php
$mailContent = '
    <table class="content">

        <tr>
          <td>
            <h1>Hallo '.$_POST['username'].' 👋</h1>
            <p>Vielen Dank f&uuml;r das Erstellen eines Kontos auf '.$helper->siteName().'!<br>
            Es gibt noch einen weiteren Schritt, bevor du dieses verwenden kannst.<br>
            Du musst dein Konto aktivieren, indem du auf den folgenden Button klickst.</p>
          </td>
        </tr>

        <tr>
          <td>
            <a href="'.$helper->url().'activate/'.$verify_code.'" class="button">Account Aktivieren</a><br><br>
            <a class="link" href="'.$helper->url().'activate/'.$verify_code.'">'.$helper->url().'activate/'.$verify_code.'</a>
          </td>
        </tr>

    </table>';
$mailSubject = 'Benutzerkonto aktivieren - '.env('APP_DOMAIN');