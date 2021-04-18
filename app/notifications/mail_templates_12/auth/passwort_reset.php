<?php
$mailContent = '<div style="padding: 60px 70px; border-top: 1px solid rgba(0,0,0,0.05);">
            <h1 style="margin-top: 0px;">
               Hallo,
            </h1>
            <div style="color: #636363; font-size: 14px;">
               <p>
                  Sie haben anscheinend Ihr Passwort vergessen? <br>Kein Problem klicken Sie einfach auf folgenden Link um Ihr Passwort zur&uuml;cksetzen.
               </p>
               <p>
                  Sollten Sie kein Passwort-Reset angefordert haben ignoriere Sie diese E-Mail einfach der Link wird nach 12 Stunden ung&uuml;ltig.
               </p>
               
               <a href="'.$url.'passwort_reset/'.$verify_code.'">'.$url.'passwort_reset/'.$verify_code.'</a>
               
            </div>
            <a href="'.$url.'passwort_reset/'.$verify_code.'" style="padding: 8px 20px; background-color: #4B72FA; color: #fff; font-weight: bolder; font-size: 16px; display: inline-block; margin: 20px 0px; margin-right: 20px; text-decoration: none;">Passwort zur&uuml;cksetzen</a>
            <h4 style="margin-bottom: 10px;">
                Haben Sie eine Frage oder ben&ouml;tigen Sie Hilfe?
            </h4>
            <div style="color: #A5A5A5; font-size: 12px;">
               <p>
                 Erstelle eine <a href="'.$helper->url().'tickets" style="text-decoration: underline; color: #4B72FA;">Supportanfrage</a> oder schreiben uns eine Mail <a href="mailto:support@red-host.eu" style="text-decoration: underline; color: #4B72FA;">support@red-host.eu</a>
               </p>
            </div>
         </div>';
$mailSubject = 'Passwort vergessen';
$emailAltBody = 'Klicken Sie auf diesen Link um Ihr Passwort zu ändern '.$url.'passwort_reset/'.$verify_code;