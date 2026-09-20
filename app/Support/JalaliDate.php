<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Hekmatinasser\Verta\Verta;
use Stringable;

class SafeJalali implements Stringable
{
    public function __construct(protected ?Verta $verta = null) {}

    public function format(string $format = 'Y/m/d H:i'): string
    {
        return $this->verta ? $this->verta->format($format) : '-';
    }

    public function formatDate(): string
    {
        return $this->format('Y/m/d');
    }

    public function formatDateTime(): string
    {
        return $this->format('Y/m/d H:i');
    }

    public function getVerta(): ?Verta
    {
        return $this->verta;
    }

    public function __call(string $name, array $arguments): mixed
    {
        if ($this->verta) {
            return $this->verta->$name(...$arguments);
        }

        return null;
    }

    public function __toString(): string
    {
        return $this->verta ? $this->verta->format('Y/m/d H:i') : '-';
    }
}

class JalaliDate
{
    /**
     * Normalize Persian and Arabic numerals to English digits.
     */
    public static function normalizeDigits(string $string): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $string = str_replace($persian, $english, $string);

        return str_replace($arabic, $english, $string);
    }

    /**
     * Parse any date into a SafeJalali object.
     */
    public static function from(mixed $date = null): SafeJalali
    {
        if ($date === null && func_num_args() === 0) {
            return new SafeJalali(verta());
        }

        if (empty($date)) {
            return new SafeJalali(null);
        }

        try {
            if ($date instanceof Verta) {
                return new SafeJalali($date);
            }

            if ($date instanceof DateTimeInterface) {
                return new SafeJalali(verta($date));
            }

            $str = trim(self::normalizeDigits((string) $date));

            if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/', $str, $matches)) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
                $hour = isset($matches[4]) ? (int) $matches[4] : 0;
                $minute = isset($matches[5]) ? (int) $matches[5] : 0;
                $second = isset($matches[6]) ? (int) $matches[6] : 0;

                if ($year >= 1200 && $year <= 1500) {
                    return new SafeJalali(Verta::createJalali($year, $month, $day, $hour, $minute, $second));
                }

                if ($year >= 1900 && $year <= 2200) {
                    $c = Carbon::create($year, $month, $day, $hour, $minute, $second);

                    return new SafeJalali(verta($c));
                }
            }

            return new SafeJalali(verta(Carbon::parse($str)));
        } catch (\Throwable) {
            return new SafeJalali(null);
        }
    }

    /**
     * Convert any Jalali or Gregorian date string to Gregorian format.
     */
    public static function toGregorian(?string $date, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (empty($date)) {
            return null;
        }

        $str = trim(self::normalizeDigits($date));

        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/', $str, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            $hour = isset($matches[4]) ? (int) $matches[4] : 0;
            $minute = isset($matches[5]) ? (int) $matches[5] : 0;
            $second = isset($matches[6]) ? (int) $matches[6] : 0;

            if ($year >= 1200 && $year <= 1500) {
                try {
                    $v = Verta::createJalali($year, $month, $day, $hour, $minute, $second);

                    return $v->toCarbon()->format($format);
                } catch (\Throwable) {
                    return null;
                }
            } elseif ($year >= 1900 && $year <= 2200) {
                try {
                    $c = Carbon::create($year, $month, $day, $hour, $minute, $second);

                    return $c->format($format);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        try {
            return Carbon::parse($str)->format($format);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Convert date string to Gregorian Date (Y-m-d).
     */
    public static function toGregorianDate(?string $date): ?string
    {
        return self::toGregorian($date, 'Y-m-d');
    }

    /**
     * Convert date string to Gregorian DateTime (Y-m-d H:i:s).
     */
    public static function toGregorianDateTime(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        $str = trim(self::normalizeDigits($date));
        $hasTime = str_contains($str, ':');

        return self::toGregorian($date, $hasTime ? 'Y-m-d H:i:s' : 'Y-m-d 00:00:00');
    }

    /**
     * Format a date into Jalali string.
     */
    public static function toJalali(mixed $date = null, string $format = 'Y/m/d'): ?string
    {
        if (empty($date)) {
            return null;
        }

        return self::from($date)->format($format);
    }
}
