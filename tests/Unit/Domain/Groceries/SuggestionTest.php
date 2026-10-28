<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Groceries;

use App\Domain\Groceries\Suggestion;
use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Quantity;
use App\Domain\Pantry\Unit;
use PHPUnit\Framework\TestCase;

final class SuggestionTest extends TestCase
{
    public function testAnIngredientRunningLowIsSuggested(): void
    {
        self::assertTrue((new Suggestion([]))->suggests(self::ingredient('Milk', 3)));
    }

    public function testAnIngredientThatIsOutIsSuggested(): void
    {
        self::assertTrue((new Suggestion([]))->suggests(self::ingredient('Milk', 0)));
    }

    public function testAnIngredientWithEnoughStockIsNotSuggested(): void
    {
        self::assertFalse((new Suggestion([]))->suggests(self::ingredient('Milk', 4)));
    }

    public function testAHomemadeIngredientIsNeverSuggestedEvenWhenOut(): void
    {
        $seitan = self::ingredient('Seitan', 0);
        $suggestion = new Suggestion([$seitan->id()]);

        self::assertTrue($suggestion->isHomemade($seitan));
        self::assertFalse($suggestion->suggests($seitan));
    }

    public function testOnlyTheOutputsOfPrepsAreHomemade(): void
    {
        $seitan = self::ingredient('Seitan', 0);
        $milk = self::ingredient('Milk', 0);
        $suggestion = new Suggestion([$seitan->id()]);

        self::assertFalse($suggestion->isHomemade($milk));
        self::assertTrue($suggestion->suggests($milk));
    }

    private static function ingredient(string $name, int $stock): Ingredient
    {
        $ingredient = Ingredient::add($name, Location::Fridge, Unit::Carton, false, null, new \DateTimeImmutable());
        $ingredient->setStock(Quantity::ofTwelfths($stock));

        return $ingredient;
    }
}
