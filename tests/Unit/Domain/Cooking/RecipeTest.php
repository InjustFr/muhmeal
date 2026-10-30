<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Cooking;

use App\Domain\Cooking\Exception\EmptyRecipeName;
use App\Domain\Cooking\Exception\IngredientListedTwice;
use App\Domain\Cooking\Exception\NotAPrep;
use App\Domain\Cooking\Exception\RecipeNameTooLong;
use App\Domain\Cooking\Exception\RecipeNeedsIngredient;
use App\Domain\Cooking\Exception\ZeroQuantity;
use App\Domain\Cooking\Exception\ZeroYield;
use App\Domain\Cooking\Need;
use App\Domain\Cooking\Portion;
use App\Domain\Cooking\Recipe;
use App\Domain\Cooking\RecipeLine;
use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Quantity;
use App\Domain\Pantry\Unit;
use PHPUnit\Framework\TestCase;

final class RecipeTest extends TestCase
{
    public function testAMealRecipeIsNamedAndListsItsIngredientsInOrder(): void
    {
        $pasta = self::ingredient('Pasta', 24);
        $oil = self::ingredient('Oil', 6, manual: true);

        $recipe = Recipe::meal('  Aglio e olio ', [new Need($pasta, Quantity::ofTwelfths(12)), new Need($oil, null)], new \DateTimeImmutable());

        self::assertSame('Aglio e olio', $recipe->name());
        self::assertFalse($recipe->isPrep());
        self::assertNull($recipe->output());
        self::assertNull($recipe->yieldAmount());
        self::assertSame(['Pasta', 'Oil'], array_map(static fn (RecipeLine $line): string => $line->ingredient()->name(), $recipe->lines()));
        self::assertSame([12, null], array_map(static fn (RecipeLine $line): ?int => $line->amount()?->twelfths(), $recipe->lines()));
    }

    public function testAPrepRecipeMakesAnIngredient(): void
    {
        $gluten = self::ingredient('Gluten', 12);
        $seitan = self::ingredient('Seitan', 0, Unit::Bag);

        $recipe = Recipe::prep('Seitan', [new Need($gluten, Quantity::ofTwelfths(6))], $seitan, Quantity::ofTwelfths(12), new \DateTimeImmutable());

        self::assertTrue($recipe->isPrep());
        self::assertSame($seitan, $recipe->output());
        self::assertSame(12, $recipe->yieldAmount()?->twelfths());
    }

    public function testThePrepYieldMustBeAboveZero(): void
    {
        $this->expectExceptionObject(new ZeroYield());

        Recipe::prep('Seitan', [new Need(self::ingredient('Gluten', 12), null)], self::ingredient('Seitan', 0), Quantity::zero(), new \DateTimeImmutable());
    }

    public function testTheNameIsRequired(): void
    {
        $this->expectExceptionObject(new EmptyRecipeName());

        Recipe::meal('   ', [new Need(self::ingredient('Pasta', 12), null)], new \DateTimeImmutable());
    }

    public function testTheNameHasAtMostEightyCharacters(): void
    {
        self::assertSame(80, mb_strlen(Recipe::meal(str_repeat('é', 80), [new Need(self::ingredient('Pasta', 12), null)], new \DateTimeImmutable())->name()));

        $this->expectExceptionObject(new RecipeNameTooLong(80));

        Recipe::meal(str_repeat('é', 81), [new Need(self::ingredient('Pasta', 12), null)], new \DateTimeImmutable());
    }

    public function testItNeedsAtLeastOneIngredient(): void
    {
        $this->expectExceptionObject(new RecipeNeedsIngredient());

        Recipe::meal('Nothing', [], new \DateTimeImmutable());
    }

    public function testAnIngredientIsListedOnce(): void
    {
        $pasta = self::ingredient('Pasta', 12);

        $this->expectExceptionObject(new IngredientListedTwice('Pasta'));

        Recipe::meal('Pasta pasta', [new Need($pasta, null), new Need($pasta, Quantity::ofTwelfths(12))], new \DateTimeImmutable());
    }

    public function testAnAmountIsEitherSomeOrAboveZero(): void
    {
        $this->expectExceptionObject(new ZeroQuantity('Pasta'));

        new Need(self::ingredient('Pasta', 12), Quantity::zero());
    }

    public function testRewriteReplacesTheNameAndKeepsTheLinesItDoesNotDrop(): void
    {
        $pasta = self::ingredient('Pasta', 24);
        $oil = self::ingredient('Oil', 6);
        $garlic = self::ingredient('Garlic', 12);
        $recipe = Recipe::meal('Pasta', [new Need($pasta, Quantity::ofTwelfths(12)), new Need($oil, null)], new \DateTimeImmutable());
        [$pastaLine] = $recipe->lines();

        $recipe->rewrite(' Aglio ', [new Need($garlic, null), new Need($pasta, Quantity::ofTwelfths(18))], null, null);

        self::assertSame('Aglio', $recipe->name());
        self::assertSame(['Garlic', 'Pasta'], array_map(static fn (RecipeLine $line): string => $line->ingredient()->name(), $recipe->lines()));
        self::assertSame($pastaLine, $recipe->lines()[1]);
        self::assertSame(18, $pastaLine->amount()?->twelfths());
    }

