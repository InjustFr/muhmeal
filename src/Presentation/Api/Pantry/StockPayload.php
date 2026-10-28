<?php

declare(strict_types=1);

namespace App\Presentation\Api\Pantry;

use App\Domain\Pantry\Quantity;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class StockPayload
{
    public function __construct(
        #[Assert\Range(min: 0, max: Quantity::MAX_TWELFTHS, notInRangeMessage: 'quantity.range')]
        public int $stock,
    ) {
    }
}
