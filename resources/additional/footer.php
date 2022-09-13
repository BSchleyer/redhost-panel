<?php
/*
 * *************************************************************************
 *  * Copyright 2006-2022 (C) Björn Schleyer, Schleyer-EDV - All rights reserved.
 *  *
 *  * Made in Gelsenkirchen with-&hearts; by Björn Schleyer
 *  *
 *  * @project     RED-Host Panel
 *  * @file        footer.php
 *  * @author      BjörnSchleyer
 *  * @site        www.schleyer-edv.de
 *  * @date        16.8.2022
 *  * @time        22:43
 *
 */

?>

<div class="footer py-4 d-flex flex-lg-column" id="kt_footer">
    <!--begin::Container-->
    <div class="container-fluid d-flex flex-column flex-md-row align-items-center justify-content-between">
        <!--begin::Menu-->
        <ul class="menu menu-gray-600 menu-hover-primary fw-bold order-1">
            <li class="menu-item">
                <a target="_blank" href="<?= $helper->url(); ?>legal/conditions/" class="menu-link px-2">AGB</a>
            </li>
            <li class="menu-item">
                <a target="_blank" href="<?= $helper->url(); ?>legal/withdrawal/" class="menu-link px-2">Widerrufsrecht</a>
            </li>
            <li class="menu-item">
                <a target="_blank" href="<?= $helper->url(); ?>legal/privacy/" class="menu-link px-2">Datenschutz</a>
            </li>
            <li class="menu-item">
                <a target="_blank" href="<?= $helper->url(); ?>legal/imprint/" class="menu-link px-2">Impressum</a>
            </li>
        </ul>

        <div class="text-dark order-2 order-md-1">
            <span class="text-muted fw-bold me-1">
                Copyright &copy; 2013-<script type="text/javascript">
                    document.write(new Date().getFullYear());
                </script>
                <?= env('APP_COPYRIGHT_NAME'); ?>
            </span>
        </div>
        <!--end::Menu-->
    </div>
    <!--end::Container-->
</div>
<!--end::Footer-->
</div>
<!--end::Wrapper-->
</div>
<!--end::Page-->
</div>


<script>var hostUrl = "<?= env('URL'); ?>";</script>

<script>var KTAppSettings = { "breakpoints": { "sm": 576, "md": 768, "lg": 992, "xl": 1200, "xxl": 1400 }, "colors": { "theme": { "base": { "white": "#ffffff", "primary": "#3699FF", "secondary": "#E5EAEE", "success": "#1BC5BD", "info": "#8950FC", "warning": "#FFA800", "danger": "#F64E60", "light": "#E4E6EF", "dark": "#181C32" }, "light": { "white": "#ffffff", "primary": "#E1F0FF", "secondary": "#EBEDF3", "success": "#C9F7F5", "info": "#EEE5FF", "warning": "#FFF4DE", "danger": "#FFE2E5", "light": "#F3F6F9", "dark": "#D6D6E0" }, "inverse": { "white": "#ffffff", "primary": "#ffffff", "secondary": "#3F4254", "success": "#ffffff", "info": "#ffffff", "warning": "#ffffff", "danger": "#ffffff", "light": "#464E5F", "dark": "#ffffff" } }, "gray": { "gray-100": "#F3F6F9", "gray-200": "#EBEDF3", "gray-300": "#E4E6EF", "gray-400": "#D1D3E0", "gray-500": "#B5B5C3", "gray-600": "#7E8299", "gray-700": "#5E6278", "gray-800": "#3F4254", "gray-900": "#181C32" } }, "font-family": "Poppins" };</script>

<script src="<?= $helper->styleUrl(); ?>js/scripts.bundle.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.4/clipboard.min.js"></script>
<script>
    var clipboard = new ClipboardJS('.copy-btn');
    clipboard.on('success', function(e){
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toastr-top-right",
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
        toastr.success(
            "Anfrage in der Zwischenablage.",    //Message
            "Erfolgreich"       //Titel
        );
    });
</script>

<script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $('#dataTableLoad').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/German.json"
            }
        });
    } );
</script>

<script>

    /*$(document).ready(function() {
        $('#dataTableLoadTwo').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/German.json"
            }
            paging: true;
            responsive: true;
            "order": {
                "desc"
            }
        });
    } );*/

    function humanFileSize(bytes, si) {
        var thresh = si ? 1000 : 1024;
        if(Math.abs(bytes) < thresh) {
            return bytes + ' B';
        }
        var units = si
            ? ['kB','MB','GB','TB','PB','EB','ZB','YB']
            : ['KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB'];
        var u = -1;
        do {
            bytes /= thresh;
            ++u;
        } while(Math.abs(bytes) >= thresh && u < units.length - 1);
        return bytes.toFixed(2)+' '+units[u];
    }

    function number_format (number, decimals, dec_point, thousands_sep) {
        // Strip all characters but numerical ones.
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number,
            prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
            sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
            dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
            s = '',
            toFixedFix = function (n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
        // Fix for IE parseFloat(0.55).toFixed(0) = 0;
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
    }

</script>

<?php

if(isset($_SESSION['success_sweet_msg']) && !empty($_SESSION['success_sweet_msg'])){
    echo sendSweetSuccess($_SESSION['success_sweet_msg']);
    $_SESSION['success_sweet_msg'] = '';
    unset($_SESSION['success_sweet_msg']);
}

if(isset($_SESSION['product_locked_msg']) && !empty($_SESSION['product_locked_msg'])){
    echo sendSweetError($_SESSION['product_locked_msg'], 'Dein Produkt ist gesperrt');
    $_SESSION['product_locked_msg'] = '';
    unset($_SESSION['product_locked_msg']);
}

?>

<?php if($user->getDataById($userid,'livechat')){ ?>
    <!-- livechat einfügen -->
<?php } ?>
<!--end::Javascript-->
</body>
<!--end::Body-->
</html>