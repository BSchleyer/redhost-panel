<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMail($user_email, $user_name, $mailContent, $mailSubject, $emailAltBody = null){

    include BASE_PATH.'app/notifications/mail_templates/mail_style.php';

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = 'mx01.red-host.eu';
        $mail->SMTPSecure = 'tls';
        $mail->SMTPAutoTLS = true;
        $mail->Username = 'no-reply@red-host.eu';
        $mail->Password = '';
        $mail->Port = 587;

        $mail->setFrom('no-reply@red-host.eu', 'REDHost - Kundencenter');
        $mail->addAddress($user_email, $user_name);

        $mail->isHTML(true);
        $mail->Subject = $mailSubject;
        $mail->Body = $emailBody;
        $mail->AltBody = $emailAltBody;
        $mail->CharSet = 'utf-8';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return 'abc';
        return 'Message could not be sent. Mailer Error: '.$mail->ErrorInfo;
    }

}