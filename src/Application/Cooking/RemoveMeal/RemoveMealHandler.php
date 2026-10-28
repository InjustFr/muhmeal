<?php

declare(strict_types=1);

namespace App\Application\Cooking\RemoveMeal;

use App\Application\Transaction;
use App\Domain\Cooking\MealRepository;

final readonly class RemoveMealHandler
{
    public function __construct(
        private MealRepository $meals,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemoveMeal $command): void
    {
        $meal = $this->meals->get($command->id);
        if ($command->giveBack) {
            $meal->giveBack();
        }
        $this->meals->remove($meal);
        $this->transaction->commit();
    }
}
