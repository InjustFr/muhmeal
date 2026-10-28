<?php

declare(strict_types=1);

namespace App\Domain\Pantry\Exception;

use App\Domain\Shared\Exception\DomainException;

abstract class InvalidIngredient extends DomainException
{
}
