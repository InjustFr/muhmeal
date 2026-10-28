<?php

declare(strict_types=1);

namespace App\Application\Cooking;

final readonly class MealPage
{
    /**
     * @param list<MealView> $meals
     */
    public function __construct(
        public array $meals,
        public ?string $before,
    ) {
    }
}
