<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2024 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel (master)
 *  * @file        send.php
 *  * @author      BSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        24.3.2024
 *  * @time        13:57
 *
 */

/*
 * toastr
 */

if (!function_exists('sendError')) {
    function sendError($message, $title = _ALERT_ERROR)
    {
        return '<script>toastr.options = { "closeButton": false,
        "debug": false,
        "newestOnTop": true,
        "progressBar": false,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut" };
toastr.error("' . $message . '", "' . $title . '");</script>';
    }
}

if (!function_exists('sendInfo')) {
    function sendInfo($message, $title = _ALERT_INFO)
    {
        return '<script>toastr.options = { 
    "closeButton": false,
        "debug": false,
        "newestOnTop": true,
        "progressBar": false,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
};
toastr.info("' . $message . '", "' . $title . '");</script>';
    }
}

if (!function_exists('sendSuccess')) {
    function sendSuccess($message, $title = _ALERT_SUCCESS)
    {
        return '<script>toastr.options = { "closeButton": false,
        "debug": false,
        "newestOnTop": true,
        "progressBar": false,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut" };
toastr.success("' . $message . '", "' . $title . '");</script>';
    }
}

if (!function_exists('sendWarning')) {
    function sendWarning($message, $title = _ALERT_WARNING)
    {
        return '<script>toastr.options = { "closeButton": false,
        "debug": false,
        "newestOnTop": true,
        "progressBar": false,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut" };
toastr.warning("' . $message . '", "' . $title . '");</script>';
    }
}

/*
 * Sweetalert 2
 */

if (!function_exists('sendSweetError')) {
    function sendSweetError($message, $title = _ALERT_ERROR)
    {
        return '<script> Swal.fire( "' . $title . '", "' . $message . '", "error" ); </script>';
    }
}

if (!function_exists('sendSweetInfo')) {
    function sendSweetInfo($message, $title = _ALERT_INFO)
    {
        return '<script> Swal.fire( "' . $title . '", "' . $message . '", "info"); </script>';
    }
}

if (!function_exists('sendSweetSuccess')) {
    function sendSweetSuccess($message, $title = _ALERT_SUCCESS)
    {
        return '<script> Swal.fire("' . $title . '", "' . $message . '", "success"); </script>';
    }
}

if (!function_exists('sendSweetWarning')) {
    function sendSweetWarning($message, $title = _ALERT_WARNING)
    {
        return '<script> Swal.fire( "' . $title . '", "' . $message . '", "warning"); </script>';
    }
}