<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\Day;
use App\Domain\Shared\Exception\InvalidDay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DayTest extends TestCase
{
    public function testOfParsesAMidnightDay(): void
    {
        self::assertSame('2026-10-06 00:00:00', Day::of('2026-10-06')->format('Y-m-d H:i:s'));
    }

    #[DataProvider('invalidDays')]
    public function testRejectsInvalidDays(string $day): void
    {
        $this->expectExceptionObject(new InvalidDay($day));

        Day::of($day);
    }

    /** @return iterable<array{string}> */
    public static function invalidDays(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['tomorrow'];
        yield 'february 30' => ['2026-02-30'];
        yield 'month 13' => ['2026-13-01'];
        yield 'no padding' => ['2026-1-5'];
        yield 'with time' => ['2026-10-06 10:00'];
    }

    public function testNormalizeDropsTimeAndZone(): void
    {
        self::assertEquals(Day::of('2026-10-06'), Day::normalize(new \DateTimeImmutable('2026-10-06 23:59', new \DateTimeZone('Pacific/Auckland'))));
    }

    public function testFormat(): void
    {
        self::assertNull(Day::format(null));
        self::assertSame('2026-10-06', Day::format(Day::of('2026-10-06')));
    }

    public function testDaysBetweenIsSigned(): void
    {
        self::assertSame(0, Day::daysBetween(new \DateTimeImmutable('2026-10-06 01:00'), new \DateTimeImmutable('2026-10-06 23:00')));
        self::assertSame(1, Day::daysBetween(Day::of('2026-10-06'), Day::of('2026-10-07')));
        self::assertSame(-3, Day::daysBetween(Day::of('2026-10-06'), Day::of('2026-10-03')));
        self::assertSame(1, Day::daysBetween(Day::of('2026-10-24'), Day::of('2026-10-25')));
    }
}
