<?php

declare(strict_types=1);

namespace App\Application\Cooking;

use App\Domain\Cooking\Recipe;
use App\Domain\Cooking\RecipeLine;

final readonly class RecipeView
{
    /**
     * @param list<RecipeLineView> $lines
     * @param list<string>         $missing
     */
    private function __construct(
        public string $id,
        public string $name,
        public string $makes,
        public array $lines,
        public ?RecipeOutputView $output,
        public array $missing,
        public bool $makeable,
    ) {
    }

    public static function of(Recipe $recipe): self
    {
        $output = $recipe->output();
        $yield = $recipe->yieldAmount();
        $missing = array_map(static fn (RecipeLine $line): string => (string) $line->ingredient()->id(), $recipe->missing());

        return new self(
            (string) $recipe->id(),
            $recipe->name(),
            $recipe->isPrep() ? 'prep' : 'meal',
            array_map(RecipeLineView::of(...), $recipe->lines()),
            null === $output || null === $yield ? null : RecipeOutputView::of($output, $yield),
            $missing,
            [] === $missing,
        );
    }
}
