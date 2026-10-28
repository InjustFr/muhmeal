<?php

declare(strict_types=1);

namespace App\Domain\Cooking\Exception;

final class RecipeNameTooLong extends InvalidCooking
{
    public function __construct(int $max)
    {
        parent::__construct('recipe.name_too_long', ['max' => $max]);
    }
}
