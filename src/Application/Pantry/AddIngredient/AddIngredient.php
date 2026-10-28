<?php

declare(strict_types=1);

namespace App\Application\Pantry\AddIngredient;

use App\Domain\Pantry\Location;
use App\Domain\Pantry\Unit;

final readonly class AddIngredient
{
    public function __construct(
        public string $name,
        public Location $location,
        public Unit $unit,
        public bool $manual,
        public ?int $lowAt = null,
    ) {
    }
}
