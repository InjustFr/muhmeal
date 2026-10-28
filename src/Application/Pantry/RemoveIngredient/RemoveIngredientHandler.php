<?php

declare(strict_types=1);

namespace App\Application\Pantry\RemoveIngredient;

use App\Application\Transaction;
use App\Domain\Pantry\IngredientRepository;

final readonly class RemoveIngredientHandler
{
    public function __construct(
        private IngredientRepository $ingredients,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemoveIngredient $command): void
    {
        $this->ingredients->remove($this->ingredients->get($command->id));
        $this->transaction->commit();
    }
}
