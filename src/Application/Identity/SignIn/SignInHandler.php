<?php

declare(strict_types=1);

namespace App\Application\Identity\SignIn;

use App\Application\Transaction;
use App\Domain\Identity\Exception\EmailAlreadyTaken;
use App\Domain\Identity\Exception\InvalidEmail;
use App\Domain\Identity\User;
use App\Domain\Identity\UserRepository;
use Psr\Clock\ClockInterface;

final readonly class SignInHandler
{
    public function __construct(
        private UserRepository $users,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(SignIn $command): User
    {
        $user = $this->users->findByAccountId($command->accountId) ?? $this->join($command);
        if (null !== $command->email) {
            $this->followEmail($user, $command->email);
        }
        $this->transaction->commit();

        return $user;
    }

    private function join(SignIn $command): User
    {
        $email = User::normalizeEmail($command->email ?? throw new InvalidEmail(''));
        if (null !== $this->users->findByEmail($email)) {
            throw new EmailAlreadyTaken($email);
        }

        $user = User::join($command->accountId, $email, $this->displayName($command, $email), $this->clock->now());
        $this->users->add($user);

        return $user;
    }

    private function followEmail(User $user, string $email): void
    {
        $email = User::normalizeEmail($email);
        if ($email === $user->email()) {
            return;
        }

        $holder = $this->users->findByEmail($email);
        if (null !== $holder && $holder !== $user) {
            throw new EmailAlreadyTaken($email);
        }
        $user->changeEmail($email);
    }

    private function displayName(SignIn $command, string $email): string
    {
        $name = trim($command->displayName ?? '');

        return '' === $name ? (string) strstr($email, '@', true) : $name;
    }
}
