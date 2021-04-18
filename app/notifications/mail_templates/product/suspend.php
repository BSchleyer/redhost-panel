<?php
$mailContent = '
    <table class="content">

        <tr>
          <td>
            <h1>Hallo '.$_POST['username'].' 👋</h1>
            <p>
            Dein '.$product_name.' ist soeben abgelaufen und wurde nun gesperrt.<br>
            Du hast 3 Tage Zeit diesen zu verlängern ansonsten wird dieser gelöscht.
            </p>
          </td>
        </tr>

    </table>';
$mailSubject = $product_name.' ist abgelaufen - '.env('APP_DOMAIN');