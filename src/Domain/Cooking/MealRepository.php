<?php

declare(strict_types=1);

namespace App\Domain\Cooking;

use Symfony\Component\Uid\Ulid;

interface MealRepository
{
    public function add(Meal $meal): void;

    public function remove(Meal $meal): void;

    public function get(Ulid $id): Meal;
}
