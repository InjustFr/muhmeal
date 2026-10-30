<?php

declare(strict_types=1);

namespace App\Domain\Groceries;

use App\Domain\Pantry\Ingredient;
use Symfony\Component\Uid\Ulid;

final readonly class Suggestion
{
    /** @var array<string, true> */
    private array $homemade;

    /**
     * @param list<Ulid> $homemade
     */
    public function __construct(array $homemade, private int $month)
    {
        $this->homemade = array_fill_keys(array_map(static fn (Ulid $id): string => (string) $id, $homemade), true);
    }

    public function isHomemade(Ingredient $ingredient): bool
    {
        return isset($this->homemade[(string) $ingredient->id()]);
    }

    public function suggests(Ingredient $ingredient): bool
    {
        return $ingredient->isRunningLow() && !$this->isHomemade($ingredient) && $ingredient->isBoughtIn($this->month);
    }
}
