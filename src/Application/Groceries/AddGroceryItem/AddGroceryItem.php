<?php

declare(strict_types=1);

namespace App\Application\Groceries\AddGroceryItem;

use Symfony\Component\Uid\Ulid;

final readonly class AddGroceryItem
{
    public function __construct(public Ulid $ingredientId)
    {
    }
}
