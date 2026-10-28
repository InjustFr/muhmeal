<?php

declare(strict_types=1);

namespace App\Application\Pantry\AddIngredient;

use App\Application\Pantry\IngredientView;
use App\Application\Transaction;
use App\Domain\Pantry\Exception\IngredientNameTaken;
use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\IngredientRepository;
use App\Domain\Pantry\Quantity;
use Psr\Clock\ClockInterface;

final readonly class AddIngredientHandler
{
    public function __construct(
        private IngredientRepository $ingredients,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddIngredient $command): IngredientView
    {
        if (null !== $this->ingredients->named($command->name)) {
            throw new IngredientNameTaken(trim($command->name));
        }

        $ingredient = Ingredient::add(
            $command->name,
            $command->location,
            $command->unit,
            $command->manual,
            null === $command->lowAt ? null : Quantity::ofTwelfths($command->lowAt),
            $this->clock->now(),
        );
        $ingredient->setStock(Quantity::ofTwelfths($command->stock));
        $this->ingredients->add($ingredient);
        $this->transaction->commit();

        return IngredientView::of($ingredient);
    }
}
