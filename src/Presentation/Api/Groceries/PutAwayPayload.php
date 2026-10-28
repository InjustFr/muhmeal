<?php

declare(strict_types=1);

namespace App\Presentation\Api\Groceries;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PutAwayPayload
{
    /**
     * @param list<BoughtPayload> $bought
     */
    public function __construct(
        #[Assert\Valid]
        public array $bought = [],
    ) {
    }
}
