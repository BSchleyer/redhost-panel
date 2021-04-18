<?php
$mailContent = '
<h3>
Produkt abgelaufen
</h3>
<p>
    Dein '.$product_name.' ist soeben abgelaufen und wurde nun gesperrt.<br>
    Du hast 3 Tage Zeit diesen zu verlängern ansonsten wird dieser gelöscht.
</p>
';
$mailSubject = $product_name.' ist abgelaufen - '.env('APP_DOMAIN');