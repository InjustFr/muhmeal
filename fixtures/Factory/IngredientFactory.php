<?php

declare(strict_types=1);

namespace App\Fixtures\Factory;

use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Quantity;
use App\Domain\Pantry\Unit;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Ingredient> */
final class IngredientFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Ingredient::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->word(),
            'location' => Location::Pantry,
            'unit' => Unit::Piece,
            'manual' => false,
            'lowAt' => null,
            'stock' => 0,
            'now' => new \DateTimeImmutable(),
        ];
    }

    protected function initialize(): static
    {
        return $this
            ->instantiateWith(Instantiator::namedConstructor('add')->disableHydration())
            ->afterInstantiate(static function (Ingredient $ingredient, array $parameters): void {
                $stock = $parameters['stock'] ?? 0;
                $ingredient->setStock(Quantity::ofTwelfths(\is_int($stock) ? $stock : 0));
            });
    }
}
