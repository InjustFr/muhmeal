<?php

declare(strict_types=1);

namespace App\Application\Groceries;

final readonly class BoughtInput
{
    public function __construct(
        public string $ingredientId,
        public int $amount,
    ) {
    }
}
