<?php

declare(strict_types=1);

namespace App\Application\Cooking;

use App\Domain\Cooking\Recipe;

final readonly class PrepStepView
{
    private function __construct(
        public string $id,
        public string $name,
    ) {
    }

    public static function of(Recipe $prep): self
    {
        return new self((string) $prep->id(), $prep->name());
    }
}
