<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

final class InvalidDay extends DomainException
{
    public function __construct(string $day)
    {
        parent::__construct('shared.invalid_day', ['day' => $day]);
    }
}
