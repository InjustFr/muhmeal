<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Exception;

final class GroceryListAlreadyOpen extends InvalidGroceries
{
    public function __construct()
    {
        parent::__construct('groceries.already_open');
    }
}
