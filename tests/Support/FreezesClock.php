<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Clock\Test\ClockSensitiveTrait;

trait FreezesClock
{
    use ClockSensitiveTrait;

    protected static function freezeAt(string $moment): void
    {
        self::mockTime(new \DateTimeImmutable($moment)->setTimezone(new \DateTimeZone(date_default_timezone_get())));
    }
}
