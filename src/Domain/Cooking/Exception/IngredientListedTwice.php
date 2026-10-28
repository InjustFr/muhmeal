<?php

declare(strict_types=1);

namespace App\Domain\Cooking\Exception;

final class IngredientListedTwice extends InvalidCooking
{
    public function __construct(string $ingredient)
    {
        parent::__construct('cooking.ingredient_listed_twice', ['ingredient' => $ingredient]);
    }
}
