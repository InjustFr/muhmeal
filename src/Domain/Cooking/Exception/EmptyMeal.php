<?php

declare(strict_types=1);

namespace App\Domain\Cooking\Exception;

final class EmptyMeal extends InvalidCooking
{
    public function __construct()
    {
        parent::__construct('meal.empty');
    }
}
