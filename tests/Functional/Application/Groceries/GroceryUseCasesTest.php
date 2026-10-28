<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Groceries;

use App\Application\Cooking\RecipeLineInput;
use App\Application\Cooking\WriteRecipe\WriteRecipe;
use App\Application\Cooking\WriteRecipe\WriteRecipeHandler;
use App\Application\Groceries\AddGroceryItem\AddGroceryItem;
use App\Application\Groceries\AddGroceryItem\AddGroceryItemHandler;
use App\Application\Groceries\BoughtInput;
use App\Application\Groceries\GroceryListView;
use App\Application\Groceries\GroceryQueries;
use App\Application\Groceries\PutAwayGroceries\PutAwayGroceries;
use App\Application\Groceries\PutAwayGroceries\PutAwayGroceriesHandler;
use App\Application\Groceries\StartGroceryList\StartGroceryList;
use App\Application\Groceries\StartGroceryList\StartGroceryListHandler;
use App\Application\Groceries\TickGroceryItem\TickGroceryItem;
use App\Application\Groceries\TickGroceryItem\TickGroceryItemHandler;
use App\Application\Pantry\AddIngredient\AddIngredient;
use App\Application\Pantry\AddIngredient\AddIngredientHandler;
use App\Application\Pantry\IngredientQueries;
use App\Application\Pantry\IngredientView;
use App\Application\Pantry\RemoveIngredient\RemoveIngredient;
use App\Application\Pantry\RemoveIngredient\RemoveIngredientHandler;
use App\Domain\Cooking\Exception\IngredientListedTwice;
use App\Domain\Cooking\Exception\ZeroQuantity;
use App\Domain\Groceries\Exception\EmptyGroceryList;
use App\Domain\Groceries\Exception\GroceryListAlreadyOpen;
use App\Domain\Identity\User;
use App\Domain\Pantry\Exception\QuantityOutOfRange;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Unit;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class GroceryUseCasesTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;

    private User $louis;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
        $this->louis = self::createUser(displayName: 'Louis');
        self::actAs($this->louis);
    }

    public function testStartAListWithItsIngredientsUnticked(): void
    {
        $milk = $this->ingredient('Milk', 0, Unit::Carton, Location::Fridge);
        $eggs = $this->ingredient('Eggs', 24);

        $list = $this->start([$milk, $eggs]);

        self::assertTrue(Ulid::isValid($list->id));
        self::assertSame(['Louis', '2026-10-06T08:00:00+00:00'], [$list->startedBy->name, $list->startedAt]);
        self::assertSame(
            [[$eggs->id, 'Eggs', 'piece', 'pantry', 24, false], [$milk->id, 'Milk', 'carton', 'fridge', 0, false]],
            array_map(static fn ($item): array => [$item->ingredientId, $item->name, $item->unit, $item->location, $item->stock, $item->ticked], $list->items),
        );
    }

    public function testTheListIsStoredForGood(): void
    {
        $this->start([$this->ingredient('Milk', 0)]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $list = $this->open();

        self::assertNotNull($list);
        self::assertSame(['Milk'], array_map(static fn ($item): string => $item->name, $list->items));
    }

    public function testThereIsNoOpenListUntilOneIsStarted(): void
    {
        self::assertNull($this->open());
    }

    public function testAListNeedsAtLeastOneIngredient(): void
    {
        $this->expectExceptionObject(new EmptyGroceryList());

        $this->start([]);
    }

    public function testOnlyOneListCanBeOpen(): void
    {
        $milk = $this->ingredient('Milk', 0);
        $this->start([$milk]);

        $this->expectExceptionObject(new GroceryListAlreadyOpen());

        $this->start([$milk]);
    }

    public function testTheDatabaseRefusesASecondOpenList(): void
    {
        $milk = $this->ingredient('Milk', 0);
        $this->start([$milk]);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->expectException(UniqueConstraintViolationException::class);

        $entityManager->getConnection()->executeStatement(
            'INSERT INTO grocery_list (id, open, started_at, started_by_id) SELECT gen_random_uuid(), open, started_at, started_by_id FROM grocery_list',
        );
    }

    public function testStartingWithAnUnknownIngredientIsNotFound(): void
    {
        $unknown = new Ulid();

        $this->expectExceptionObject(new NotFound('ingredient', (string) $unknown));

        self::getContainer()->get(StartGroceryListHandler::class)(new StartGroceryList([(string) $unknown]));
    }

    public function testAnIngredientListedTwiceIsOneItem(): void
    {
        $milk = $this->ingredient('Milk', 0);

        self::assertCount(1, $this->start([$milk, $milk])->items);
    }

    public function testAddAnIngredientToTheOpenList(): void
    {
        $milk = $this->ingredient('Milk', 0);
        $eggs = $this->ingredient('Eggs', 24);
        $this->start([$milk]);

        $list = $this->add($eggs);

        self::assertSame(['Eggs', 'Milk'], array_map(static fn ($item): string => $item->name, $list->items));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertCount(2, $this->open()->items ?? []);
    }

    public function testAddingAnIngredientAlreadyListedDoesNothing(): void
    {
        $milk = $this->ingredient('Milk', 0);
        $list = $this->start([$milk]);
        $this->tick($list->items[0]->id, true);

        $list = $this->add($milk);

        self::assertSame([[$milk->id, true]], array_map(static fn ($item): array => [$item->ingredientId, $item->ticked], $list->items));
    }

    public function testAddNeedsAnOpenListAndAKnownIngredient(): void
    {
        $milk = $this->ingredient('Milk', 0);

        try {
            $this->add($milk);
            self::fail('There is no open list.');
        } catch (NotFound $notFound) {
            self::assertSame(['shared.not_found', 'grocery_list'], [$notFound->getMessage(), $notFound->parameters()['subject']]);
        }

        $this->start([$milk]);
        $unknown = new Ulid();

        $this->expectExceptionObject(new NotFound('ingredient', (string) $unknown));

        self::getContainer()->get(AddGroceryItemHandler::class)(new AddGroceryItem($unknown));
    }

    public function testTickingSetsAValueAndNeverToggles(): void
    {
        $list = $this->start([$this->ingredient('Milk', 0)]);
        $item = $list->items[0]->id;

        self::assertTrue($this->tick($item, true)->items[0]->ticked);
        self::assertTrue($this->tick($item, true)->items[0]->ticked);
        self::assertFalse($this->tick($item, false)->items[0]->ticked);
        self::assertFalse($this->tick($item, false)->items[0]->ticked);
    }

    public function testATickIsStoredForGood(): void
    {
        $list = $this->start([$this->ingredient('Milk', 0)]);
        $this->tick($list->items[0]->id, true);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertTrue($this->open()?->items[0]->ticked ?? false);
    }

    public function testTickingAnUnknownItemOrWithoutAListIsNotFound(): void
    {
        $unknown = new Ulid();

        try {
            $this->tick((string) $unknown, true);
            self::fail('There is no open list.');
        } catch (NotFound $notFound) {
            self::assertSame('grocery_list', $notFound->parameters()['subject']);
        }

        $this->start([$this->ingredient('Milk', 0)]);

        $this->expectExceptionObject(new NotFound('grocery_item', (string) $unknown));

        $this->tick((string) $unknown, true);
    }

    public function testPuttingAwayRestocksTickedItemsAndExtrasAndDeletesTheList(): void
    {
        $milk = $this->ingredient('Milk', 3, Unit::Carton);
        $eggs = $this->ingredient('Eggs', 0);
        $chives = $this->ingredient('Chives', 0);
        $this->start([$milk, $eggs]);

        $this->putAway([new BoughtInput($milk->id, 12), new BoughtInput($chives->id, 6)]);

        self::assertSame([15, 0, 6], $this->stocks('Milk', 'Eggs', 'Chives'));
        self::assertNull($this->open());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull($this->open());
    }

    public function testPuttingAwayNothingJustDiscardsTheList(): void
    {
        $milk = $this->ingredient('Milk', 3, Unit::Carton);
        $this->start([$milk]);

        $this->putAway([]);

        self::assertSame([3], $this->stocks('Milk'));
        self::assertNull($this->open());
    }

    public function testAFreshListCanBeStartedOnceTheOldOneIsPutAway(): void
    {
        $milk = $this->ingredient('Milk', 0);
        $this->start([$milk]);
        $this->putAway([]);

        self::assertCount(1, $this->start([$milk])->items);
    }

    public function testPuttingAwayWithoutAnOpenListIsNotFound(): void
    {
        try {
            $this->putAway([]);
            self::fail('There is no open list.');
        } catch (NotFound $notFound) {
            self::assertSame('grocery_list', $notFound->parameters()['subject']);
        }
    }

    public function testABrokenPutAwayChangesNothing(): void
    {
        $milk = $this->ingredient('Milk', 3, Unit::Carton);
        $this->start([$milk]);

        try {
            $this->putAway([new BoughtInput($milk->id, 12), new BoughtInput($milk->id, 12)]);
            self::fail('An ingredient is bought once.');
        } catch (IngredientListedTwice) {
        }

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([3], $this->stocks('Milk'));
        self::assertNotNull($this->open());
    }

    public function testABoughtAmountOfZeroIsRefused(): void
    {
        $milk = $this->ingredient('Milk', 3, Unit::Carton);
        $this->start([$milk]);

        $this->expectExceptionObject(new ZeroQuantity('Milk'));

        $this->putAway([new BoughtInput($milk->id, 0)]);
    }

    public function testPuttingAwayPastTheCeilingIsRefused(): void
    {
        $milk = $this->ingredient('Milk', 11988, Unit::Carton);
        $this->start([$milk]);

        $this->expectExceptionObject(new QuantityOutOfRange(999));

        $this->putAway([new BoughtInput($milk->id, 1)]);
    }

    public function testDeletingAnIngredientRemovesItFromTheList(): void
    {
        $milk = $this->ingredient('Milk', 0);
        $eggs = $this->ingredient('Eggs', 0);
        $this->start([$milk, $eggs]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::getContainer()->get(RemoveIngredientHandler::class)(new RemoveIngredient(Ulid::fromString($milk->id)));
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame(['Eggs'], array_map(static fn ($item): string => $item->name, $this->open()->items ?? []));
    }

    public function testIngredientsAreSuggestedWhenRunningLowUnlessHomemade(): void
    {
        $milk = $this->ingredient('Milk', 3, Unit::Carton);
        $cheese = $this->ingredient('Cheese', 36, Unit::Pack);
        $gluten = $this->ingredient('Gluten', 24, Unit::Bag);
        $seitan = $this->ingredient('Seitan', 0, Unit::Bag);
        self::getContainer()->get(WriteRecipeHandler::class)(new WriteRecipe('Seitan', [new RecipeLineInput($gluten->id, 12)], $seitan->id, 12));

        $views = [];
        foreach (self::getContainer()->get(IngredientQueries::class)->all() as $view) {
            $views[$view->name] = [$view->homemade, $view->suggested];
        }

        self::assertSame(
            ['Cheese' => [false, false], 'Gluten' => [false, false], 'Milk' => [false, true], 'Seitan' => [true, false]],
            $views,
        );
    }

    public function testWritesAnswerWithTheSuggestionFlags(): void
    {
        $milk = $this->ingredient('Milk', 0, Unit::Carton);

        self::assertSame([false, true], [$milk->homemade, $milk->suggested]);
    }

    private function ingredient(string $name, int $stock, Unit $unit = Unit::Piece, Location $location = Location::Pantry): IngredientView
    {
        return self::getContainer()->get(AddIngredientHandler::class)(new AddIngredient($name, $location, $unit, false, null, $stock));
    }

    /**
     * @param list<IngredientView> $ingredients
     */
    private function start(array $ingredients): GroceryListView
    {
        return self::getContainer()->get(StartGroceryListHandler::class)(new StartGroceryList(array_map(static fn (IngredientView $ingredient): string => $ingredient->id, $ingredients)));
    }

    private function add(IngredientView $ingredient): GroceryListView
    {
        return self::getContainer()->get(AddGroceryItemHandler::class)(new AddGroceryItem(Ulid::fromString($ingredient->id)));
    }

    private function tick(string $itemId, bool $ticked): GroceryListView
    {
        return self::getContainer()->get(TickGroceryItemHandler::class)(new TickGroceryItem(Ulid::fromString($itemId), $ticked));
    }

    /**
     * @param list<BoughtInput> $bought
     */
    private function putAway(array $bought): void
    {
        self::getContainer()->get(PutAwayGroceriesHandler::class)(new PutAwayGroceries($bought));
    }

    private function open(): ?GroceryListView
    {
        return self::getContainer()->get(GroceryQueries::class)->open();
    }

    /**
     * @return list<int>
     */
    private function stocks(string ...$names): array
    {
        $byName = [];
        foreach (self::getContainer()->get(IngredientQueries::class)->all() as $view) {
            $byName[$view->name] = $view->stock;
        }

        return array_map(static fn (string $name): int => $byName[$name], array_values($names));
    }
}
