<?php

declare(strict_types=1);

namespace App\Application\Cooking\RemoveRecipe;

use Symfony\Component\Uid\Ulid;

final readonly class RemoveRecipe
{
    public function __construct(public Ulid $id)
    {
    }
}
