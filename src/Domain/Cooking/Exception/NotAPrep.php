<?php

declare(strict_types=1);

namespace App\Domain\Cooking\Exception;

final class NotAPrep extends InvalidCooking
{
    public function __construct(string $name)
    {
        parent::__construct('recipe.not_a_prep', ['name' => $name]);
    }
}
