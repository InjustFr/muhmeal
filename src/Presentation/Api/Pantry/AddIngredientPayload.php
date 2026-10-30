<?php

declare(strict_types=1);

namespace App\Presentation\Api\Pantry;

use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Location;
use App\Domain\Pantry\Quantity;
use App\Domain\Pantry\Unit;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddIngredientPayload
{
    /**
     * @param list<int> $months
     */
    public function __construct(
        public Location $location,
        public Unit $unit,
        #[Assert\NotBlank(message: 'name.required', normalizer: 'trim')]
        #[Assert\Length(max: Ingredient::MAX_NAME_LENGTH, maxMessage: 'name.too_long', normalizer: 'trim')]
        public string $name = '',
        public bool $manual = false,
        #[Assert\Range(min: 0, max: Quantity::MAX_TWELFTHS, notInRangeMessage: 'quantity.range')]
        public ?int $lowAt = null,
        #[Assert\Range(min: 0, max: Quantity::MAX_TWELFTHS, notInRangeMessage: 'quantity.range')]
        public int $stock = 0,
        #[Assert\All([new Assert\Range(min: 1, max: 12, notInRangeMessage: 'month.range')])]
        public array $months = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
        #[Assert\Ulid(message: 'id.invalid')]
        public ?string $variantOf = null,
    ) {
    }
}
