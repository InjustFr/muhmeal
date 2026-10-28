<?php

declare(strict_types=1);

namespace App\Application\Groceries\StartGroceryList;

use App\Application\Groceries\GroceryListView;
use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Groceries\Exception\GroceryListAlreadyOpen;
use App\Domain\Groceries\GroceryList;
use App\Domain\Groceries\GroceryListRepository;
use App\Domain\Pantry\IngredientRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class StartGroceryListHandler
{
    public function __construct(
        private GroceryListRepository $lists,
        private IngredientRepository $ingredients,
        private CurrentUser $currentUser,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(StartGroceryList $command): GroceryListView
    {
        if ($this->lists->isOpen()) {
            throw new GroceryListAlreadyOpen();
        }

        $list = new GroceryList(
            array_map(fn (string $id) => $this->ingredients->get(Ulid::fromString($id)), $command->ingredientIds),
            $this->currentUser->get(),
            $this->clock->now(),
        );
        $this->lists->add($list);
        $this->transaction->commit();

        return GroceryListView::of($list);
    }
}
