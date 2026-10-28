<?php

declare(strict_types=1);

namespace App\Domain\Groceries;

interface GroceryListRepository
{
    public function add(GroceryList $list): void;

    public function remove(GroceryList $list): void;

    public function isOpen(): bool;

    public function open(): GroceryList;
}
