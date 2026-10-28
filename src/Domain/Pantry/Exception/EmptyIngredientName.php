<?php

declare(strict_types=1);

namespace App\Domain\Pantry\Exception;

final class EmptyIngredientName extends InvalidIngredient
{
    public function __construct()
    {
        parent::__construct('ingredient.empty_name');
    }
}
