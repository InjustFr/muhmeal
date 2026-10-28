<?php

declare(strict_types=1);

namespace App\Application\Pantry\RemoveIngredient;

use Symfony\Component\Uid\Ulid;

final readonly class RemoveIngredient
{
    public function __construct(public Ulid $id)
    {
    }
}
