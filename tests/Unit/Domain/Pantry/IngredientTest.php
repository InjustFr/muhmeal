<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pantry;

use App\Domain\Pantry\Exception\EmptyIngredientName;
use App\Domain\Pantry\Exception\IngredientNameTooLong;
use App\Domain\Pantry\Exception\InvalidMonth;
use App\Domain\Pantry\Exception\NestedVariant;
use App\Domain\Pantry\Exception\QuantityOutOfRange;
use App\Domain\Pantry\Exception\VariantOfItself;
use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Months;
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

    public function testIsBoughtAllYearUntilToldOtherwise(): void
    {
        $zucchini = self::ingredient('Zucchini');
        self::assertSame(range(1, 12), $zucchini->months()->toList());

        $zucchini->buyIn(Months::of([6, 7, 8, 9]));

        self::assertTrue($zucchini->isBoughtIn(7));
        self::assertFalse($zucchini->isBoughtIn(1));
        self::assertSame([6, 7, 8, 9], $zucchini->months()->toList());
    }

    public function testNoMonthMeansNeverBought(): void
    {
        $sodas = self::ingredient('Sodas');
        $sodas->buyIn(Months::of([]));

        self::assertSame([], $sodas->months()->toList());
        self::assertFalse($sodas->isBoughtIn(5));
    }

    public function testAMonthGoesFromOneToTwelve(): void
    {
        $this->expectExceptionObject(new InvalidMonth(13));

        Months::of([1, 13]);
    }

    public function testAVariantJoinsTheFamilyOfItsMainIngredient(): void
    {
        $zucchini = self::ingredient('Zucchini');
        $frozen = self::ingredient('Frozen zucchini', Unit::Bag);
        $grilled = self::ingredient('Grilled zucchini', Unit::Jar);
        $frozen->makeVariantOf($zucchini);
        $grilled->makeVariantOf($zucchini);

        self::assertSame($zucchini, $frozen->variantOf());
        self::assertSame([$zucchini, $frozen, $grilled], $zucchini->family());
        self::assertSame([$frozen, $zucchini, $grilled], $frozen->family());
        $leek = self::ingredient('Leek');
        self::assertSame([$leek], $leek->family());
    }

    public function testLeavingAFamily(): void
    {
        $zucchini = self::ingredient('Zucchini');
        $frozen = self::ingredient('Frozen zucchini', Unit::Bag);
        $frozen->makeVariantOf($zucchini);

        $frozen->makeVariantOf(null);

        self::assertNull($frozen->variantOf());
        self::assertSame([$zucchini], $zucchini->family());
    }

    public function testAnIngredientIsNotAVariantOfItself(): void
    {
        $zucchini = self::ingredient('Zucchini');

        $this->expectExceptionObject(new VariantOfItself('Zucchini'));

        $zucchini->makeVariantOf($zucchini);
    }

    public function testVariantsGoOneLevelDeep(): void
    {
        $zucchini = self::ingredient('Zucchini');
        $frozen = self::ingredient('Frozen zucchini', Unit::Bag);
        $frozen->makeVariantOf($zucchini);

        try {
            self::ingredient('Cheap frozen zucchini')->makeVariantOf($frozen);
            self::fail('A variant of a variant was accepted.');
        } catch (NestedVariant $exception) {
            self::assertEquals(new NestedVariant('Cheap frozen zucchini'), $exception);
        }

        $this->expectExceptionObject(new NestedVariant('Zucchini'));

        $zucchini->makeVariantOf(self::ingredient('Courgette'));
    }

    private static function ingredient(string $name = 'Eggs', Unit $unit = Unit::Piece, bool $manual = false, ?Quantity $lowAt = null): Ingredient
    {
        return Ingredient::add($name, Location::Pantry, $unit, $manual, $lowAt, new \DateTimeImmutable());
    }
}
