<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Cooking;

use App\Application\Cooking\EditRecipe\EditRecipe;
use App\Application\Cooking\EditRecipe\EditRecipeHandler;
use App\Application\Cooking\MakePrep\MakePrep;
use App\Application\Cooking\MakePrep\MakePrepHandler;
use App\Application\Cooking\RecipeLineInput;
use App\Application\Cooking\RecipeQueries;
use App\Application\Cooking\RecipeView;
use App\Application\Cooking\RemoveRecipe\RemoveRecipe;
use App\Application\Cooking\RemoveRecipe\RemoveRecipeHandler;
use App\Application\Cooking\UsedInput;
use App\Application\Cooking\WriteRecipe\WriteRecipe;
use App\Application\Cooking\WriteRecipe\WriteRecipeHandler;
use App\Application\Pantry\AddIngredient\AddIngredient;
use App\Application\Pantry\AddIngredient\AddIngredientHandler;
use App\Application\Pantry\IngredientQueries;
use App\Application\Pantry\IngredientView;
use App\Application\Pantry\RemoveIngredient\RemoveIngredient;
use App\Application\Pantry\RemoveIngredient\RemoveIngredientHandler;
use App\Domain\Cooking\Exception\EmptyRecipeName;
use App\Domain\Cooking\Exception\IngredientListedTwice;
use App\Domain\Cooking\Exception\NotAPrep;
use App\Domain\Cooking\Exception\RecipeNameTaken;
use App\Domain\Cooking\Exception\RecipeNeedsIngredient;
use App\Domain\Cooking\Exception\ZeroQuantity;
use App\Domain\Cooking\Exception\ZeroYield;
use App\Domain\Pantry\Exception\IngredientInUse;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Unit;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\FreezesClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class RecipeUseCasesTest extends KernelTestCase
{
    use FreezesClock;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testWriteAMealRecipe(): void
    {
        $pasta = $this->ingredient('Pasta', 24, Unit::Pack);
        $oil = $this->ingredient('Oil', 6, Unit::Bottle, manual: true);

        $view = $this->write('  Aglio e olio ', [$this->line($pasta, 12), $this->line($oil)]);

        self::assertTrue(Ulid::isValid($view->id));
        self::assertSame(['Aglio e olio', 'meal', null, true, [], 2], [$view->name, $view->makes, $view->output, $view->makeable, $view->missing, \count($view->lines)]);
        self::assertSame(
            [$pasta->id, 'Pasta', 'pack', false, 12, 24, true],
            [$view->lines[0]->ingredientId, $view->lines[0]->name, $view->lines[0]->unit, $view->lines[0]->manual, $view->lines[0]->amount, $view->lines[0]->stock, $view->lines[0]->covered],
        );
        self::assertSame([null, true, true], [$view->lines[1]->amount, $view->lines[1]->manual, $view->lines[1]->covered]);
    }

    public function testWriteAPrepRecipe(): void
    {
        $gluten = $this->ingredient('Gluten', 24, Unit::Bag);
        $seitan = $this->ingredient('Seitan', 0, Unit::Bag);

        $view = $this->write('Seitan', [$this->line($gluten, 12)], $seitan, 12);

        $output = $view->output;
        self::assertNotNull($output);
        self::assertSame('prep', $view->makes);
        self::assertSame([$seitan->id, 'Seitan', 'bag', 12, 0], [$output->ingredientId, $output->name, $output->unit, $output->yield, $output->stock]);
    }

    public function testWhatTheStockDoesNotCoverIsMissing(): void
    {
        $pasta = $this->ingredient('Pasta', 6);
        $cheese = $this->ingredient('Cheese', 0);
        $tomatoes = $this->ingredient('Tomatoes', 36);

        $view = $this->write('Pasta bake', [$this->line($pasta, 12), $this->line($cheese), $this->line($tomatoes, 12)]);

        self::assertSame([[$pasta->id, $cheese->id], false], [$view->missing, $view->makeable]);
        self::assertSame([false, false, true], array_map(static fn ($line): bool => $line->covered, $view->lines));
    }

    public function testWriteIsStoredForGoodWithTheLinesInOrder(): void
    {
        $pasta = $this->ingredient('Pasta', 24);
        $oil = $this->ingredient('Oil', 6);
        $garlic = $this->ingredient('Garlic', 12);
        $this->write('Aglio', [$this->line($oil), $this->line($garlic, 6), $this->line($pasta, 12)]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        [$view] = self::getContainer()->get(RecipeQueries::class)->all();

        self::assertSame(['Oil', 'Garlic', 'Pasta'], array_map(static fn ($line): string => $line->name, $view->lines));
        self::assertSame([null, 6, 12], array_map(static fn ($line): ?int => $line->amount, $view->lines));
    }

    public function testNamesAreUniqueIgnoringCaseAndPadding(): void
    {
        $pasta = $this->ingredient('Pasta', 12);
        $this->write('Pasta bake', [$this->line($pasta)]);

        $this->expectExceptionObject(new RecipeNameTaken('PASTA BAKE'));

        $this->write(' PASTA BAKE ', [$this->line($pasta)]);
    }

    public function testNameIsRequired(): void
    {
        $this->expectExceptionObject(new EmptyRecipeName());

        $this->write('  ', [$this->line($this->ingredient('Pasta', 12))]);
    }

    public function testItNeedsAtLeastOneIngredient(): void
    {
        $this->expectExceptionObject(new RecipeNeedsIngredient());

        $this->write('Nothing', []);
    }

    public function testAnIngredientIsListedOnce(): void
    {
        $pasta = $this->ingredient('Pasta', 12);

        $this->expectExceptionObject(new IngredientListedTwice('Pasta'));

        $this->write('Pasta pasta', [$this->line($pasta), $this->line($pasta, 12)]);
    }

    public function testAnAmountOfZeroIsRefused(): void
    {
        $pasta = $this->ingredient('Pasta', 12);

        $this->expectExceptionObject(new ZeroQuantity('Pasta'));

        $this->write('Pasta', [$this->line($pasta, 0)]);
    }

    public function testAPrepYieldMustBeAboveZero(): void
    {
        $gluten = $this->ingredient('Gluten', 12);
        $seitan = $this->ingredient('Seitan', 0);

        $this->expectExceptionObject(new ZeroYield());

        $this->write('Seitan', [$this->line($gluten)], $seitan, 0);
    }

    public function testAnUnknownIngredientIsNotFound(): void
    {
        $id = new Ulid();

        $this->expectExceptionObject(new NotFound('ingredient', (string) $id));

        self::getContainer()->get(WriteRecipeHandler::class)(new WriteRecipe('Ghost', [new RecipeLineInput((string) $id, null)]));
    }

    public function testEditRewritesNameLinesAndKind(): void
    {
        $pasta = $this->ingredient('Pasta', 24);
        $oil = $this->ingredient('Oil', 6);
        $garlic = $this->ingredient('Garlic', 12);
        $dish = $this->ingredient('Dish', 0);
        $recipe = $this->write('Pasta', [$this->line($pasta, 12), $this->line($oil)]);

        $view = $this->edit($recipe, 'Aglio', [$this->line($garlic), $this->line($pasta, 18)], $dish, 24);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        [$stored] = self::getContainer()->get(RecipeQueries::class)->all();

        self::assertSame($view->id, $stored->id);
        self::assertSame(['Aglio', 'prep', ['Garlic', 'Pasta'], [null, 18], 24], [$stored->name, $stored->makes, array_map(static fn ($line): string => $line->name, $stored->lines), array_map(static fn ($line): ?int => $line->amount, $stored->lines), $stored->output?->yield]);
    }

    public function testEditCanTurnAPrepBackIntoAMeal(): void
    {
        $gluten = $this->ingredient('Gluten', 12);
        $seitan = $this->ingredient('Seitan', 0);
        $recipe = $this->write('Seitan', [$this->line($gluten)], $seitan, 12);

        $view = $this->edit($recipe, 'Seitan', [$this->line($gluten)]);

        self::assertSame(['meal', null], [$view->makes, $view->output]);
    }

    public function testEditMayChangeTheCaseOfItsOwnName(): void
    {
        $pasta = $this->ingredient('Pasta', 12);
        $recipe = $this->write('pasta bake', [$this->line($pasta)]);

        self::assertSame('Pasta bake', $this->edit($recipe, 'Pasta bake', [$this->line($pasta)])->name);
    }

    public function testEditCannotTakeAnotherRecipesName(): void
    {
        $pasta = $this->ingredient('Pasta', 12);
        $this->write('Pasta bake', [$this->line($pasta)]);
        $other = $this->write('Soup', [$this->line($pasta)]);

        $this->expectExceptionObject(new RecipeNameTaken('pasta bake'));

        $this->edit($other, 'pasta bake', [$this->line($pasta)]);
    }

    public function testEditOfAnUnknownRecipeIsNotFound(): void
    {
        $id = new Ulid();

        $this->expectExceptionObject(new NotFound('recipe', (string) $id));

        self::getContainer()->get(EditRecipeHandler::class)(new EditRecipe($id, 'Ghost', []));
    }

    public function testRemoveDeletesTheRecipeAndKeepsTheIngredients(): void
    {
        $pasta = $this->ingredient('Pasta', 12);
        $recipe = $this->write('Pasta bake', [$this->line($pasta)]);
        $this->write('Soup', [$this->line($pasta)]);

        self::getContainer()->get(RemoveRecipeHandler::class)(new RemoveRecipe(Ulid::fromString($recipe->id)));
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame(['Soup'], array_map(static fn (RecipeView $view): string => $view->name, self::getContainer()->get(RecipeQueries::class)->all()));
        self::assertSame(['Pasta'], array_map(static fn (IngredientView $view): string => $view->name, self::getContainer()->get(IngredientQueries::class)->all()));
    }

    public function testRemoveOfAnUnknownRecipeIsNotFound(): void
    {
        $id = new Ulid();

        $this->expectExceptionObject(new NotFound('recipe', (string) $id));

        self::getContainer()->get(RemoveRecipeHandler::class)(new RemoveRecipe($id));
    }

    public function testMakePrepConsumesWhatWasUsedAndRestocksTheOutput(): void
    {
        $gluten = $this->ingredient('Gluten', 24, Unit::Bag);
        $spices = $this->ingredient('Spices', 12, Unit::Jar, manual: true);
        $seitan = $this->ingredient('Seitan', 0, Unit::Bag);
        $recipe = $this->write('Seitan', [$this->line($gluten, 12), $this->line($spices)], $seitan, 12);

        $view = $this->make($recipe, [new UsedInput($gluten->id, 18), new UsedInput($spices->id, 3)], 24);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame(24, $view->output?->stock);
        self::assertSame([6, 9, 24], array_map(static fn (IngredientView $ingredient): int => $ingredient->stock, self::stocks('Gluten', 'Spices', 'Seitan')));
    }

    public function testMakePrepNeverRefusesAShortage(): void
    {
        $gluten = $this->ingredient('Gluten', 6);
        $seitan = $this->ingredient('Seitan', 12);
        $recipe = $this->write('Seitan', [$this->line($gluten)], $seitan, 12);

        $this->make($recipe, [new UsedInput($gluten->id, 36)], 12);

        self::assertSame([0, 24], array_map(static fn (IngredientView $ingredient): int => $ingredient->stock, self::stocks('Gluten', 'Seitan')));
    }

    public function testMakePrepWritesNoMeal(): void
    {
        $gluten = $this->ingredient('Gluten', 24);
        $recipe = $this->write('Seitan', [$this->line($gluten)], $this->ingredient('Seitan', 0), 12);

        $this->make($recipe, [new UsedInput($gluten->id, 12)], 12);

        self::assertEquals(0, self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM meal'));
    }

    public function testMakePrepOfAMealRecipeIsRefused(): void
    {
        $recipe = $this->write('Pasta', [$this->line($this->ingredient('Pasta', 12))]);

        $this->expectExceptionObject(new NotAPrep('Pasta'));

        $this->make($recipe, [], 12);
    }

    public function testMakePrepNeedsAnAmountMade(): void
    {
        $gluten = $this->ingredient('Gluten', 12);
        $recipe = $this->write('Seitan', [$this->line($gluten)], $this->ingredient('Seitan', 0), 12);

        $this->expectExceptionObject(new ZeroYield());

        $this->make($recipe, [], 0);
    }

    public function testMakePrepOfAnUnknownRecipeIsNotFound(): void
    {
        $id = new Ulid();

        $this->expectExceptionObject(new NotFound('recipe', (string) $id));

        self::getContainer()->get(MakePrepHandler::class)(new MakePrep($id, [], 12));
    }

    public function testAMadePrepMakesItsDishesMakeable(): void
    {
        $gluten = $this->ingredient('Gluten', 24);
        $seitan = $this->ingredient('Seitan', 0);
        $prep = $this->write('Seitan', [$this->line($gluten, 12)], $seitan, 12);
        $stew = $this->write('Stew', [$this->line($seitan, 12)]);
        self::assertFalse($this->recipeNamed('Stew')->makeable);

        $this->make($prep, [new UsedInput($gluten->id, 12)], 12);

        self::assertTrue($this->recipeNamed('Stew')->makeable);
        self::assertSame($stew->id, $this->recipeNamed('Stew')->id);
    }

    public function testTheQueryListsByNameIgnoringCase(): void
    {
        $pasta = $this->ingredient('Pasta', 12);
        $this->write('zucchini soup', [$this->line($pasta)]);
        $this->write('Apple pie', [$this->line($pasta)]);
        $this->write('beans', [$this->line($pasta)]);

        self::assertSame(['Apple pie', 'beans', 'zucchini soup'], array_map(static fn (RecipeView $view): string => $view->name, self::getContainer()->get(RecipeQueries::class)->all()));
    }

    public function testTheQueryListsNothingWithoutRecipes(): void
    {
        self::assertSame([], self::getContainer()->get(RecipeQueries::class)->all());
    }

    public function testAnIngredientUsedByARecipeLineCannotBeRemoved(): void
    {
        $pasta = $this->ingredient('Pasta', 12);
        $this->write('Pasta bake', [$this->line($pasta)]);
        $this->write('Aglio', [$this->line($pasta)]);

        $this->expectExceptionObject(new IngredientInUse('Pasta', 'Aglio, Pasta bake'));

        $this->remove($pasta);
    }

    public function testAnIngredientMadeByAPrepCannotBeRemoved(): void
    {
        $gluten = $this->ingredient('Gluten', 12);
        $seitan = $this->ingredient('Seitan', 0);
        $this->write('Seitan', [$this->line($gluten)], $seitan, 12);

        $this->expectExceptionObject(new IngredientInUse('Seitan', 'Seitan'));

        $this->remove($seitan);
    }

    public function testAnIngredientIsFreeToGoOnceItsRecipesAreGone(): void
    {
        $pasta = $this->ingredient('Pasta', 12);
        $recipe = $this->write('Pasta bake', [$this->line($pasta)]);
        $this->ingredient('Rice', 12);
        self::getContainer()->get(RemoveRecipeHandler::class)(new RemoveRecipe(Ulid::fromString($recipe->id)));

        $this->remove($pasta);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame(['Rice'], array_map(static fn (IngredientView $view): string => $view->name, self::getContainer()->get(IngredientQueries::class)->all()));
    }

    public function testAnIngredientNoRecipeUsesCanBeRemoved(): void
    {
        $pasta = $this->ingredient('Pasta', 12);
        $rice = $this->ingredient('Rice', 12);
        $this->write('Pasta bake', [$this->line($pasta)]);

        $this->remove($rice);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame(['Pasta'], array_map(static fn (IngredientView $view): string => $view->name, self::getContainer()->get(IngredientQueries::class)->all()));
    }

    private function ingredient(string $name, int $stock, Unit $unit = Unit::Piece, bool $manual = false): IngredientView
    {
        return self::getContainer()->get(AddIngredientHandler::class)(new AddIngredient($name, Location::Pantry, $unit, $manual, null, $stock));
    }

    private function line(IngredientView $ingredient, ?int $amount = null): RecipeLineInput
    {
        return new RecipeLineInput($ingredient->id, $amount);
    }

    /**
     * @param list<RecipeLineInput> $lines
     */
    private function write(string $name, array $lines, ?IngredientView $output = null, ?int $yield = null): RecipeView
    {
        return self::getContainer()->get(WriteRecipeHandler::class)(new WriteRecipe($name, $lines, $output?->id, $yield));
    }

    /**
     * @param list<RecipeLineInput> $lines
     */
    private function edit(RecipeView $recipe, string $name, array $lines, ?IngredientView $output = null, ?int $yield = null): RecipeView
    {
        return self::getContainer()->get(EditRecipeHandler::class)(new EditRecipe(Ulid::fromString($recipe->id), $name, $lines, $output?->id, $yield));
    }

    /**
     * @param list<UsedInput> $used
     */
    private function make(RecipeView $recipe, array $used, int $made): RecipeView
    {
        return self::getContainer()->get(MakePrepHandler::class)(new MakePrep(Ulid::fromString($recipe->id), $used, $made));
    }

    private function remove(IngredientView $ingredient): void
    {
        self::getContainer()->get(RemoveIngredientHandler::class)(new RemoveIngredient(Ulid::fromString($ingredient->id)));
    }

    private function recipeNamed(string $name): RecipeView
    {
        foreach (self::getContainer()->get(RecipeQueries::class)->all() as $view) {
            if ($view->name === $name) {
                return $view;
            }
        }

        self::fail("No recipe named $name.");
    }

    /**
     * @return list<IngredientView>
     */
    private static function stocks(string ...$names): array
    {
        $byName = [];
        foreach (self::getContainer()->get(IngredientQueries::class)->all() as $view) {
            $byName[$view->name] = $view;
        }

        return array_map(static fn (string $name): IngredientView => $byName[$name], array_values($names));
    }
}
