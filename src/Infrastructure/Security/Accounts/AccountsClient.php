<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Accounts;

use App\Application\Identity\SignIn\SignIn;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AccountsClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private AccountsConfiguration $configuration,
    ) {
    }

    public function signIn(string $code, string $verifier, string $redirectUri): AccountsSignIn
    {
        try {
            $tokens = $this->httpClient->request('POST', $this->configuration->tokenUrl, ['body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $verifier,
                'client_id' => $this->configuration->clientId,
                'client_secret' => $this->configuration->clientSecret,
            ]])->toArray();
            $accessToken = $tokens['access_token'] ?? null;
            if (!\is_string($accessToken) || '' === $accessToken) {
                throw new CustomUserMessageAuthenticationException('login.failed');
            }

            $claims = $this->httpClient->request('GET', $this->configuration->userinfoUrl, ['auth_bearer' => $accessToken])->toArray();
        } catch (ExceptionInterface $exception) {
            throw new CustomUserMessageAuthenticationException('login.failed', previous: $exception);
        }

        $accountId = $claims['sub'] ?? null;
        if (!\is_string($accountId) || '' === $accountId) {
            throw new CustomUserMessageAuthenticationException('login.failed');
        }

        return new AccountsSignIn(
            new SignIn($accountId, self::text($claims, 'email'), self::text($claims, 'name') ?? self::text($claims, 'preferred_username')),
            self::text($tokens, 'id_token'),
        );
    }

    /** @param array<mixed> $claims */
    private static function text(array $claims, string $claim): ?string
    {
        $value = $claims[$claim] ?? null;

        return \is_string($value) && '' !== trim($value) ? $value : null;
    }
}
