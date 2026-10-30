<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Cooking;

use App\Domain\Cooking\Need;
use App\Domain\Cooking\Planner;
use App\Domain\Cooking\Recipe;
use App\Domain\Cooking\RecipeLine;
use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Quantity;
use App\Domain\Pantry\Unit;
use PHPUnit\Framework\TestCase;

final class PlannerTest extends TestCase
{
    public function testMissingListsTheLinesTheStockDoesNotCover(): void
    {
        $recipe = self::meal('Pasta', [
            [self::ingredient('Pasta', 12), 24],
            [self::ingredient('Tomatoes', 24), 24],
            [self::ingredient('Cheese', 0), null],
        ]);

        $plan = (new Planner([]))->plan($recipe);

        self::assertSame(['Pasta', 'Cheese'], self::names($plan->missing));
        self::assertSame([false, true, false], array_map($plan->covers(...), $recipe->lines()));
        self::assertFalse($plan->isMakeable());
    }

    public function testAManualIngredientAndASomeOnlyNeedAnyStock(): void
    {
        $plan = (new Planner([]))->plan(self::meal('Dressing', [
            [self::ingredient('Oil', 1, manual: true), 36],
            [self::ingredient('Salt', 1), null],
        ]));

        self::assertSame([], $plan->missing);
        self::assertTrue($plan->isMakeable());
    }

    public function testADishWaitsForThePrepThatMakesWhatItLacks(): void
    {
        $flour = self::ingredient('Flour', 12, Unit::Bag);
        $dough = self::ingredient('Dough', 0);
        $apples = self::ingredient('Apples', 48);
        $prep = self::prep('Dough', [[$flour, 6]], $dough, 12);
        $tart = self::meal('Apple tart', [[$dough, 12], [$apples, 36]]);

        $plan = (new Planner([$prep, $tart]))->plan($tart);

        self::assertSame([], $plan->missing);
        self::assertSame([$prep], $plan->preps);
        self::assertSame([false, true], array_map($plan->covers(...), $tart->lines()));
        self::assertSame($dough, $plan->uses($tart->lines()[0]));
        self::assertFalse($plan->isMakeable());
    }

    public function testPrepsAreFollowedThroughEveryLevelDeepestFirst(): void
    {
        $gluten = self::ingredient('Gluten', 12, Unit::Bag);
        $seitan = self::ingredient('Seitan', 0);
        $filling = self::ingredient('Filling', 0, Unit::Tub);
        $seitanPrep = self::prep('Seitan', [[$gluten, 6]], $seitan, 12);
        $fillingPrep = self::prep('Filling', [[$seitan, 12]], $filling, 12);
        $pie = self::meal('Pie', [[$filling, 12]]);

        $plan = (new Planner([$fillingPrep, $seitanPrep, $pie]))->plan($pie);

        self::assertSame([], $plan->missing);
        self::assertSame([$seitanPrep, $fillingPrep], $plan->preps);
    }

    public function testAPrepIsMadeAsManyTimesAsNeeded(): void
    {
        $flour = self::ingredient('Flour', 12, Unit::Bag);
        $dough = self::ingredient('Dough', 0);
        $prep = self::prep('Dough', [[$flour, 4]], $dough, 12);

        self::assertSame([], (new Planner([$prep]))->plan(self::meal('Big tart', [[$dough, 36]]))->missing);
        self::assertCount(1, (new Planner([$prep]))->plan(self::meal('Huge tart', [[$dough, 48]]))->missing);
    }

    public function testAPrepAndTheDishShareTheSameStock(): void
    {
        $butter = self::ingredient('Butter', 12);
        $dough = self::ingredient('Dough', 0);
        $prep = self::prep('Dough', [[$butter, 6]], $dough, 12);

        $enough = self::meal('Tart', [[$dough, 12], [$butter, 6]]);
        $tooMuch = self::meal('Rich tart', [[$dough, 12], [$butter, 8]]);
        $planner = new Planner([$prep]);

        self::assertSame([], $planner->plan($enough)->missing);
        self::assertSame(['Butter'], self::names($planner->plan($tooMuch)->missing));
    }

    public function testAPrepThatCannotBeMadeLeavesTheLineMissing(): void
    {
        $flour = self::ingredient('Flour', 0, Unit::Bag);
        $dough = self::ingredient('Dough', 0);
        $prep = self::prep('Dough', [[$flour, 6]], $dough, 12);

        $plan = (new Planner([$prep]))->plan(self::meal('Tart', [[$dough, 12]]));

        self::assertSame(['Dough'], self::names($plan->missing));
        self::assertSame([], $plan->preps);
    }

    public function testAPrepNeverMakesItself(): void
    {
        $starter = self::ingredient('Starter', 0, Unit::Jar);
        $prep = self::prep('Starter', [[$starter, 3]], $starter, 12);

        self::assertSame(['Starter'], self::names((new Planner([$prep]))->plan($prep)->missing));
    }

