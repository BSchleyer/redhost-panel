<?php
$mailContent = '
    <table class="content">

        <tr>
          <td>
            <h1>Hallo '.$_POST['username'].' 👋</h1>
            <p>
            Dein '.$product_name.' läuft in 3 Tagen ab.<br>
            Verlängere diesen wenn du ihn weiterhin benutzen möchtest.
            </p>
          </td>
        </tr>

    </table>';
$mailSubject = $product_name.' läuft bald ab - '.env('APP_DOMAIN');