<?php

declare(strict_types=1);

namespace App\Application\Cooking;

use App\Domain\Cooking\Planner;
use App\Domain\Cooking\Recipe;
use App\Domain\Cooking\RecipeRepository;

final readonly class RecipeViews
{
    public function __construct(private RecipeRepository $recipes)
    {
    }

    public function of(Recipe $recipe): RecipeView
    {
        return RecipeView::of($recipe, new Planner($this->recipes->preps()));
    }

    /**
     * @param list<Recipe> $recipes
     *
     * @return list<RecipeView>
     */
    public function all(array $recipes): array
    {
        $planner = new Planner($recipes);

        return array_map(static fn (Recipe $recipe): RecipeView => RecipeView::of($recipe, $planner), $recipes);
    }
}
