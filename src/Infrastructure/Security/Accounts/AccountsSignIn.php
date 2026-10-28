<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Accounts;

use App\Application\Identity\SignIn\SignIn;

final readonly class AccountsSignIn
{
    public function __construct(
        public SignIn $command,
        public ?string $idToken,
    ) {
    }
}
