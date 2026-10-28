<?php

declare(strict_types=1);

namespace App\Domain\Cooking\Exception;

final class RecipeNameTaken extends InvalidCooking
{
    public function __construct(string $name)
    {
        parent::__construct('recipe.name_taken', ['name' => $name]);
    }
}
