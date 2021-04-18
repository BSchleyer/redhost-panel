<?php
$mailContent = '
<h3>
Soeben hat '.$username.' auf das Ticket #'.$ticket_id.' geantwortet.
</h3>
<p>
    '.$_POST['message'].'
</p>
';
$mailSubject = 'Neues antwort auf ein Ticket - '.env('APP_DOMAIN');