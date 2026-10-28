<?php

declare(strict_types=1);

namespace App\Presentation\Api\Groceries;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddGroceryItemPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'id.invalid')]
        #[Assert\Ulid(message: 'id.invalid')]
        public string $ingredientId = '',
    ) {
    }
}
