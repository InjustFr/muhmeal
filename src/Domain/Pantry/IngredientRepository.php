<?php

declare(strict_types=1);

namespace App\Domain\Pantry;

use Symfony\Component\Uid\Ulid;

interface IngredientRepository
{
    public function add(Ingredient $ingredient): void;

    public function remove(Ingredient $ingredient): void;

    public function get(Ulid $id): Ingredient;

    public function named(string $name, ?Ulid $except = null): ?Ingredient;
}
