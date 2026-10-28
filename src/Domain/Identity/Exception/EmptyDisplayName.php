<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exception;

final class EmptyDisplayName extends InvalidUser
{
    public function __construct()
    {
        parent::__construct('user.empty_display_name');
    }
}
