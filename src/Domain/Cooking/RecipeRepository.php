<?php

declare(strict_types=1);

namespace App\Domain\Cooking;

use App\Domain\Pantry\Ingredient;
use Symfony\Component\Uid\Ulid;

interface RecipeRepository
{
    public function add(Recipe $recipe): void;

    public function remove(Recipe $recipe): void;

    public function get(Ulid $id): Recipe;

    public function named(string $name, ?Ulid $except = null): ?Recipe;

    /**
     * @return list<Recipe>
     */
    public function using(Ingredient $ingredient): array;

    /**
     * @return list<Ulid>
     */
    public function outputIds(): array;
}
