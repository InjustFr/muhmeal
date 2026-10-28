<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Groceries;

use App\Domain\Cooking\Exception\IngredientListedTwice;
use App\Domain\Cooking\Exception\ZeroQuantity;
use App\Domain\Cooking\Portion;
use App\Domain\Groceries\Exception\EmptyGroceryList;
use App\Domain\Groceries\GroceryItem;
use App\Domain\Groceries\GroceryList;
use App\Domain\Identity\User;
use App\Domain\Pantry\Exception\QuantityOutOfRange;
use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Quantity;
use App\Domain\Pantry\Unit;
use App\Domain\Shared\Exception\NotFound;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class GroceryListTest extends TestCase
{
    public function testAListStartsWithItsIngredientsUnticked(): void
    {
        $milk = self::ingredient('Milk');
        $eggs = self::ingredient('Eggs');
        $louis = self::user();

        $list = new GroceryList([$milk, $eggs], $louis, new \DateTimeImmutable('2026-10-06 08:00'));

        self::assertSame([[$milk->id(), false], [$eggs->id(), false]], array_map(static fn (GroceryItem $item): array => [$item->ingredient()->id(), $item->isTicked()], $list->items()));
        self::assertSame($louis, $list->startedBy());
        self::assertSame('2026-10-06 08:00', $list->startedAt()->format('Y-m-d H:i'));
    }

    public function testAListNeedsAtLeastOneIngredient(): void
    {
        $this->expectExceptionObject(new EmptyGroceryList());

        new GroceryList([], self::user(), new \DateTimeImmutable());
    }

    public function testAnIngredientListedTwiceIsOneItem(): void
    {
        $milk = self::ingredient('Milk');

        $list = new GroceryList([$milk, $milk], self::user(), new \DateTimeImmutable());

        self::assertCount(1, $list->items());
    }

    public function testAddingAnIngredientAlreadyListedDoesNothing(): void
    {
        $milk = self::ingredient('Milk');
        $eggs = self::ingredient('Eggs');
        $list = new GroceryList([$milk], self::user(), new \DateTimeImmutable());
        $list->tick($list->items()[0]->id(), true);

        $list->add($milk);
        $list->add($eggs);

        self::assertSame([[$milk->id(), true], [$eggs->id(), false]], array_map(static fn (GroceryItem $item): array => [$item->ingredient()->id(), $item->isTicked()], $list->items()));
    }

    public function testTickingSetsAValueAndNeverToggles(): void
    {
        $list = new GroceryList([self::ingredient('Milk')], self::user(), new \DateTimeImmutable());
        $id = $list->items()[0]->id();

        $list->tick($id, true);
        $list->tick($id, true);
        self::assertTrue($list->items()[0]->isTicked());

        $list->tick($id, false);
        $list->tick($id, false);
        self::assertFalse($list->items()[0]->isTicked());
    }

    public function testTickingAnUnknownItemIsNotFound(): void
    {
        $list = new GroceryList([self::ingredient('Milk')], self::user(), new \DateTimeImmutable());
        $unknown = new Ulid();

        $this->expectExceptionObject(new NotFound('grocery_item', (string) $unknown));

        $list->tick($unknown, true);
    }

    public function testPuttingAwayAddsEveryBoughtAmountToTheStock(): void
    {
        $milk = self::ingredient('Milk', 3);
        $extra = self::ingredient('Chives');
        $list = new GroceryList([$milk], self::user(), new \DateTimeImmutable());

        $list->putAway([new Portion($milk, Quantity::ofTwelfths(12)), new Portion($extra, Quantity::ofTwelfths(6))]);

        self::assertSame([15, 6], [$milk->stock()->twelfths(), $extra->stock()->twelfths()]);
    }

    public function testPuttingAwayNothingChangesNothing(): void
    {
        $milk = self::ingredient('Milk', 3);
        $list = new GroceryList([$milk], self::user(), new \DateTimeImmutable());

        $list->putAway([]);

        self::assertSame(3, $milk->stock()->twelfths());
    }

    public function testAnIngredientIsBoughtOnce(): void
    {
        $milk = self::ingredient('Milk');
        $list = new GroceryList([$milk], self::user(), new \DateTimeImmutable());

        $this->expectExceptionObject(new IngredientListedTwice('Milk'));

        $list->putAway([new Portion($milk, Quantity::ofTwelfths(12)), new Portion($milk, Quantity::ofTwelfths(12))]);
    }

    public function testABoughtAmountIsAboveZero(): void
    {
        $this->expectExceptionObject(new ZeroQuantity('Milk'));

        new Portion(self::ingredient('Milk'), Quantity::zero());
    }

    public function testPuttingAwayPastTheCeilingIsRefused(): void
    {
        $milk = self::ingredient('Milk', Quantity::MAX_TWELFTHS);
        $list = new GroceryList([$milk], self::user(), new \DateTimeImmutable());

        $this->expectExceptionObject(new QuantityOutOfRange(Quantity::MAX_UNITS));

        $list->putAway([new Portion($milk, Quantity::ofTwelfths(1))]);
    }

    private static function ingredient(string $name, int $stock = 0): Ingredient
    {
        $ingredient = Ingredient::add($name, Location::Fridge, Unit::Carton, false, null, new \DateTimeImmutable());
        $ingredient->setStock(Quantity::ofTwelfths($stock));

        return $ingredient;
    }

    private static function user(): User
    {
        return User::join('louis', 'louis@muhmeal.test', 'Louis', new \DateTimeImmutable());
    }
}
