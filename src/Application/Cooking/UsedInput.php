<?php

declare(strict_types=1);

namespace App\Application\Cooking;

final readonly class UsedInput
{
    public function __construct(
        public string $ingredientId,
        public int $amount,
    ) {
    }
}
