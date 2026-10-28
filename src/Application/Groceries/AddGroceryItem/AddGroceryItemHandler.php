<?php

declare(strict_types=1);

namespace App\Application\Groceries\AddGroceryItem;

use App\Application\Groceries\GroceryListView;
use App\Application\Transaction;
use App\Domain\Groceries\GroceryListRepository;
use App\Domain\Pantry\IngredientRepository;

final readonly class AddGroceryItemHandler
{
    public function __construct(
        private GroceryListRepository $lists,
        private IngredientRepository $ingredients,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddGroceryItem $command): GroceryListView
    {
        $list = $this->lists->open();
        $list->add($this->ingredients->get($command->ingredientId));
        $this->transaction->commit();

        return GroceryListView::of($list);
    }
}
