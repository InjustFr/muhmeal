<?php

declare(strict_types=1);

namespace App\Application\Pantry\SetStock;

use App\Application\Pantry\IngredientView;
use App\Application\Transaction;
use App\Domain\Pantry\IngredientRepository;
use App\Domain\Pantry\Quantity;

final readonly class SetStockHandler
{
    public function __construct(
        private IngredientRepository $ingredients,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(SetStock $command): IngredientView
    {
        $ingredient = $this->ingredients->get($command->id);
        $ingredient->setStock(Quantity::ofTwelfths($command->stock));
        $this->transaction->commit();

        return IngredientView::of($ingredient);
    }
}
