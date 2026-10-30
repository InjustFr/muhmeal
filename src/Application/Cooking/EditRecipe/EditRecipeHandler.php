<?php

declare(strict_types=1);

namespace App\Application\Cooking\EditRecipe;

use App\Application\Cooking\CookingIngredients;
use App\Application\Cooking\RecipeView;
use App\Application\Cooking\RecipeViews;
use App\Application\Transaction;
use App\Domain\Cooking\Exception\RecipeNameTaken;
use App\Domain\Cooking\RecipeRepository;
use App\Domain\Pantry\IngredientRepository;
use App\Domain\Pantry\Quantity;
use Symfony\Component\Uid\Ulid;

final readonly class EditRecipeHandler
{
    public function __construct(
        private RecipeRepository $recipes,
        private IngredientRepository $ingredients,
        private CookingIngredients $cooking,
        private RecipeViews $views,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditRecipe $command): RecipeView
    {
        $recipe = $this->recipes->get($command->id);
        if (null !== $this->recipes->named($command->name, $command->id)) {
            throw new RecipeNameTaken(trim($command->name));
        }

        $recipe->rewrite(
            $command->name,
            $this->cooking->needs($command->lines),
            null === $command->outputId ? null : $this->ingredients->get(Ulid::fromString($command->outputId)),
            null === $command->yield ? null : Quantity::ofTwelfths($command->yield),
        );
        $this->transaction->commit();

        return $this->views->of($recipe);
    }
}
