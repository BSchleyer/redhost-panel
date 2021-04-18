<?php
$mailContent = '
<h3>
Verlängere deinen Server
</h3>
<p>
    Dein '.$product_name.' läuft in 3 Tagen ab.<br>
    Verlängere diesen wenn du ihn weiterhin benutzen möchtest.
</p>
';
$mailSubject = $product_name.' läuft bald ab - '.env('APP_DOMAIN');