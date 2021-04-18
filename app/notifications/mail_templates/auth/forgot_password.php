<?php
$mailContent = '
    <table class="content">

        <tr>
          <td>
            <h1>Hallo '.$_POST['username'].' 👋</h1>
            <p>Sie haben anscheinend Ihr Passwort vergessen?<br>
            Kein Problem klicken Sie einfach auf den folgenden Button um Ihr Passwort zur&uuml;cksetzen.</p>
          </td>
        </tr>

        <tr>
          <td>
            <a href="'.$helper->url().'passwort_reset/'.$verify_code.'" class="button">Account Aktivieren</a><br><br>
            <a class="link" href="'.$helper->url().'passwort_reset/'.$verify_code.'">'.$helper->url().'passwort_reset/'.$verify_code.'</a>
          </td>
        </tr>

    </table>';
$mailSubject = 'Passwort vergessen - '.env('APP_DOMAIN');