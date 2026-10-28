<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Exception;

final class EmptyGroceryList extends InvalidGroceries
{
    public function __construct()
    {
        parent::__construct('groceries.empty');
    }
}