    public function testRewriteCanTurnAMealIntoAPrepAndBack(): void
    {
        $gluten = self::ingredient('Gluten', 12);
        $seitan = self::ingredient('Seitan', 0);
        $recipe = Recipe::meal('Seitan', [new Need($gluten, null)], new \DateTimeImmutable());

        $recipe->rewrite('Seitan', [new Need($gluten, null)], $seitan, Quantity::ofTwelfths(12));
        self::assertTrue($recipe->isPrep());

        $recipe->rewrite('Seitan', [new Need($gluten, null)], null, null);
        self::assertFalse($recipe->isPrep());
        self::assertNull($recipe->yieldAmount());
    }

    public function testRewriteChangesNothingWhenARuleIsBroken(): void
    {
        $pasta = self::ingredient('Pasta', 12);
        $oil = self::ingredient('Oil', 12);
        $recipe = Recipe::meal('Pasta', [new Need($pasta, null)], new \DateTimeImmutable());

        try {
            $recipe->rewrite('Pasta', [new Need($oil, null), new Need($oil, null)], null, null);
            self::fail('A duplicated ingredient must be refused.');
        } catch (IngredientListedTwice) {
            self::assertSame(['Pasta'], array_map(static fn (RecipeLine $line): string => $line->ingredient()->name(), $recipe->lines()));
        }
    }

    public function testRewriteValidatesTheNameAndTheYield(): void
    {
        $gluten = self::ingredient('Gluten', 12);
        $recipe = Recipe::meal('Seitan', [new Need($gluten, null)], new \DateTimeImmutable());

        try {
            $recipe->rewrite(' ', [new Need($gluten, null)], null, null);
            self::fail('A blank name must be refused.');
        } catch (EmptyRecipeName) {
        }

        $this->expectExceptionObject(new ZeroYield());

        $recipe->rewrite('Seitan', [new Need($gluten, null)], self::ingredient('Seitan', 0), null);
    }

    public function testMakeConsumesTheUsedAmountsAndRestocksTheOutput(): void
    {
        $gluten = self::ingredient('Gluten', 24);
        $spices = self::ingredient('Spices', 12, manual: true);
        $seitan = self::ingredient('Seitan', 0, Unit::Bag);
        $recipe = Recipe::prep('Seitan', [new Need($gluten, Quantity::ofTwelfths(12)), new Need($spices, null)], $seitan, Quantity::ofTwelfths(12), new \DateTimeImmutable());

        $recipe->make([new Portion($gluten, Quantity::ofTwelfths(18)), new Portion($spices, Quantity::ofTwelfths(3))], Quantity::ofTwelfths(24));

        self::assertSame([6, 9, 24], [$gluten->stock()->twelfths(), $spices->stock()->twelfths(), $seitan->stock()->twelfths()]);
    }

    public function testMakeEmptiesWhatIsShortAndNeverRefuses(): void
    {
        $gluten = self::ingredient('Gluten', 6);
        $seitan = self::ingredient('Seitan', 0);
        $recipe = Recipe::prep('Seitan', [new Need($gluten, null)], $seitan, Quantity::ofTwelfths(12), new \DateTimeImmutable());

        $recipe->make([new Portion($gluten, Quantity::ofTwelfths(36))], Quantity::ofTwelfths(12));

        self::assertSame([0, 12], [$gluten->stock()->twelfths(), $seitan->stock()->twelfths()]);
    }

    public function testMakeNeedsAnAmountMadeAboveZero(): void
    {
        $gluten = self::ingredient('Gluten', 6);
        $recipe = Recipe::prep('Seitan', [new Need($gluten, null)], self::ingredient('Seitan', 0), Quantity::ofTwelfths(12), new \DateTimeImmutable());

        $this->expectExceptionObject(new ZeroYield());

        $recipe->make([], Quantity::zero());
    }

    public function testOnlyAPrepCanBeMade(): void
    {
        $recipe = Recipe::meal('Pasta', [new Need(self::ingredient('Pasta', 12), null)], new \DateTimeImmutable());

        $this->expectExceptionObject(new NotAPrep('Pasta'));

        $recipe->make([], Quantity::ofTwelfths(12));
    }

    public function testMakeUsesAnIngredientOnce(): void
    {
        $gluten = self::ingredient('Gluten', 24);
        $recipe = Recipe::prep('Seitan', [new Need($gluten, null)], self::ingredient('Seitan', 0), Quantity::ofTwelfths(12), new \DateTimeImmutable());

        $this->expectExceptionObject(new IngredientListedTwice('Gluten'));

        $recipe->make([new Portion($gluten, Quantity::ofTwelfths(6)), new Portion($gluten, Quantity::ofTwelfths(6))], Quantity::ofTwelfths(12));
    }

    public function testAPortionIsAboveZero(): void
    {
        $this->expectExceptionObject(new ZeroQuantity('Gluten'));

        new Portion(self::ingredient('Gluten', 12), Quantity::zero());
    }

    private static function ingredient(string $name, int $stock, Unit $unit = Unit::Piece, bool $manual = false): Ingredient
    {
        $ingredient = Ingredient::add($name, Location::Pantry, $unit, $manual, null, new \DateTimeImmutable());
        $ingredient->setStock(Quantity::ofTwelfths($stock));

        return $ingredient;
    }
}
