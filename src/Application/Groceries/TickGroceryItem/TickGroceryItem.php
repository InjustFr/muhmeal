<?php

declare(strict_types=1);

namespace App\Application\Groceries\TickGroceryItem;

use Symfony\Component\Uid\Ulid;

final readonly class TickGroceryItem
{
    public function __construct(
        public Ulid $itemId,
        public bool $ticked,
    ) {
    }
}
