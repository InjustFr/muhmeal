<?php

declare(strict_types=1);

namespace App\Application\Pantry\SetStock;

use Symfony\Component\Uid\Ulid;

final readonly class SetStock
{
    public function __construct(
        public Ulid $id,
        public int $stock,
    ) {
    }
}
