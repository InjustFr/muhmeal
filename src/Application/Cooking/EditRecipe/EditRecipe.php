<?php

declare(strict_types=1);

namespace App\Application\Cooking\EditRecipe;

use App\Application\Cooking\RecipeLineInput;
use Symfony\Component\Uid\Ulid;

final readonly class EditRecipe
{
    /**
     * @param list<RecipeLineInput> $lines
     */
    public function __construct(
        public Ulid $id,
        public string $name,
        public array $lines,
        public ?string $outputId = null,
        public ?int $yield = null,
    ) {
    }
}
