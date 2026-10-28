<?php

declare(strict_types=1);

namespace App\Application\Cooking\RemoveRecipe;

use App\Application\Transaction;
use App\Domain\Cooking\RecipeRepository;

final readonly class RemoveRecipeHandler
{
    public function __construct(
        private RecipeRepository $recipes,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemoveRecipe $command): void
    {
        $this->recipes->remove($this->recipes->get($command->id));
        $this->transaction->commit();
    }
}
