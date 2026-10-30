<?php

declare(strict_types=1);

namespace App\Application\Pantry;

use App\Domain\Groceries\Suggestion;
use App\Domain\Pantry\Ingredient;

final readonly class IngredientView
{
    /**
     * @param list<int> $months
     */
    private function __construct(
        public string $id,
        public string $name,
        public string $location,
        public string $unit,
        public bool $manual,
        public int $stock,
        public int $lowAt,
        public bool $runningLow,
        public bool $out,
        public bool $homemade,
        public bool $suggested,
        public array $months,
        public ?string $variantOf,
    ) {
    }

    public static function of(Ingredient $ingredient, Suggestion $suggestion): self
    {
        return new self(
            (string) $ingredient->id(),
            $ingredient->name(),
            $ingredient->location()->value,
            $ingredient->unit()->value,
            $ingredient->isManual(),
            $ingredient->stock()->twelfths(),
            $ingredient->lowAt()->twelfths(),
            $ingredient->isRunningLow(),
            $ingredient->isOut(),
            $suggestion->isHomemade($ingredient),
            $suggestion->suggests($ingredient),
            $ingredient->months()->toList(),
            null === $ingredient->variantOf() ? null : (string) $ingredient->variantOf()->id(),
        );
    }
}
