<?php

declare(strict_types=1);

namespace App\Domain\Pantry\Exception;

final class VariantOfItself extends InvalidIngredient
{
    public function __construct(string $name)
    {
        parent::__construct('ingredient.variant_of_itself', ['name' => $name]);
    }
}
