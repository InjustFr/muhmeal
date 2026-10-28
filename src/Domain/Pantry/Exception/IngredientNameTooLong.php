<?php

declare(strict_types=1);

namespace App\Domain\Pantry\Exception;

final class IngredientNameTooLong extends InvalidIngredient
{
    public function __construct(int $max)
    {
        parent::__construct('ingredient.name_too_long', ['max' => $max]);
    }
}
