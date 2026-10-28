<?php

declare(strict_types=1);

namespace App\Presentation\Api\Groceries;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class StartGroceryListPayload
{
    /**
     * @param list<string> $ingredientIds
     */
    public function __construct(
        #[Assert\All([new Assert\Sequentially([new Assert\Type(type: 'string', message: 'id.invalid'), new Assert\Ulid(message: 'id.invalid')])])]
        public array $ingredientIds = [],
    ) {
    }
}
