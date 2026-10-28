<?php

declare(strict_types=1);

namespace App\Domain\Pantry;

enum Location: string
{
    case Fridge = 'fridge';
    case Freezer = 'freezer';
    case Pantry = 'pantry';
}
