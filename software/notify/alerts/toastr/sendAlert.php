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
 * toastr
 */

function sendError($message, $title = 'Fehler')
{
    return '<script>toastr.options = { "closeButton": false, "debug": false, "newestOnTop": true, "progressBar": false, "positionClass": "toastr-top-right", "preventDuplicates": false, "onclick": null, "showDuration": "300", "hideDuration": "1000", "timeOut": "5000", "extendedTimeOut": "1000", "showEasing": "swing", "hideEasing": "linear", "showMethod": "fadeIn", "hideMethod": "fadeOut" };
toastr.error("' . $message . '", "' . $title . '");</script>';
}

function sendInfo($message, $title = 'Info')
{
    return '<script>toastr.options = { "closeButton": false, "debug": false, "newestOnTop": true, "progressBar": false, "positionClass": "toastr-top-right", "preventDuplicates": false, "onclick": null, "showDuration": "300", "hideDuration": "1000", "timeOut": "5000", "extendedTimeOut": "1000", "showEasing": "swing", "hideEasing": "linear", "showMethod": "fadeIn", "hideMethod": "fadeOut" };
toastr.info("' . $message . '", "' . $title . '");</script>';
}

function sendSuccess($message, $title = 'Erfolgreich')
{
    return '<script>toastr.options = { "closeButton": false, "debug": false, "newestOnTop": true, "progressBar": false, "positionClass": "toastr-top-right", "preventDuplicates": false, "onclick": null, "showDuration": "300", "hideDuration": "1000", "timeOut": "5000", "extendedTimeOut": "1000", "showEasing": "swing", "hideEasing": "linear", "showMethod": "fadeIn", "hideMethod": "fadeOut" };
toastr.success("' . $message . '", "' . $title . '");</script>';
}

function sendWarning($message, $title = 'Erfolgreich')
{
    return '<script>toastr.options = { "closeButton": false, "debug": false, "newestOnTop": true, "progressBar": false, "positionClass": "toastr-top-right", "preventDuplicates": false, "onclick": null, "showDuration": "300", "hideDuration": "1000", "timeOut": "5000", "extendedTimeOut": "1000", "showEasing": "swing", "hideEasing": "linear", "showMethod": "fadeIn", "hideMethod": "fadeOut" };
toastr.warning("' . $message . '", "' . $title . '");</script>';
}
