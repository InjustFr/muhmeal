<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Domain\Cooking\Recipe;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecipePayload
{
    /**
     * @param list<RecipeLinePayload> $lines
     */
    public function __construct(
        #[Assert\NotBlank(message: 'name.required', normalizer: 'trim')]
        #[Assert\Length(max: Recipe::MAX_NAME_LENGTH, maxMessage: 'name.too_long', normalizer: 'trim')]
        public string $name = '',
        #[Assert\Valid]
        public array $lines = [],
        #[Assert\Valid]
        public ?RecipeOutputPayload $output = null,
    ) {
    }
}
