<?php

declare(strict_types=1);

namespace App\Application\Cooking;

final readonly class RecipeLineInput
{
    public function __construct(
        public string $ingredientId,
        public ?int $amount,
    ) {
    }
}
