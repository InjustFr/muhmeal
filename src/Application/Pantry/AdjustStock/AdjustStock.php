<?php

declare(strict_types=1);

namespace App\Application\Pantry\AdjustStock;

use Symfony\Component\Uid\Ulid;

final readonly class AdjustStock
{
    public function __construct(
        public Ulid $id,
        public int $by,
    ) {
    }
}
