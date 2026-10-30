<?php

declare(strict_types=1);

namespace App\Domain\Pantry\Exception;

final class NestedVariant extends InvalidIngredient
{
    public function __construct(string $name)
    {
        parent::__construct('ingredient.nested_variant', ['name' => $name]);
    }
}
