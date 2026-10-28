<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pantry;

use App\Domain\Pantry\Exception\EmptyIngredientName;
use App\Domain\Pantry\Exception\IngredientNameTooLong;
use App\Domain\Pantry\Exception\QuantityOutOfRange;
use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Quantity;
use App\Domain\Pantry\Unit;
use PHPUnit\Framework\TestCase;

final class IngredientTest extends TestCase
{
    public function testStartsEmptyWithTheUnitsDefaultThreshold(): void
    {
        $milk = self::ingredient('  Milk ', Unit::Carton);

        self::assertSame('Milk', $milk->name());
        self::assertTrue($milk->stock()->isZero());
        self::assertSame(3, $milk->lowAt()->twelfths());
        self::assertTrue($milk->isOut());
    }

    public function testNameIsRequired(): void
    {
        $this->expectExceptionObject(new EmptyIngredientName());

        self::ingredient('   ');
    }

    public function testNameHasAtMostSixtyCharacters(): void
    {
        self::assertSame(60, mb_strlen(self::ingredient(str_repeat('é', 60))->name()));

        $this->expectExceptionObject(new IngredientNameTooLong(60));

        self::ingredient(str_repeat('é', 61));
    }

    public function testDescribeReplacesWhatTheIngredientIs(): void
    {
        $oil = self::ingredient('Oil', Unit::Piece);

        $oil->describe(' Olive oil ', Location::Fridge, Unit::Bottle, true, Quantity::ofTwelfths(6));

        self::assertSame(['Olive oil', Location::Fridge, Unit::Bottle, true, 6], [$oil->name(), $oil->location(), $oil->unit(), $oil->isManual(), $oil->lowAt()->twelfths()]);
    }

    public function testDescribeWithoutThresholdFallsBackOnTheUnit(): void
    {
        $oil = self::ingredient('Oil', Unit::Piece, lowAt: Quantity::ofTwelfths(36));

        $oil->describe('Oil', Location::Pantry, Unit::Bottle, false, null);

        self::assertSame(3, $oil->lowAt()->twelfths());
    }

    public function testDescribeValidatesTheName(): void
    {
        $this->expectExceptionObject(new EmptyIngredientName());

        self::ingredient('Oil')->describe(' ', Location::Pantry, Unit::Piece, false, null);
    }

    public function testRestockAndConsumeMoveTheStock(): void
    {
        $flour = self::ingredient('Flour', Unit::Bag);

        $flour->restock(Quantity::ofTwelfths(24));
        $flour->consume(Quantity::ofTwelfths(9));

        self::assertSame(15, $flour->stock()->twelfths());
    }

    public function testConsumingMoreThanTheStockEmptiesIt(): void
    {
        $flour = self::ingredient('Flour', Unit::Bag);
        $flour->setStock(Quantity::ofTwelfths(6));

        $flour->consume(Quantity::ofTwelfths(30));

        self::assertTrue($flour->isOut());
    }

    public function testAdjustMovesTheStockBothWaysAndStopsAtZero(): void
    {
        $eggs = self::ingredient('Eggs');

        $eggs->adjust(12);
        $eggs->adjust(-5);
        self::assertSame(7, $eggs->stock()->twelfths());

        $eggs->adjust(-100);
        self::assertSame(0, $eggs->stock()->twelfths());
    }

    public function testAdjustCannotPassTheCeiling(): void
    {
        $eggs = self::ingredient('Eggs');
        $eggs->setStock(Quantity::ofTwelfths(11988));

        $this->expectExceptionObject(new QuantityOutOfRange(999));

        $eggs->adjust(1);
    }

    public function testRunningLowFromTheThresholdDownwards(): void
    {
        $milk = self::ingredient('Milk', Unit::Carton, lowAt: Quantity::ofTwelfths(6));

        $milk->setStock(Quantity::ofTwelfths(7));
        self::assertFalse($milk->isRunningLow());

        $milk->setStock(Quantity::ofTwelfths(6));
        self::assertTrue($milk->isRunningLow());
        self::assertFalse($milk->isOut());

        $milk->setStock(Quantity::zero());
        self::assertTrue($milk->isRunningLow());
        self::assertTrue($milk->isOut());
    }

    public function testCoversAnAmountWhenTheStockReachesIt(): void
    {
        $flour = self::ingredient('Flour', Unit::Bag);
        $flour->setStock(Quantity::ofTwelfths(9));

        self::assertTrue($flour->covers(Quantity::ofTwelfths(9)));
        self::assertTrue($flour->covers(Quantity::ofTwelfths(4)));
        self::assertFalse($flour->covers(Quantity::ofTwelfths(10)));
    }

    public function testCoversASomeWhenThereIsAnyStock(): void
    {
        $salt = self::ingredient('Salt', Unit::Box);

        self::assertFalse($salt->covers(null));

        $salt->setStock(Quantity::ofTwelfths(1));
        self::assertTrue($salt->covers(null));
    }

    public function testAManualIngredientIsCoveredByAnyStockWhateverTheAmount(): void
    {
        $oil = self::ingredient('Oil', Unit::Bottle, manual: true);
        self::assertFalse($oil->covers(Quantity::ofTwelfths(1)));

        $oil->setStock(Quantity::ofTwelfths(1));

        self::assertTrue($oil->covers(Quantity::ofTwelfths(36)));
    }

    private static function ingredient(string $name = 'Eggs', Unit $unit = Unit::Piece, bool $manual = false, ?Quantity $lowAt = null): Ingredient
    {
        return Ingredient::add($name, Location::Pantry, $unit, $manual, $lowAt, new \DateTimeImmutable());
    }
}
