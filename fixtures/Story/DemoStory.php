<?php

declare(strict_types=1);

namespace App\Fixtures\Story;

use App\Domain\Pantry\Location;
use App\Domain\Pantry\Unit;
use App\Fixtures\Factory\IngredientFactory;
use App\Fixtures\Factory\UserFactory;
use Psr\Clock\ClockInterface;
use Zenstruck\Foundry\Story;

final class DemoStory extends Story
{
    private const int UNIT = 12;

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function build(): void
    {
        $now = $this->clock->now();

        UserFactory::createOne(['accountId' => 'demo', 'email' => 'demo@muhmeal.local', 'displayName' => 'Demo', 'now' => $now]);
        UserFactory::createOne(['accountId' => 'sam', 'email' => 'sam@muhmeal.local', 'displayName' => 'Sam', 'now' => $now]);

        foreach ($this->kitchen() as [$name, $location, $unit, $stock, $manual]) {
            IngredientFactory::createOne(['name' => $name, 'location' => $location, 'unit' => $unit, 'stock' => $stock, 'manual' => $manual, 'now' => $now]);
        }
    }

    /**
     * @return list<array{string, Location, Unit, int, bool}>
     */
    private function kitchen(): array
    {
        return [
            ['Butter', Location::Fridge, Unit::Pack, 6, false],
            ['Cheddar', Location::Fridge, Unit::Pack, 3, false],
            ['Eggs', Location::Fridge, Unit::Piece, 8 * self::UNIT, false],
            ['Leeks', Location::Fridge, Unit::Piece, 3 * self::UNIT, false],
            ['Milk', Location::Fridge, Unit::Carton, 21, false],
            ['Yoghurt', Location::Fridge, Unit::Tub, 0, false],
            ['Fish fingers', Location::Freezer, Unit::Box, self::UNIT, false],
            ['Frozen berries', Location::Freezer, Unit::Bag, 28, false],
            ['Peas', Location::Freezer, Unit::Bag, 8, false],
            ['Canned tomatoes', Location::Pantry, Unit::Can, 4 * self::UNIT, false],
            ['Flour', Location::Pantry, Unit::Bag, 18, false],
            ['Olive oil', Location::Pantry, Unit::Bottle, 10, true],
            ['Onions', Location::Pantry, Unit::Piece, 5 * self::UNIT, false],
            ['Pasta', Location::Pantry, Unit::Pack, 3 * self::UNIT, false],
            ['Rice', Location::Pantry, Unit::Bag, 15, false],
            ['Salt', Location::Pantry, Unit::Box, self::UNIT, true],
        ];
    }
}
