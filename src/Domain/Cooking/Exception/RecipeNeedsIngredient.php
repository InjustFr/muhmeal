<?php

declare(strict_types=1);

namespace App\Domain\Cooking\Exception;

final class RecipeNeedsIngredient extends InvalidCooking
{
    public function __construct()
    {
        parent::__construct('recipe.needs_ingredient');
    }
}
