<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Domain\Shared\Day;

final class DayParameter
{
    public static function of(?string $day): ?\DateTimeImmutable
    {
        return null === $day || '' === $day ? null : Day::of($day);
    }
}
