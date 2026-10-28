<?php

declare(strict_types=1);

namespace App\Application\Pantry\AdjustStock;

use App\Application\Pantry\IngredientView;
use App\Application\Pantry\IngredientViews;
use App\Application\Transaction;
use App\Domain\Pantry\IngredientRepository;

final readonly class AdjustStockHandler
{
    public function __construct(
        private IngredientRepository $ingredients,
        private IngredientViews $views,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AdjustStock $command): IngredientView
    {
        $ingredient = $this->ingredients->get($command->id);
        $ingredient->adjust($command->by);
        $this->transaction->commit();

        return $this->views->of($ingredient);
    }
}
