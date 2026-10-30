<?php

declare(strict_types=1);

namespace App\Application\Cooking;

use App\Domain\Pantry\Ingredient;

final readonly class VariantView
{
    private function __construct(
        public string $ingredientId,
        public string $name,
        public string $unit,
        public bool $manual,
        public int $stock,
    ) {
    }

    public static function of(Ingredient $ingredient): self
    {
        return new self((string) $ingredient->id(), $ingredient->name(), $ingredient->unit()->value, $ingredient->isManual(), $ingredient->stock()->twelfths());
    }
}
