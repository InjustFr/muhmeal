<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Security;

use App\Domain\Identity\UserRepository;
use App\Infrastructure\Security\SecurityCurrentUser;
use App\Tests\Support\ActsAsUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SecurityCurrentUserTest extends KernelTestCase
{
    use ActsAsUser;

    public function testTheSignedInUserIsTheCurrentUser(): void
    {
        $user = self::actAsNewUser();

        self::assertSame($user->id()->toBase32(), $this->currentUser()->get()->id()->toBase32());
    }

    public function testNobodyIsSignedInOutsideARequest(): void
    {
        $this->expectException(\LogicException::class);

        $this->currentUser()->get();
    }

    private function currentUser(): SecurityCurrentUser
    {
        return new SecurityCurrentUser(self::getContainer()->get('security.token_storage'), self::getContainer()->get(UserRepository::class));
    }
}
