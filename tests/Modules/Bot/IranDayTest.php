<?php

namespace Tests\Modules\Bot;

use App\Modules\Bot\IranDay;
use PHPUnit\Framework\TestCase;

final class IranDayTest extends TestCase
{
    public function testWeekStartsOnSaturday(): void
    {
        // 2026-09-30 is a Wednesday.
        self::assertSame('2026-09-26', IranDay::weekStart('2026-09-30'));
        self::assertSame('2026-09-26', IranDay::weekStart('2026-09-26'));
        self::assertSame('2026-09-26', IranDay::weekStart('2026-10-02'));
        // Friday rolls into the next week's Saturday.
        self::assertSame('2026-10-03', IranDay::weekStart('2026-10-03'));
    }

    public function testWeekDaysAreSevenConsecutiveDaysSaturdayToFriday(): void
    {
        $days = IranDay::weekDays('2026-09-26');

        self::assertCount(7, $days);
        self::assertSame('2026-09-26', $days[0]);
        self::assertSame('2026-10-02', $days[6]);
    }

    public function testDayBoundsUtcConvertIranMidnightBoundaries(): void
    {
        $bounds = IranDay::dayBoundsUtc('2026-09-30');

        // Iran is UTC+03:30 (no DST since 2022).
        self::assertSame('2026-09-29 20:30:00', $bounds['start']);
        self::assertSame('2026-09-30 20:30:00', $bounds['end']);
    }

    public function testJalaliConversion(): void
    {
        self::assertSame('1405/07/08', IranDay::jalali('2026-09-30'));
    }

    public function testRelativeComparisons(): void
    {
        $today = '2026-09-30';

        self::assertSame(-1, IranDay::compare('2026-09-29', $today));
        self::assertSame(0, IranDay::compare('2026-09-30', $today));
        self::assertSame(1, IranDay::compare('2026-10-01', $today));
        self::assertTrue(IranDay::isPast('2026-09-29', $today));
        self::assertTrue(IranDay::isFuture('2026-10-01', $today));
    }
}
