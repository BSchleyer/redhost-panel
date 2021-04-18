<?php
$mailContent = '
<h3>
Soeben wurde auf dein Ticket #'.$ticket_id.' geantwortet.
</h3>
<p>
    '.$_POST['message'].'
</p>
';
$mailSubject = 'Neues antwort auf dein Ticket - '.env('APP_DOMAIN');