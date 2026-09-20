<?php

use App\Support\JalaliDate;
use App\Support\SafeJalali;

if (! function_exists('jdate')) {
    /**
     * Parse date into SafeJalali or format directly.
     */
    function jdate(mixed $date = null, ?string $format = null): SafeJalali|string
    {
        $safe = func_num_args() === 0 ? JalaliDate::from() : JalaliDate::from($date);

        return $format ? $safe->format($format) : $safe;
    }
}

if (! function_exists('to_jalali')) {
    /**
     * Helper to format date to Jalali string.
     */
    function to_jalali(mixed $date, string $format = 'Y/m/d'): ?string
    {
        return JalaliDate::toJalali($date, $format);
    }
}

if (! function_exists('jalali_to_gregorian')) {
    /**
     * Helper to convert Jalali string to Gregorian.
     */
    function jalali_to_gregorian(?string $date, string $format = 'Y-m-d'): ?string
    {
        return JalaliDate::toGregorian($date, $format);
    }
}
