<?php

declare(strict_types=1);

namespace App\Domain\Cooking\Exception;

final class ZeroYield extends InvalidCooking
{
    public function __construct()
    {
        parent::__construct('recipe.zero_yield');
    }
}
