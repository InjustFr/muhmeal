<?php

declare(strict_types=1);

namespace App\Presentation\Api\Pantry;

use App\Domain\Pantry\Quantity;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdjustStockPayload
{
    public function __construct(
        #[Assert\Range(min: -Quantity::MAX_TWELFTHS, max: Quantity::MAX_TWELFTHS, notInRangeMessage: 'adjustment.range')]
        #[Assert\NotEqualTo(value: 0, message: 'adjustment.zero')]
        public int $by = 0,
    ) {
    }
}
