<?php

declare(strict_types=1);

namespace App\Application\Groceries;

interface GroceryQueries
{
    public function open(): ?GroceryListView;
}
