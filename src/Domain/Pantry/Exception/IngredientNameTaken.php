<?php

declare(strict_types=1);

namespace App\Domain\Pantry\Exception;

final class IngredientNameTaken extends InvalidIngredient
{
    public function __construct(string $name)
    {
        parent::__construct('ingredient.name_taken', ['name' => $name]);
    }
}
