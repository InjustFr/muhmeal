<?php

declare(strict_types=1);

namespace App\Fixtures\Story;

use App\Domain\Cooking\Meal;
use App\Domain\Cooking\MealType;
use App\Domain\Cooking\Need;
use App\Domain\Cooking\Portion;
use App\Domain\Cooking\Recipe;
use App\Domain\Identity\User;
use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Quantity;
use App\Domain\Pantry\Unit;
use App\Fixtures\Factory\IngredientFactory;
use App\Fixtures\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Zenstruck\Foundry\Story;

final class DemoStory extends Story
{
    private const int UNIT = 12;

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(): void
    {
        $now = $this->clock->now();

        $demo = UserFactory::createOne(['accountId' => 'demo', 'email' => 'demo@muhmeal.local', 'displayName' => 'Demo', 'now' => $now]);
        $sam = UserFactory::createOne(['accountId' => 'sam', 'email' => 'sam@muhmeal.local', 'displayName' => 'Sam', 'now' => $now]);

        $kitchen = [];
        foreach ($this->kitchen() as [$name, $location, $unit, $stock, $manual]) {
            $kitchen[$name] = IngredientFactory::createOne(['name' => $name, 'location' => $location, 'unit' => $unit, 'stock' => $stock, 'manual' => $manual, 'now' => $now]);
        }

        $this->recipes($kitchen, $now);
        $this->pastMeals($kitchen, $demo, $sam, $now);
        $this->entityManager->flush();
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
            ['Leeks', Location::Fridge, Unit::Piece, self::UNIT, false],
            ['Milk', Location::Fridge, Unit::Carton, 21, false],
            ['Seitan', Location::Fridge, Unit::Bag, 0, false],
            ['Yoghurt', Location::Fridge, Unit::Tub, 0, false],
            ['Fish fingers', Location::Freezer, Unit::Box, self::UNIT, false],
            ['Frozen berries', Location::Freezer, Unit::Bag, 28, false],
            ['Peas', Location::Freezer, Unit::Bag, 8, false],
            ['Canned tomatoes', Location::Pantry, Unit::Can, 4 * self::UNIT, false],
            ['Flour', Location::Pantry, Unit::Bag, 18, false],
            ['Gluten', Location::Pantry, Unit::Bag, 2 * self::UNIT, false],
            ['Olive oil', Location::Pantry, Unit::Bottle, 10, true],
            ['Onions', Location::Pantry, Unit::Piece, 5 * self::UNIT, false],
            ['Pasta', Location::Pantry, Unit::Pack, 3 * self::UNIT, false],
            ['Rice', Location::Pantry, Unit::Bag, 3, false],
            ['Salt', Location::Pantry, Unit::Box, self::UNIT, true],
            ['Spices', Location::Pantry, Unit::Jar, 6, true],
            ['Water', Location::Pantry, Unit::Bottle, 2 * self::UNIT, true],
        ];
    }

    /**
     * @param array<string, Ingredient> $kitchen
     */
    private function recipes(array $kitchen, \DateTimeImmutable $now): void
    {
        $recipes = [
            Recipe::prep('Seitan', [
                $this->need($kitchen, 'Gluten', self::UNIT),
                $this->need($kitchen, 'Water'),
                $this->need($kitchen, 'Spices'),
            ], $kitchen['Seitan'], Quantity::ofTwelfths(self::UNIT), $now),
            Recipe::meal('Seitan stew', [
                $this->need($kitchen, 'Seitan', self::UNIT),
                $this->need($kitchen, 'Onions', self::UNIT),
                $this->need($kitchen, 'Canned tomatoes', self::UNIT),
                $this->need($kitchen, 'Olive oil', 3),
                $this->need($kitchen, 'Salt'),
            ], $now),
            Recipe::meal('Pasta al pomodoro', [
                $this->need($kitchen, 'Pasta', self::UNIT),
                $this->need($kitchen, 'Canned tomatoes', self::UNIT),
                $this->need($kitchen, 'Onions', self::UNIT),
                $this->need($kitchen, 'Olive oil', 3),
                $this->need($kitchen, 'Salt'),
            ], $now),
            Recipe::meal('Cheese omelette', [
                $this->need($kitchen, 'Eggs', 3 * self::UNIT),
                $this->need($kitchen, 'Cheddar', 3),
                $this->need($kitchen, 'Butter', 3),
            ], $now),
            Recipe::meal('Berry yoghurt bowl', [
                $this->need($kitchen, 'Yoghurt', self::UNIT),
                $this->need($kitchen, 'Frozen berries', 6),
            ], $now),
        ];

        foreach ($recipes as $recipe) {
            $this->entityManager->persist($recipe);
        }
    }

    /**
     * @param array<string, Ingredient> $kitchen
     */
    private function need(array $kitchen, string $name, ?int $twelfths = null): Need
    {
        return new Need($kitchen[$name], null === $twelfths ? null : Quantity::ofTwelfths($twelfths));
    }

    /**
     * @param array<string, Ingredient> $kitchen
     */
    private function pastMeals(array $kitchen, User $demo, User $sam, \DateTimeImmutable $now): void
    {
        $meals = [
            [1, MealType::Dinner, 'Pasta al pomodoro', [$demo, $sam], $demo, ['Pasta' => self::UNIT, 'Canned tomatoes' => self::UNIT, 'Onions' => self::UNIT]],
            [1, MealType::Lunch, 'Leftover pasta', [$sam], $sam, []],
            [2, MealType::Breakfast, 'Pancakes', [$demo, $sam], $sam, ['Flour' => 3, 'Eggs' => 2 * self::UNIT, 'Milk' => 3]],
            [4, MealType::Dinner, null, [$demo], $demo, ['Fish fingers' => self::UNIT, 'Peas' => 6]],
            [6, MealType::Snack, 'Frozen berries', [$sam], $sam, ['Frozen berries' => 3]],
        ];

        foreach ($meals as [$daysAgo, $type, $title, $eaters, $loggedBy, $used]) {
            $at = $now->modify("-$daysAgo days");
            $portions = [];
            foreach ($used as $name => $twelfths) {
                $portions[] = new Portion($kitchen[$name], Quantity::ofTwelfths($twelfths));
            }
            $meal = Meal::log($at, $type, $title, $eaters, $portions, $loggedBy, $at);
            $meal->giveBack();
            $this->entityManager->persist($meal);
        }
    }
}
