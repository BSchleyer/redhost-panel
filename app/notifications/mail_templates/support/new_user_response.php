<?php
$mailContent = '
    <table class="content">

        <tr>
          <td>
            <h1>Hallo '.$_POST['username'].' 👋</h1>
            <p>
            Soeben hat '.$username.' auf das Ticket #'.$ticket_id.' geantwortet.<br><br>
            '.$_POST['message'].'
            </p>
          </td>
        </tr>

    </table>';
$mailSubject = 'Neues antwort auf ein Ticket - '.env('APP_DOMAIN');