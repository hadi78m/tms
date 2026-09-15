$(document).ready(function () {
    var p = new persianDate();
    var today = p.now().toString("YYYY/MM/DD");

    $(".datedown").persianDatepicker({
        cellWidth: 38,
        cellHeight: 30,
        fontSize: 14,
        calendarPosition: {
            x: 0,
            y: 0,
        },
        formatDate: "YYYY/MM/DD",

        // نمایش تاریخ میلادی
        showGregorianDate: true,
        // نمایش یا عدم نمایش اعداد 
        // persianNumbers: false,

        // نمایش دائمی 
        // alwaysShow: true,


        // ۲. فعال کردن حالت مشاهده ماه (View Mode)
        // این تنظیم به کاربر اجازه می‌دهد فقط تا سطح ماه پایین برود و روز قابل انتخاب نباشد.
        // viewMode: 'month',

        // ۳. بستن خودکار پس از انتخاب ماه
        // چون نیازی به انتخاب روز نیست، به محض انتخاب ماه، تقویم بسته می‌شود.
        // autoClose: true,
    });
    $(".datetop").persianDatepicker({
        cellWidth: 38,
        cellHeight: 30,
        fontSize: 14,
        //  تاریخ شروع و پایان 
        // startDate: "1404/11/01",
        // endDate: "today",
        calendarPosition: {
            x: 0,
            y: -350,
        },
        formatDate: "YYYY/MM/DD",

        // نمایش تاریخ میلادی
        showGregorianDate: true,
        // نمایش یا عدم نمایش اعداد 
        // persianNumbers: false,

        // نمایش دائمی 
        // alwaysShow: true,


        // ۲. فعال کردن حالت مشاهده ماه (View Mode)
        // این تنظیم به کاربر اجازه می‌دهد فقط تا سطح ماه پایین برود و روز قابل انتخاب نباشد.
        // viewMode: 'month',

        // ۳. بستن خودکار پس از انتخاب ماه
        // چون نیازی به انتخاب روز نیست، به محض انتخاب ماه، تقویم بسته می‌شود.
        // autoClose: true,
    });


});
/****
$(document).ready(function () {
    // تنظیم زمان ابتدا و انتها
    $("#pdpStartEnd").persianDatepicker({
        startDate: "1394/11/12",
        endDate: "1395/5/5",
    });
    $("#pdpStartToday").persianDatepicker({
        startDate: "today",
        endDate: "1395/5/5",
    });
    $("#pdpEndToday").persianDatepicker({
        startDate: "1393/11/12",
        endDate: "today",
    });

    var p = new persianDate();
    $("#pdpStartDateTomarrow").persianDatepicker({
        startDate: p.now().addMonth(-1).toString("YYYY/MM/DD"),
        endDate: p.now().addMonth(2).toString("YYYY/MM/DD"),
    });
});
*/