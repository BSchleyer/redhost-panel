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