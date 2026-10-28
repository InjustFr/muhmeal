<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pantry;

use App\Domain\Pantry\Exception\QuantityOutOfRange;
use App\Domain\Pantry\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function testHoldsTwelfthsFromZeroTo999Units(): void
    {
        self::assertSame(0, Quantity::zero()->twelfths());
        self::assertSame(33, Quantity::ofTwelfths(33)->twelfths());
        self::assertSame(11988, Quantity::ofTwelfths(11988)->twelfths());
    }

    #[DataProvider('outOfRange')]
    public function testRefusesAnythingOutsideTheRange(int $twelfths): void
    {
        $this->expectExceptionObject(new QuantityOutOfRange(999));

        Quantity::ofTwelfths($twelfths);
    }

    /** @return iterable<array{int}> */
    public static function outOfRange(): iterable
    {
        yield 'negative' => [-1];
        yield 'one twelfth above 999 units' => [11989];
    }

    public function testThirdsAddUpExactly(): void
    {
        $third = Quantity::ofTwelfths(4);

        self::assertSame(12, $third->plus($third)->plus($third)->twelfths());
    }

    public function testSubtractionStopsAtZero(): void
    {
        self::assertSame(5, Quantity::ofTwelfths(9)->minus(Quantity::ofTwelfths(4))->twelfths());
        self::assertTrue(Quantity::ofTwelfths(4)->minus(Quantity::ofTwelfths(9))->isZero());
    }

    public function testAdditionCannotPassTheCeiling(): void
    {
        $this->expectExceptionObject(new QuantityOutOfRange(999));

        Quantity::ofTwelfths(11988)->plus(Quantity::ofTwelfths(1));
    }

    public function testComparesWithAtLeast(): void
    {
        self::assertTrue(Quantity::ofTwelfths(6)->atLeast(Quantity::ofTwelfths(6)));
        self::assertTrue(Quantity::ofTwelfths(7)->atLeast(Quantity::ofTwelfths(6)));
        self::assertFalse(Quantity::ofTwelfths(5)->atLeast(Quantity::ofTwelfths(6)));
    }

    public function testZeroIsZero(): void
    {
        self::assertTrue(Quantity::zero()->isZero());
        self::assertFalse(Quantity::ofTwelfths(1)->isZero());
    }
}
