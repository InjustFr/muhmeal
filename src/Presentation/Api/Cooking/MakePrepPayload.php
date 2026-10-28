<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Domain\Pantry\Quantity;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MakePrepPayload
{
    /**
     * @param list<UsedPayload> $used
     */
    public function __construct(
        #[Assert\Range(min: 1, max: Quantity::MAX_TWELFTHS, notInRangeMessage: 'quantity.positive')]
        public int $made = 0,
        #[Assert\Valid]
        public array $used = [],
    ) {
    }
}
