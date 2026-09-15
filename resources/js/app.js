import jQuery from 'jquery';
window.$ = jQuery;
window.jQuery = jQuery;

import Alpine from 'alpinejs';
import Swal from 'sweetalert2';

window.Swal = Swal;
window.Alpine = Alpine;

Alpine.start();

$(document).ready(function () {
    $(".select2").select2({
        tags: true,
        dir: "rtl",
        placeholder: "موردی را انتخاب یا تایپ نمایید",
        allowClear: true,
    });
});
