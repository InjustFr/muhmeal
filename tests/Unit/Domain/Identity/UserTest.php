<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Identity;

use App\Domain\Identity\Exception\EmptyDisplayName;
use App\Domain\Identity\Exception\InvalidEmail;
use App\Domain\Identity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEmailIsNormalized(): void
    {
        self::assertSame('louis@example.com', $this->user('  Louis@Example.COM ')->email());
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectExceptionObject(new InvalidEmail('not-an-email'));

        $this->user('not-an-email');
    }

    public function testDisplayNameIsRequired(): void
    {
        $this->expectExceptionObject(new EmptyDisplayName());

        User::join('account', 'louis@example.com', '  ', new \DateTimeImmutable());
    }

    public function testDisplayNameIsTrimmedAndCapped(): void
    {
        $user = User::join('account', 'louis@example.com', '  '.str_repeat('é', 80).'  ', new \DateTimeImmutable());

        self::assertSame(str_repeat('é', 60), $user->displayName());
    }

    public function testFollowsItsMossyleafAccount(): void
    {
        $user = $this->user();
        self::assertSame('account', $user->accountId());

        $user->changeEmail(' Lou@Example.com ');

        self::assertSame('lou@example.com', $user->email());
    }

    public function testChangedEmailMustBeValid(): void
    {
        $user = $this->user();

        $this->expectExceptionObject(new InvalidEmail('lou'));

        $user->changeEmail('lou');
    }

    private function user(string $email = 'louis@example.com'): User
    {
        return User::join('account', $email, 'Louis', new \DateTimeImmutable('2026-10-06 09:00'));
    }
}
