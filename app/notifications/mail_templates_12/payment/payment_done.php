<?php
$mailContent = '
<h3>
Viele Dank für deine Guthabenaufladung!
</h3>
<p>
    Wir haben deine Zahlung über '.$money.'€ erhalten und soeben verbucht.
</p>
';
$mailSubject = 'Wir haben deine Zahlung erhalten - '.env('APP_DOMAIN');


<?php
$mailContent = '
    <table class="content">

        <tr>
          <td>
            <h1>Guthabenaufladung 💵</h1>
            <p>Wir haben deine Zahlung über '.$money.'€ erhalten und soeben verbucht.</p>
          </td>
        </tr>

    </table>;
$mailSubject = 'Wir haben deine Zahlung erhalten - '.env('APP_DOMAIN');