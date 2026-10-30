<?php

declare(strict_types=1);

namespace App\Application\Pantry;

use App\Domain\Cooking\RecipeRepository;
use App\Domain\Groceries\Suggestion;
use App\Domain\Pantry\Ingredient;
use Psr\Clock\ClockInterface;

final readonly class IngredientViews
{
    public function __construct(
        private RecipeRepository $recipes,
        private ClockInterface $clock,
    ) {
    }

    public function of(Ingredient $ingredient): IngredientView
    {
        return IngredientView::of($ingredient, $this->suggestion());
    }

    /**
     * @param list<Ingredient> $ingredients
     *
     * @return list<IngredientView>
     */
    public function all(array $ingredients): array
    {
        $suggestion = $this->suggestion();

        return array_map(static fn (Ingredient $ingredient): IngredientView => IngredientView::of($ingredient, $suggestion), $ingredients);
    }

    private function suggestion(): Suggestion
    {
        return new Suggestion($this->recipes->outputIds(), (int) $this->clock->now()->format('n'));
    }
}
