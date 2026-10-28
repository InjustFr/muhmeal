<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Cooking;

use App\Domain\Cooking\Exception\EmptyMeal;
use App\Domain\Cooking\Exception\IngredientListedTwice;
use App\Domain\Cooking\Exception\MealNeedsEater;
use App\Domain\Cooking\Exception\MealTitleTooLong;
use App\Domain\Cooking\Meal;
use App\Domain\Cooking\MealLine;
use App\Domain\Cooking\MealType;
use App\Domain\Cooking\Portion;
use App\Domain\Identity\User;
use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Quantity;
use App\Domain\Pantry\Unit;
use PHPUnit\Framework\TestCase;

final class MealTest extends TestCase
{
    public function testLoggingAMealConsumesEveryUsedAmount(): void
    {
        $pasta = self::ingredient('Pasta', 36);
        $oil = self::ingredient('Oil', 6, manual: true);

        $meal = self::meal([new Portion($pasta, Quantity::ofTwelfths(12)), new Portion($oil, Quantity::ofTwelfths(1))]);

        self::assertSame([24, 5], [$pasta->stock()->twelfths(), $oil->stock()->twelfths()]);
        self::assertSame(['Pasta', 'Oil'], array_map(static fn (MealLine $line): string => $line->name(), $meal->lines()));
    }

    public function testUsingMoreThanTheStockEmptiesItAndNeverRefuses(): void
    {
        $pasta = self::ingredient('Pasta', 6);

        self::meal([new Portion($pasta, Quantity::ofTwelfths(36))]);

        self::assertTrue($pasta->isOut());
    }

    public function testLinesKeepASnapshotOfTheIngredient(): void
    {
        $pasta = self::ingredient('Pasta', 36, Unit::Pack);
        $meal = self::meal([new Portion($pasta, Quantity::ofTwelfths(18))]);
        $pasta->describe('Penne', Location::Fridge, Unit::Box, false, null);

        [$line] = $meal->lines();

        self::assertSame(['Pasta', Unit::Pack, 18, $pasta->id()], [$line->name(), $line->unit(), $line->used()->twelfths(), $line->ingredientId()]);
    }

    public function testAMealWithATitleAndNoLineIsValid(): void
    {
        $meal = self::meal([], title: '  Takeaway pizza ');

        self::assertSame('Takeaway pizza', $meal->title());
        self::assertSame([], $meal->lines());
    }

    public function testAMealWithLinesAndNoTitleIsValid(): void
    {
        $meal = self::meal([new Portion(self::ingredient('Pasta', 12), Quantity::ofTwelfths(12))], title: '  ');

        self::assertNull($meal->title());
    }

    public function testAMealNeedsATitleOrALine(): void
    {
        $this->expectExceptionObject(new EmptyMeal());

        self::meal([], title: ' ');
    }

    public function testTheTitleHasAtMostEightyCharacters(): void
    {
        self::assertSame(80, mb_strlen((string) self::meal([], title: str_repeat('é', 80))->title()));

        $this->expectExceptionObject(new MealTitleTooLong(80));

        self::meal([], title: str_repeat('é', 81));
    }

    public function testAMealNeedsAnEater(): void
    {
        $this->expectExceptionObject(new MealNeedsEater());

        Meal::log(new \DateTimeImmutable('2026-10-06'), MealType::Dinner, 'Pizza', [], [], self::user('Louis'), new \DateTimeImmutable());
    }

    public function testEatersAreKeptOnceEach(): void
    {
        $louis = self::user('Louis');
        $ada = self::user('Ada');

        $meal = Meal::log(new \DateTimeImmutable('2026-10-06'), MealType::Dinner, 'Pizza', [$louis, $ada, $louis], [], $louis, new \DateTimeImmutable());

        self::assertSame([$louis, $ada], $meal->eaters());
    }

    public function testAnIngredientIsUsedOncePerMealAndNothingIsConsumedWhenItIsRefused(): void
    {
        $pasta = self::ingredient('Pasta', 36);

        try {
            self::meal([new Portion($pasta, Quantity::ofTwelfths(12)), new Portion($pasta, Quantity::ofTwelfths(12))]);
            self::fail('A duplicated ingredient must be refused.');
        } catch (IngredientListedTwice $exception) {
            self::assertSame('cooking.ingredient_listed_twice', $exception->getMessage());
            self::assertSame(36, $pasta->stock()->twelfths());
        }
    }

    public function testTheDayIsDroppedToMidnightAndTheTypeKept(): void
    {
        $meal = Meal::log(new \DateTimeImmutable('2026-10-06 18:45:12'), MealType::Snack, 'Fruit', [self::user('Louis')], [], self::user('Louis'), new \DateTimeImmutable());

        self::assertSame(['2026-10-06 00:00:00', MealType::Snack], [$meal->eatenOn()->format('Y-m-d H:i:s'), $meal->type()]);
    }

    public function testGivingBackRestocksWhatTheMealUsed(): void
    {
        $pasta = self::ingredient('Pasta', 36);
        $oil = self::ingredient('Oil', 6, manual: true);
        $meal = self::meal([new Portion($pasta, Quantity::ofTwelfths(12)), new Portion($oil, Quantity::ofTwelfths(2))]);

        $meal->giveBack();

        self::assertSame([36, 6], [$pasta->stock()->twelfths(), $oil->stock()->twelfths()]);
    }

    public function testGivingBackIsExactEvenWhenTheStockWasClamped(): void
    {
        $pasta = self::ingredient('Pasta', 6);
        $meal = self::meal([new Portion($pasta, Quantity::ofTwelfths(36))]);

        $meal->giveBack();

        self::assertSame(36, $pasta->stock()->twelfths());
    }

    /**
     * @param list<Portion> $portions
     */
    private static function meal(array $portions, ?string $title = 'Dinner'): Meal
    {
        $louis = self::user('Louis');

        return Meal::log(new \DateTimeImmutable('2026-10-06'), MealType::Dinner, $title, [$louis], $portions, $louis, new \DateTimeImmutable());
    }

    private static function user(string $name): User
    {
        return User::join(strtolower($name), strtolower($name).'@muhmeal.test', $name, new \DateTimeImmutable());
    }

    private static function ingredient(string $name, int $stock, Unit $unit = Unit::Piece, bool $manual = false): Ingredient
    {
        $ingredient = Ingredient::add($name, Location::Pantry, $unit, $manual, null, new \DateTimeImmutable());
        $ingredient->setStock(Quantity::ofTwelfths($stock));

        return $ingredient;
    }
}
