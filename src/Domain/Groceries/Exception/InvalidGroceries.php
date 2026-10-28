<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Exception;

use App\Domain\Shared\Exception\DomainException;

abstract class InvalidGroceries extends DomainException
{
}
