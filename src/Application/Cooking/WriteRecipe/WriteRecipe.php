<?php

declare(strict_types=1);

namespace App\Application\Cooking\WriteRecipe;

use App\Application\Cooking\RecipeLineInput;

final readonly class WriteRecipe
{
    /**
     * @param list<RecipeLineInput> $lines
     */
    public function __construct(
        public string $name,
        public array $lines,
        public ?string $outputId = null,
        public ?int $yield = null,
    ) {
    }
}
