<?php

declare(strict_types=1);

namespace App\Domain\Cooking\Exception;

final class MealTitleTooLong extends InvalidCooking
{
    public function __construct(int $max)
    {
        parent::__construct('meal.title_too_long', ['max' => $max]);
    }
}
