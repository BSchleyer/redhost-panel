<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2022 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel
 *  * @file        sendAlert.php
 *  * @author      BjörnSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        16.8.2022
 *  * @time        23:10
 *
 */


/*
 * Sweetalert 2
 */

function sendSweetError($message, $title = 'Fehler')
{
    return '<script> Swal.fire( "' . $title . '", "' . $message . '", "error" ); </script>';
}

function sendSweetInfo($message, $title = 'Info')
{
    return '<script> Swal.fire( "' . $title . '", "' . $message . '", "info"); </script>';
}

function sendSweetSuccess($message, $title = 'Erfolgreich')
{
    return '<script> Swal.fire("' . $title . '", "' . $message . '", "success"); </script>';
}

function sendSweetWarning($message, $title = 'Erfolgreich')
{
    return '<script> Swal.fire( "' . $title . '", "' . $message . '", "warning"); </script>';
}