<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final class OptionalText
{
    public static function of(?string $text): ?string
    {
        $text = trim($text ?? '');

        return '' === $text ? null : $text;
    }
}
