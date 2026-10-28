<?php

declare(strict_types=1);

namespace App\Application\Cooking\WriteRecipe;

use App\Application\Cooking\CookingIngredients;
use App\Application\Cooking\RecipeView;
use App\Application\Transaction;
use App\Domain\Cooking\Exception\RecipeNameTaken;
use App\Domain\Cooking\Recipe;
use App\Domain\Cooking\RecipeRepository;
use App\Domain\Pantry\IngredientRepository;
use App\Domain\Pantry\Quantity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class WriteRecipeHandler
{
    public function __construct(
        private RecipeRepository $recipes,
        private IngredientRepository $ingredients,
        private CookingIngredients $cooking,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(WriteRecipe $command): RecipeView
    {
        if (null !== $this->recipes->named($command->name)) {
            throw new RecipeNameTaken(trim($command->name));
        }

        $needs = $this->cooking->needs($command->lines);
        $recipe = null === $command->outputId
            ? Recipe::meal($command->name, $needs, $this->clock->now())
            : Recipe::prep($command->name, $needs, $this->ingredients->get(Ulid::fromString($command->outputId)), Quantity::ofTwelfths($command->yield ?? 0), $this->clock->now());
        $this->recipes->add($recipe);
        $this->transaction->commit();

        return RecipeView::of($recipe);
    }
}
