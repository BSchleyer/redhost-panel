<?php
$mailContent = '
    <table class="content">

        <tr>
          <td>
            <h1>Hallo '.$_POST['username'].' 👋</h1>
            <p>
            Vielen Dank für deine Bestellung.<br>
            Dein Produkt wird gleich eingerichtet und steht dann für dich bereit.
            </p>
          </td>
        </tr>

    </table>';
$mailSubject = 'Vielen Dank für deine Bestellung - '.env('APP_DOMAIN');