    public function testAPrepThatUsesAsMuchAsItMakesNeverHelps(): void
    {
        $starter = self::ingredient('Starter', 6, Unit::Jar);
        $prep = self::prep('Feed starter', [[$starter, 12]], $starter, 12);
        $sourdough = self::ingredient('Sourdough', 12, Unit::Jar);
        $other = self::prep('Refresh', [[$sourdough, 12]], $sourdough, 12);

        self::assertSame(['Starter'], self::names((new Planner([$prep]))->plan(self::meal('Bread', [[$starter, 12]]))->missing));
        self::assertSame(['Sourdough'], self::names((new Planner([$other]))->plan(self::meal('Loaf', [[$sourdough, 24]]))->missing));
    }

    public function testAVariantInStockStandsIn(): void
    {
        $zucchini = self::ingredient('Zucchini', 0);
        $frozen = self::ingredient('Frozen zucchini', 24);
        $frozen->makeVariantOf($zucchini);
        $gratin = self::meal('Gratin', [[$zucchini, 24]]);

        $plan = (new Planner([]))->plan($gratin);

        self::assertTrue($plan->isMakeable());
        self::assertTrue($plan->covers($gratin->lines()[0]));
        self::assertSame($frozen, $plan->uses($gratin->lines()[0]));
    }

    public function testTheLineIngredientComesBeforeItsVariants(): void
    {
        $zucchini = self::ingredient('Zucchini', 24);
        $frozen = self::ingredient('Frozen zucchini', 24);
        $frozen->makeVariantOf($zucchini);
        $gratin = self::meal('Gratin', [[$frozen, 24]]);

        self::assertSame($frozen, (new Planner([]))->plan($gratin)->uses($gratin->lines()[0]));
    }

    public function testAVariantInAnotherUnitCoversWithAnyStock(): void
    {
        $zucchini = self::ingredient('Zucchini', 0);
        $frozen = self::ingredient('Frozen zucchini', 1, Unit::Bag);
        $frozen->makeVariantOf($zucchini);

        self::assertTrue((new Planner([]))->plan(self::meal('Gratin', [[$zucchini, 36]]))->isMakeable());

        $frozen->setStock(Quantity::zero());
        self::assertFalse((new Planner([]))->plan(self::meal('Gratin', [[$zucchini, 36]]))->isMakeable());
    }

    public function testAVariantInTheSameUnitMustCoverTheAmount(): void
    {
        $zucchini = self::ingredient('Zucchini', 0);
        $frozen = self::ingredient('Frozen zucchini', 12);
        $frozen->makeVariantOf($zucchini);

        self::assertFalse((new Planner([]))->plan(self::meal('Gratin', [[$zucchini, 24]]))->isMakeable());
    }

    public function testAPrepOfAVariantCanBeMade(): void
    {
        $tomatoes = self::ingredient('Tomatoes', 48);
        $sauce = self::ingredient('Tomato sauce', 0, Unit::Jar);
        $homemade = self::ingredient('Homemade sauce', 0, Unit::Jar);
        $homemade->makeVariantOf($sauce);
        $prep = self::prep('Sauce', [[$tomatoes, 24]], $homemade, 12);
        $pasta = self::meal('Pasta', [[$sauce, 6]]);

        $plan = (new Planner([$prep]))->plan($pasta);

        self::assertSame([$prep], $plan->preps);
        self::assertSame($homemade, $plan->uses($pasta->lines()[0]));
    }

    /**
     * @param list<array{Ingredient, ?int}> $lines
     */
    private static function meal(string $name, array $lines): Recipe
    {
        return Recipe::meal($name, self::needs($lines), new \DateTimeImmutable());
    }

    /**
     * @param list<array{Ingredient, ?int}> $lines
     */
    private static function prep(string $name, array $lines, Ingredient $output, int $yield): Recipe
    {
        return Recipe::prep($name, self::needs($lines), $output, Quantity::ofTwelfths($yield), new \DateTimeImmutable());
    }

    /**
     * @param list<array{Ingredient, ?int}> $lines
     *
     * @return list<Need>
     */
    private static function needs(array $lines): array
    {
        return array_map(static fn (array $line): Need => new Need($line[0], null === $line[1] ? null : Quantity::ofTwelfths($line[1])), $lines);
    }

    /**
     * @param list<RecipeLine> $lines
     *
     * @return list<string>
     */
    private static function names(array $lines): array
    {
        return array_map(static fn (RecipeLine $line): string => $line->ingredient()->name(), $lines);
    }

    private static function ingredient(string $name, int $stock, Unit $unit = Unit::Piece, bool $manual = false): Ingredient
    {
        $ingredient = Ingredient::add($name, Location::Pantry, $unit, $manual, null, new \DateTimeImmutable());
        $ingredient->setStock(Quantity::ofTwelfths($stock));

        return $ingredient;
    }
}
