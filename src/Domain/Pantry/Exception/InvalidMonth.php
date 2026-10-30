<?php

declare(strict_types=1);

namespace App\Domain\Pantry\Exception;

final class InvalidMonth extends InvalidIngredient
{
    public function __construct(int $month)
    {
        parent::__construct('ingredient.invalid_month', ['month' => $month]);
    }
}
