<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Domain\Pantry\Quantity;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecipeOutputPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'id.invalid')]
        #[Assert\Ulid(message: 'id.invalid')]
        public string $ingredientId = '',
        #[Assert\Range(min: 1, max: Quantity::MAX_TWELFTHS, notInRangeMessage: 'quantity.positive')]
        public int $yield = 0,
    ) {
    }
}
