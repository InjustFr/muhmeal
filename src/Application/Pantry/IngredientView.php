<?php

declare(strict_types=1);

namespace App\Application\Pantry;

use App\Domain\Pantry\Ingredient;

final readonly class IngredientView
{
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
    ) {
    }

    public static function of(Ingredient $ingredient): self
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
        );
    }
}
