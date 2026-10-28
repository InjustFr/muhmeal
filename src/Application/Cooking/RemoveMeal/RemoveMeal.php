<?php

declare(strict_types=1);

namespace App\Application\Cooking\RemoveMeal;

use Symfony\Component\Uid\Ulid;

final readonly class RemoveMeal
{
    public function __construct(
        public Ulid $id,
        public bool $giveBack,
    ) {
    }
}
