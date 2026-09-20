<?php

use App\Support\JalaliDate;
use Carbon\Carbon;

test('it normalizes persian and arabic digits', function () {
    expect(JalaliDate::normalizeDigits('۱۴۰۵/۰۱/۱۵'))->toBe('1405/01/15');
    expect(JalaliDate::normalizeDigits('١٤٠٥/٠١/١٥'))->toBe('1405/01/15');
    expect(JalaliDate::normalizeDigits('1405/01/15'))->toBe('1405/01/15');
});

test('it converts jalali date to gregorian correctly', function () {
    // 1405/01/15 Jalali corresponds to 2026-04-04 Gregorian
    $gregorian = JalaliDate::toGregorianDate('1405/01/15');
    expect($gregorian)->toBe('2026-04-04');

    // Persian digits
    $gregorianPersian = JalaliDate::toGregorianDate('۱۴۰۵/۰۱/۱۵');
    expect($gregorianPersian)->toBe('2026-04-04');
});

test('it converts jalali datetime to gregorian datetime correctly', function () {
    $gregorian = JalaliDate::toGregorianDateTime('1405/01/15 14:30');
    expect($gregorian)->toBe('2026-04-04 14:30:00');

    $gregorianDateOnly = JalaliDate::toGregorianDateTime('1405/01/15');
    expect($gregorianDateOnly)->toBe('2026-04-04 00:00:00');
});

test('it leaves already gregorian dates untouched', function () {
    expect(JalaliDate::toGregorianDate('2026-04-04'))->toBe('2026-04-04');
    expect(JalaliDate::toGregorianDateTime('2026-04-04 14:30:00'))->toBe('2026-04-04 14:30:00');
});

test('it handles null and empty inputs safely', function () {
    expect(JalaliDate::toGregorianDate(null))->toBeNull();
    expect(JalaliDate::toGregorianDate(''))->toBeNull();
    expect(JalaliDate::toGregorianDateTime(null))->toBeNull();
    expect(JalaliDate::toGregorianDateTime(''))->toBeNull();

    expect(jdate(null)->format('Y/m/d'))->toBe('-');
    expect(jdate(null)->formatDateTime())->toBe('-');
    expect(jdate('')->format('Y/m/d'))->toBe('-');
    expect((string) jdate(null))->toBe('-');
});

test('it formats gregorian carbon instances to jalali', function () {
    $carbon = Carbon::create(2026, 4, 4, 14, 30, 0);
    expect(jdate($carbon)->format('Y/m/d'))->toBe('1405/01/15');
    expect(jdate($carbon, 'Y/m/d'))->toBe('1405/01/15');
    expect(to_jalali($carbon, 'Y/m/d'))->toBe('1405/01/15');
});

test('it provides global helper jalali_to_gregorian', function () {
    expect(jalali_to_gregorian('1405/01/15'))->toBe('2026-04-04');
    expect(jalali_to_gregorian(null))->toBeNull();
});
