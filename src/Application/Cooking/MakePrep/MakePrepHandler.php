<?php

declare(strict_types=1);

namespace App\Application\Cooking\MakePrep;

use App\Application\Cooking\CookingIngredients;
use App\Application\Cooking\RecipeView;
use App\Application\Cooking\RecipeViews;
use App\Application\Transaction;
use App\Domain\Cooking\RecipeRepository;
use App\Domain\Pantry\Quantity;

final readonly class MakePrepHandler
{
    public function __construct(
        private RecipeRepository $recipes,
        private CookingIngredients $cooking,
        private RecipeViews $views,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(MakePrep $command): RecipeView
    {
        $recipe = $this->recipes->get($command->recipeId);
        $recipe->make($this->cooking->portions($command->used), Quantity::ofTwelfths($command->made));
        $this->transaction->commit();

        return $this->views->of($recipe);
    }
}
