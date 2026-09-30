<?php

namespace App\Modules\Bot;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Iran calendar-day helper (Asia/Tehran).
 *
 * PHP keeps its global default timezone (UTC) and the DB session timezone is
 * unchanged; timezone-sensitive logic is isolated here. Days are represented
 * as Tehran calendar dates (Y-m-d), while timestamps stored in the database
 * stay in UTC. Day boundaries are exposed as UTC instants for range queries.
 * Weeks run Saturday → Friday.
 */
final class IranDay
{
    public const TIMEZONE = 'Asia/Tehran';

    private const WEEKDAY_NAMES = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }

    /** Today's Tehran calendar date (Y-m-d). */
    public static function today(): string
    {
        return (new DateTimeImmutable('now', self::timezone()))->format('Y-m-d');
    }

    /** Jalali string (YYYY/MM/DD) for a Tehran date; defaults to today. */
    public static function jalali(?string $tehranDate = null): string
    {
        $date = $tehranDate ?? self::today();
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return $date;
        }

        return self::toJalali((int) $parts[0], (int) $parts[1], (int) $parts[2]);
    }

    /**
     * Correct Gregorian → Jalali conversion (YYYY/MM/DD).
     *
     * NOTE: implemented locally instead of using App\Helpers\JalaliHelper,
     * which returns incorrect years on this deployment (e.g. 2024-03-20 →
     * 1411/12/24 instead of 1403/01/01). The existing helper is intentionally
     * left untouched.
     */
    public static function toJalali(int $gy, int $gm, int $gd): string
    {
        $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        if ($gy > 1600) {
            $jy = 979;
            $gy -= 1600;
        } else {
            $jy = 0;
            $gy -= 621;
        }

        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = (365 * $gy)
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            - 80
            + $gd
            + $gDaysInMonth[$gm - 1];

        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;

        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $jm = $days < 186 ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
        $jd = 1 + ($days < 186 ? $days % 31 : ($days - 186) % 30);

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    /** Saturday that starts the week containing the given Tehran date. */
    public static function weekStart(?string $tehranDate = null): string
    {
        $date = $tehranDate ?? self::today();
        $moment = new DateTimeImmutable($date . ' 00:00:00', self::timezone());
        $dayOfWeek = (int) $moment->format('w'); // 0=Sunday .. 6=Saturday
        $daysSinceSaturday = ($dayOfWeek - 6 + 7) % 7;

        return $moment->modify(sprintf('-%d days', $daysSinceSaturday))->format('Y-m-d');
    }

    /**
     * Seven consecutive Tehran dates (Saturday → Friday) from a week start.
     *
     * @return string[]
     */
    public static function weekDays(?string $weekStart = null): array
    {
        $start = $weekStart !== null ? self::weekStart($weekStart) : self::weekStart();
        $moment = new DateTimeImmutable($start . ' 00:00:00', self::timezone());

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $moment->format('Y-m-d');
            $moment = $moment->modify('+1 day');
        }

        return $days;
    }

    /**
     * UTC instants [start, end) covering a Tehran day, formatted as MySQL
     * DATETIME strings. Use for building UTC range queries.
     *
     * @return array{start:string,end:string}
     */
    public static function dayBoundsUtc(string $tehranDate): array
    {
        $start = new DateTimeImmutable($tehranDate . ' 00:00:00', self::timezone());
        $end = $start->modify('+1 day');
        $utc = new DateTimeZone('UTC');

        return [
            'start' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            'end'   => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }

    /** Current UTC timestamp as a MySQL DATETIME string. */
    public static function nowUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    /**
     * Relative position of a Tehran date to today.
     *
     * @return int -1 past, 0 today, 1 future
     */
    public static function compare(string $tehranDate, ?string $today = null): int
    {
        $reference = $today ?? self::today();

        return $tehranDate <=> $reference;
    }

    public static function isPast(string $tehranDate, ?string $today = null): bool
    {
        return self::compare($tehranDate, $today) < 0;
    }

    public static function isFuture(string $tehranDate, ?string $today = null): bool
    {
        return self::compare($tehranDate, $today) > 0;
    }

    /** Persian weekday name (شنبه = Saturday). */
    public static function weekdayName(string $tehranDate): string
    {
        $moment = new DateTimeImmutable($tehranDate . ' 00:00:00', self::timezone());
        $dayOfWeek = (int) $moment->format('w'); // 0=Sunday .. 6=Saturday

        return self::WEEKDAY_NAMES[($dayOfWeek + 1) % 7];
    }

    /** Shift a Tehran date by a number of days (negative allowed). */
    public static function addDays(string $tehranDate, int $delta): string
    {
        $moment = new DateTimeImmutable($tehranDate . ' 00:00:00', self::timezone());

        return $moment->modify(sprintf('%+d days', $delta))->format('Y-m-d');
    }

    /**
     * Inclusive list of Tehran dates between two dates (capped at 400 days).
     *
     * @return string[]
     */
    public static function datesBetween(string $from, string $to): array
    {
        if (!self::isValidDate($from) || !self::isValidDate($to) || $from > $to) {
            return [];
        }

        $current = new DateTimeImmutable($from . ' 00:00:00', self::timezone());
        $end = new DateTimeImmutable($to . ' 00:00:00', self::timezone());

        $dates = [];
        $guard = 0;
        while ($current <= $end && $guard < 400) {
            $dates[] = $current->format('Y-m-d');
            $current = $current->modify('+1 day');
            $guard++;
        }

        return $dates;
    }

    public static function isValidDate(string $tehranDate): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tehranDate)) {
            return false;
        }

        $moment = DateTimeImmutable::createFromFormat('!Y-m-d', $tehranDate, self::timezone());

        return $moment !== false && $moment->format('Y-m-d') === $tehranDate;
    }

    /**
     * Day state for weekly status.
     *
     * @return string sent | missed | pending
     */
    public static function dayState(string $tehranDate, bool $reportSubmitted, ?string $today = null): string
    {
        if ($reportSubmitted) {
            return 'sent';
        }

        // Future days and today without a report are pending; never missed.
        return self::isPast($tehranDate, $today) ? 'missed' : 'pending';
    }
}
