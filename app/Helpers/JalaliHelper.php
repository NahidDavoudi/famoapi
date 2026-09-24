<?php

namespace App\Helpers;

class JalaliHelper
{
    private static array $monthNames = [
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
    ];

    public static function today(): string
    {
        return self::gregorianToJalali(date('Y'), date('m'), date('d'));
    }

    public static function gregorianToJalali(int $gy, int $gm, int $gd): string
    {
        $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gy > 1600) ? ($gy - 1600) : ($gy - 621);
        $r = ($gy2 % 33);

        $leap = (!in_array($r, [1, 5, 9, 13, 17, 22, 26, 30])) ? 0 : 1;
        $m = ($gm < 7) ? ($gm - 1) : ($gm - 7);

        $days = ($gy - 1600) * 365 + floor(($gy - 1600) / 33) * 8 + floor(((($gy - 1600) % 33) + 3) / 4) + $gdm[$gm - 1] + $gd - 1;
        $days -= 79;

        $jy = 979 + 33 * floor($days / 12053) + 4 * floor(($days % 12053) / 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += 4 * floor($days / 366);
            $days %= 366;
        }

        $jm = ($days < 186) ? (1 + floor($days / 31)) : (7 + floor(($days - 186) / 30));
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    public static function jalaliToGregorian(string $jalali): string
    {
        $parts = explode('/', $jalali);
        if (count($parts) !== 3) return $jalali;

        $jy = (int) $parts[0];
        $jm = (int) $parts[1];
        $jd = (int) $parts[2];

        $jy += 1595;
        $days = -355668 + (365 * floor(($jy - 1) / 33)) + floor((($jy - 1) % 33 + 3) / 4)
            + ($jm <= 7 ? ($jm - 1) * 31 : ($jm - 7) * 30 + 186) + $jd - 1;

        $gy = 400 * floor($days / 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * floor(--$days / 36524);
            $days %= 36524;
            if ($days >= 365) $days++;
        }

        $gy += 4 * floor($days / 1461);
        $days %= 1461;

        if ($days > 365) {
            $gy += floor(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }

        $gdm = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;
        while ($gm < 12 && $days >= $gdm[$gm]) {
            $days -= $gdm[$gm];
            $gm++;
        }
        $gm++;
        $gd = $days + 1;

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }
}