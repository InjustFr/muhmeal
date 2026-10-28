<?php

declare(strict_types=1);

namespace App\Application\Pantry;

interface IngredientQueries
{
    /**
     * @return list<IngredientView>
     */
    public function all(): array;
}
