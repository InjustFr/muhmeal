<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pantry;

use App\Domain\Pantry\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnitTest extends TestCase
{
    public function testAPieceIsLowAtOneAndAContainerAtAQuarter(): void
    {
        self::assertSame(12, Unit::Piece->defaultLowAt()->twelfths());
    }

    #[DataProvider('containers')]
    public function testEveryContainerIsLowAtAQuarter(Unit $unit): void
    {
        self::assertSame(3, $unit->defaultLowAt()->twelfths());
    }

    /** @return iterable<string, array{Unit}> */
    public static function containers(): iterable
    {
        foreach (Unit::cases() as $unit) {
            if (Unit::Piece !== $unit) {
                yield $unit->value => [$unit];
            }
        }
    }
}
