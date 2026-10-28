<?php

declare(strict_types=1);

namespace App\Application\Groceries\TickGroceryItem;

use App\Application\Groceries\GroceryListView;
use App\Application\Transaction;
use App\Domain\Groceries\GroceryListRepository;

final readonly class TickGroceryItemHandler
{
    public function __construct(
        private GroceryListRepository $lists,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(TickGroceryItem $command): GroceryListView
    {
        $list = $this->lists->open();
        $list->tick($command->itemId, $command->ticked);
        $this->transaction->commit();

        return GroceryListView::of($list);
    }
}
