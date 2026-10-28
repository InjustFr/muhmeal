<?php

declare(strict_types=1);

namespace App\Presentation\Api\Groceries;

final readonly class TickPayload
{
    public function __construct(public bool $ticked)
    {
    }
}
