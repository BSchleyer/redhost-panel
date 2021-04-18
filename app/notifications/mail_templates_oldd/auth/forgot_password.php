<?php
$mailContent = '<div style="padding: 60px 70px; border-top: 1px solid rgba(0,0,0,0.05);">
            <h1 style="margin-top: 0px;">
               Hallo '.$user_name.',
            </h1>
            <div style="color: #636363; font-size: 14px;">
               <p>
                  Du hast anscheinend dein Passwort vergessen? Kein Problem klicke einfach auf den folgenden Link um dein Passwort zur&uuml;cksetzen.
               </p>
               <br>
               <a href="'.$helper->url().'passwort_reset/'.$verify_code.'">'.$helper->url().'activate/'.$verify_code.'</a>
            </div>
            <a href="'.$helper->url().'passwort_reset/'.$verify_code.'" style="padding: 8px 20px; background-color: #4B72FA; color: #fff; font-weight: bolder; font-size: 16px; display: inline-block; margin: 20px 0px; margin-right: 20px; text-decoration: none;">Passwort zur&uuml;cksetzen</a>
            <h4 style="margin-bottom: 10px;">
                Hast du eine Frage oder ben&ouml;tigst du Hilfe?
            </h4>
            <div style="color: #A5A5A5; font-size: 12px;">
               <p>
                  Erstelle eine <a href="'.$helper->url().'tickets" style="text-decoration: underline; color: #4B72FA;">Supportanfrage</a> oder schreiben uns eine Mail <a href="mailto:support@red-host.eu" style="text-decoration: underline; color: #4B72FA;">support@red-host.eu</a>
               </p>
            </div>
         </div>';
$mailSubject = 'Passwort vergessen - '.$siteDomain;