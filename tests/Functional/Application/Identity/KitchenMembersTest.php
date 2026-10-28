<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Identity;

use App\Application\Identity\KitchenMembers;
use App\Application\Identity\MemberView;
use App\Tests\Support\ActsAsUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class KitchenMembersTest extends KernelTestCase
{
    use ActsAsUser;

    public function testEveryoneWhoSignedInIsListedByDisplayName(): void
    {
        $zed = self::createUser(displayName: 'Zed');
        $adam = self::createUser(displayName: 'adam');
        $bea = self::createUser(displayName: 'Bea');

        $members = self::getContainer()->get(KitchenMembers::class)->all();

        self::assertEquals([
            new MemberView((string) $adam->id(), 'adam'),
            new MemberView((string) $bea->id(), 'Bea'),
            new MemberView((string) $zed->id(), 'Zed'),
        ], $members);
    }

    public function testAnEmptyKitchenHasNoMembers(): void
    {
        self::assertSame([], self::getContainer()->get(KitchenMembers::class)->all());
    }
}
