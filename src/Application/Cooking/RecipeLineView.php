<?php

declare(strict_types=1);

namespace App\Application\Cooking;

use App\Domain\Cooking\Plan;
use App\Domain\Cooking\RecipeLine;

final readonly class RecipeLineView
{
    private function __construct(
        public string $ingredientId,
        public string $name,
        public string $unit,
        public bool $manual,
        public ?int $amount,
        public int $stock,
        public bool $covered,
        public ?VariantView $variant,
    ) {
    }

    public static function of(RecipeLine $line, Plan $plan): self
    {
        $ingredient = $line->ingredient();
        $used = $plan->uses($line);

        return new self(
            (string) $ingredient->id(),
            $ingredient->name(),
            $ingredient->unit()->value,
            $ingredient->isManual(),
            $line->amount()?->twelfths(),
            $ingredient->stock()->twelfths(),
            $plan->covers($line),
            null === $used || $used === $ingredient ? null : VariantView::of($used),
        );
    }
}
