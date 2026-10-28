<?php

declare(strict_types=1);

namespace App\Application\Cooking\LogMeal;

use App\Application\Cooking\CookingIngredients;
use App\Application\Cooking\MealView;
use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Cooking\Meal;
use App\Domain\Cooking\MealRepository;
use App\Domain\Identity\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class LogMealHandler
{
    public function __construct(
        private MealRepository $meals,
        private UserRepository $users,
        private CookingIngredients $cooking,
        private CurrentUser $currentUser,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(LogMeal $command): MealView
    {
        $meal = Meal::log(
            $command->eatenOn,
            $command->type,
            $command->title,
            array_map(fn (string $id) => $this->users->get(Ulid::fromString($id)), $command->eaterIds),
            $this->cooking->portions($command->used),
            $this->currentUser->get(),
            $this->clock->now(),
        );
        $this->meals->add($meal);
        $this->transaction->commit();

        return MealView::of($meal);
    }
}
