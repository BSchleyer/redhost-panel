<?php
$mailContent = '
    <table class="content">

        <tr>
          <td>
            <h1>Hallo '.$_POST['username'].' 👋</h1>
            <p>
            Soeben wurde auf das Ticket #'.$ticket_id.' geantwortet.<br><br>
            '.$_POST['message'].'
            </p>
          </td>
        </tr>

    </table>';
$mailSubject = 'Neues Antwort auf das Ticket #'.$ticket_id.' - '.env('APP_DOMAIN');