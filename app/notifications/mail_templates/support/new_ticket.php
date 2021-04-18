<?php
$mailContent = '
    <table class="content">

        <tr>
          <td>
            <h1>Hallo '.$_POST['username'].' 👋</h1>
            <p>
            Soeben wurde ein neues Ticket von '.$username.' eröffnet.<br><br>
            '.$_POST['message'].'
            </p>
          </td>
        </tr>

    </table>';
$mailSubject = 'Neues Ticket von einem Kunden - '.env('APP_DOMAIN');