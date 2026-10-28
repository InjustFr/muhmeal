<?php

declare(strict_types=1);

namespace App\Application\Cooking;

use App\Domain\Cooking\Need;
use App\Domain\Cooking\Portion;
use App\Domain\Pantry\IngredientRepository;
use App\Domain\Pantry\Quantity;
use Symfony\Component\Uid\Ulid;

final readonly class CookingIngredients
{
    public function __construct(private IngredientRepository $ingredients)
    {
    }

    /**
     * @param list<RecipeLineInput> $lines
     *
     * @return list<Need>
     */
    public function needs(array $lines): array
    {
        return array_map(
            fn (RecipeLineInput $line): Need => new Need($this->ingredients->get(Ulid::fromString($line->ingredientId)), null === $line->amount ? null : Quantity::ofTwelfths($line->amount)),
            $lines,
        );
    }

    /**
     * @param list<UsedInput> $used
     *
     * @return list<Portion>
     */
    public function portions(array $used): array
    {
        return array_map(
            fn (UsedInput $input): Portion => new Portion($this->ingredients->get(Ulid::fromString($input->ingredientId)), Quantity::ofTwelfths($input->amount)),
            $used,
        );
    }
}
