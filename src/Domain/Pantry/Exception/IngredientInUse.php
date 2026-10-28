<?php

declare(strict_types=1);

namespace App\Domain\Pantry\Exception;

final class IngredientInUse extends InvalidIngredient
{
    public function __construct(string $name, string $recipes)
    {
        parent::__construct('ingredient.in_use', ['name' => $name, 'recipes' => $recipes]);
    }
}
