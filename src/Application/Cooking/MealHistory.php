<?php

declare(strict_types=1);

namespace App\Application\Cooking;

interface MealHistory
{
    public function before(?\DateTimeImmutable $before): MealPage;
}
