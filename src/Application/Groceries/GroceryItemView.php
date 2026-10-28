<?php

declare(strict_types=1);

namespace App\Application\Groceries;

use App\Domain\Groceries\GroceryItem;

final readonly class GroceryItemView
{
    private function __construct(
        public string $id,
        public string $ingredientId,
        public string $name,
        public string $unit,
        public string $location,
        public int $stock,
        public bool $ticked,
    ) {
    }

    public static function of(GroceryItem $item): self
    {
        $ingredient = $item->ingredient();

        return new self(
            (string) $item->id(),
            (string) $ingredient->id(),
            $ingredient->name(),
            $ingredient->unit()->value,
            $ingredient->location()->value,
            $ingredient->stock()->twelfths(),
            $item->isTicked(),
        );
    }
}
