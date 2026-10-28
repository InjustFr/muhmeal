<?php

declare(strict_types=1);

namespace App\Domain\Pantry\Exception;

final class QuantityOutOfRange extends InvalidIngredient
{
    public function __construct(int $maxUnits)
    {
        parent::__construct('ingredient.quantity_out_of_range', ['max' => $maxUnits]);
    }
}
