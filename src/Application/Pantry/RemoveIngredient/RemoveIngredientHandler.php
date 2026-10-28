<?php

declare(strict_types=1);

namespace App\Application\Pantry\RemoveIngredient;

use App\Application\Transaction;
use App\Domain\Cooking\Recipe;
use App\Domain\Cooking\RecipeRepository;
use App\Domain\Pantry\Exception\IngredientInUse;
use App\Domain\Pantry\IngredientRepository;

final readonly class RemoveIngredientHandler
{
    public function __construct(
        private IngredientRepository $ingredients,
        private RecipeRepository $recipes,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemoveIngredient $command): void
    {
        $ingredient = $this->ingredients->get($command->id);
        $recipes = $this->recipes->using($ingredient);
        if ([] !== $recipes) {
            throw new IngredientInUse($ingredient->name(), implode(', ', array_map(static fn (Recipe $recipe): string => $recipe->name(), $recipes)));
        }

        $this->ingredients->remove($ingredient);
        $this->transaction->commit();
    }
}
