<?php

declare(strict_types=1);

namespace App\Domain\Cooking;

use App\Domain\Pantry\Ingredient;

final readonly class Plan
{
    /**
     * @param array<string, Ingredient> $uses
     * @param array<string, true>       $waiting
     * @param list<RecipeLine>          $missing
     * @param list<Recipe>              $preps
     */
    public function __construct(
        private array $uses,
        private array $waiting,
        public array $missing,
        public array $preps,
    ) {
    }

    public function uses(RecipeLine $line): ?Ingredient
    {
        return $this->uses[(string) $line->ingredient()->id()] ?? null;
    }

    public function covers(RecipeLine $line): bool
    {
        $id = (string) $line->ingredient()->id();

        return isset($this->uses[$id]) && !isset($this->waiting[$id]);
    }

    public function isMakeable(): bool
    {
        return [] === $this->missing && [] === $this->preps;
    }
}
