<?php
$mailContent = '
<h3>
Soeben wurde ein neues Ticket von '.$username.' eröffnet.
</h3>
<p>
    '.$_POST['message'].'
</p>
';
$mailSubject = 'Neues Ticket von einem Kunden - '.env('APP_DOMAIN');