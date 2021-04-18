<?php
$mailContent = '<div style="padding: 60px 70px; border-top: 1px solid rgba(0,0,0,0.05);">
            <h1 style="margin-top: 0px;">
               Hallo '.$_POST['username'].',
            </h1>
            <div style="color: #636363; font-size: 14px;">
               <p>
                  Vielen Dank f&uuml;r das Erstellen eines Kontos auf '.$helper->siteName().', es gibt noch einen weiteren Schritt, bevor du dieses verwenden kannst. Du musst dein Konto aktivieren, indem du auf den folgenden Link klickst.
               </p>
               <br>
               <a href="'.$helper->url().'activate/'.$verify_code.'">'.$helper->url().'activate/'.$verify_code.'</a>
            </div>
            <a href="'.$helper->url().'activate/'.$verify_code.'" style="padding: 8px 20px; background-color: #4B72FA; color: #fff; font-weight: bolder; font-size: 16px; display: inline-block; margin: 20px 0px; margin-right: 20px; text-decoration: none;">Account aktivieren</a>
            <h4 style="margin-bottom: 10px;">
                Hast du eine Frage oder ben&ouml;tigst du Hilfe?
            </h4>
            <div style="color: #A5A5A5; font-size: 12px;">
               <p>
                  Erstelle eine <a href="'.$helper->url().'tickets" style="text-decoration: underline; color: #4B72FA;">Supportanfrage</a> oder schreiben uns eine Mail <a href="mailto:support@red-host.eu" style="text-decoration: underline; color: #4B72FA;">support@red-host.eu</a>
               </p>
            </div>
         </div>';
$mailSubject = 'Benutzerkonto aktivieren - '.$siteDomain;