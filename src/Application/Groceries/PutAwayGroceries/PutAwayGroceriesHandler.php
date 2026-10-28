<?php

declare(strict_types=1);

namespace App\Application\Groceries\PutAwayGroceries;

use App\Application\Groceries\BoughtInput;
use App\Application\Transaction;
use App\Domain\Cooking\Portion;
use App\Domain\Groceries\GroceryListRepository;
use App\Domain\Pantry\IngredientRepository;
use App\Domain\Pantry\Quantity;
use Symfony\Component\Uid\Ulid;

final readonly class PutAwayGroceriesHandler
{
    public function __construct(
        private GroceryListRepository $lists,
        private IngredientRepository $ingredients,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(PutAwayGroceries $command): void
    {
        $list = $this->lists->open();
        $list->putAway(array_map(
            fn (BoughtInput $input): Portion => new Portion($this->ingredients->get(Ulid::fromString($input->ingredientId)), Quantity::ofTwelfths($input->amount)),
            $command->bought,
        ));
        $this->lists->remove($list);
        $this->transaction->commit();
    }
}
