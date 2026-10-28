<?php

declare(strict_types=1);

namespace App\Application\Pantry\EditIngredient;

use App\Domain\Pantry\Location;
use App\Domain\Pantry\Unit;
use Symfony\Component\Uid\Ulid;

final readonly class EditIngredient
{
    public function __construct(
        public Ulid $id,
        public string $name,
        public Location $location,
        public Unit $unit,
        public bool $manual,
        public ?int $lowAt = null,
    ) {
    }
}
