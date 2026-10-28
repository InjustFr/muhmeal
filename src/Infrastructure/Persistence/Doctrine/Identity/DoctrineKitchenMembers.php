<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Identity;

use App\Application\Identity\KitchenMembers;
use App\Application\Identity\MemberView;
use App\Domain\Identity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineKitchenMembers implements KitchenMembers
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function all(): array
    {
        $users = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('LOWER(u.displayName)')
            ->addOrderBy('u.id')
            ->getQuery()
            ->getResult();

        return array_map(static fn (User $user): MemberView => new MemberView((string) $user->id(), $user->displayName()), $users);
    }
}
