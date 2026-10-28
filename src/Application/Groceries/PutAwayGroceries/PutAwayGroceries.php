<?php

declare(strict_types=1);

namespace App\Application\Groceries\PutAwayGroceries;

use App\Application\Groceries\BoughtInput;

final readonly class PutAwayGroceries
{
    /**
     * @param list<BoughtInput> $bought
     */
    public function __construct(public array $bought)
    {
    }
}
