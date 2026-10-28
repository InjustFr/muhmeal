<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Accounts;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AccountsSession
{
    private const string SESSION_KEY = '_accounts_id_token';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function remember(?string $idToken): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $idToken);
    }

    public function idToken(): ?string
    {
        $idToken = $this->requestStack->getSession()->get(self::SESSION_KEY);

        return \is_string($idToken) && '' !== $idToken ? $idToken : null;
    }
}
