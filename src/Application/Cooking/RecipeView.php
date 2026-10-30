<?php

declare(strict_types=1);

namespace App\Application\Cooking;

use App\Domain\Cooking\Planner;
use App\Domain\Cooking\Recipe;
use App\Domain\Cooking\RecipeLine;

final readonly class RecipeView
{
    /**
     * @param list<RecipeLineView> $lines
     * @param list<string>         $missing
     * @param list<PrepStepView>   $preps
     */
    private function __construct(
        public string $id,
        public string $name,
        public string $makes,
        public array $lines,
        public ?RecipeOutputView $output,
        public array $missing,
        public array $preps,
        public bool $makeable,
    ) {
    }

    public static function of(Recipe $recipe, Planner $planner): self
    {
        $output = $recipe->output();
        $yield = $recipe->yieldAmount();
        $plan = $planner->plan($recipe);

        return new self(
            (string) $recipe->id(),
            $recipe->name(),
            $recipe->isPrep() ? 'prep' : 'meal',
            array_map(static fn (RecipeLine $line): RecipeLineView => RecipeLineView::of($line, $plan), $recipe->lines()),
            null === $output || null === $yield ? null : RecipeOutputView::of($output, $yield),
            array_map(static fn (RecipeLine $line): string => (string) $line->ingredient()->id(), $plan->missing),
            array_map(PrepStepView::of(...), $plan->preps),
            $plan->isMakeable(),
        );
    }
}
