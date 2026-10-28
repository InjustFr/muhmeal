<?php

declare(strict_types=1);

namespace App\Application\Cooking;

use App\Domain\Cooking\MealLine;

final readonly class MealLineView
{
    private function __construct(
        public ?string $ingredientId,
        public string $name,
        public string $unit,
        public int $used,
    ) {
    }

    public static function of(MealLine $line): self
    {
        return new self(null === $line->ingredientId() ? null : (string) $line->ingredientId(), $line->name(), $line->unit()->value, $line->used()->twelfths());
    }
}
