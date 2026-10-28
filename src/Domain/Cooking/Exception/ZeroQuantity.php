<?php

declare(strict_types=1);

namespace App\Domain\Cooking\Exception;

final class ZeroQuantity extends InvalidCooking
{
    public function __construct(string $ingredient)
    {
        parent::__construct('cooking.zero_quantity', ['ingredient' => $ingredient]);
    }
}
