<?php

declare(strict_types=1);

namespace App\Application\Identity;

final readonly class MemberView
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
