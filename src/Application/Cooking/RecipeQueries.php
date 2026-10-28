<?php

declare(strict_types=1);

namespace App\Application\Cooking;

interface RecipeQueries
{
    /**
     * @return list<RecipeView>
     */
    public function all(): array;
}
