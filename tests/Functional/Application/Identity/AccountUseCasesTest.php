<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Identity;

use App\Application\Identity\SignIn\SignIn;
use App\Application\Identity\SignIn\SignInHandler;
use App\Domain\Identity\Exception\EmailAlreadyTaken;
use App\Domain\Identity\Exception\InvalidEmail;
use App\Domain\Identity\User;
use App\Domain\Identity\UserRepository;
use App\Tests\Support\FreezesClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountUseCasesTest extends KernelTestCase
{
    use FreezesClock;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testAFirstSignInCreatesTheUser(): void
    {
        $user = $this->signIn(new SignIn('account-1', 'Louis@Example.com', 'Louis'));

        self::assertSame(['account-1', 'louis@example.com', 'Louis'], [$user->accountId(), $user->email(), $user->displayName()]);
    }

    public function testAFirstSignInFallsBackOnTheEmail(): void
    {
        $user = $this->signIn(new SignIn('account-1', 'fern@example.com', '  '));

        self::assertSame('fern', $user->displayName());
    }

    public function testAnAccountWithoutEmailCannotJoin(): void
    {
        $this->expectExceptionObject(new InvalidEmail(''));

        $this->signIn(new SignIn('account-1', null));
    }

    public function testTheAccountIsFoundAgainAfterItsEmailChanges(): void
    {
        $first = $this->signIn(new SignIn('account-1', 'louis@example.com'));

        $again = $this->signIn(new SignIn('account-1', 'lou@example.com'));

        self::assertSame($first->id()->toBase32(), $again->id()->toBase32());
        self::assertSame('lou@example.com', $again->email());
    }

    public function testTheAccountIsFoundAgainWithoutEmail(): void
    {
        $first = $this->signIn(new SignIn('account-1', 'louis@example.com'));

        $again = $this->signIn(new SignIn('account-1', null));

        self::assertSame($first->id()->toBase32(), $again->id()->toBase32());
    }

    public function testAnEmailAlreadyUsedByAnotherAccountIsRefused(): void
    {
        $this->signIn(new SignIn('account-1', 'louis@example.com'));

        $this->expectExceptionObject(new EmailAlreadyTaken('louis@example.com'));

        $this->signIn(new SignIn('account-2', 'louis@example.com'));
    }

    public function testAnAccountCannotTakeTheEmailOfAnotherUser(): void
    {
        $this->signIn(new SignIn('account-1', 'louis@example.com'));
        $this->signIn(new SignIn('account-2', 'fern@example.com'));

        $this->expectExceptionObject(new EmailAlreadyTaken('louis@example.com'));

        $this->signIn(new SignIn('account-2', 'louis@example.com'));
    }

    private function signIn(SignIn $command): User
    {
        $user = self::getContainer()->get(SignInHandler::class)($command);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(UserRepository::class)->get($user->id());
    }
}
