<?php

declare(strict_types=1);

namespace App\Domain\Cooking\Exception;

final class MealNeedsEater extends InvalidCooking
{
    public function __construct()
    {
        parent::__construct('meal.needs_eater');
    }
}
