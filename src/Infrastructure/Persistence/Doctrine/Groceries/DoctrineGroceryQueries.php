<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Groceries;

use App\Application\Groceries\GroceryListView;
use App\Application\Groceries\GroceryQueries;
use App\Domain\Groceries\GroceryList;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineGroceryQueries implements GroceryQueries
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function open(): ?GroceryListView
    {
        $lists = $this->entityManager->createQueryBuilder()
            ->select('g', 'i', 'n', 'u')
            ->from(GroceryList::class, 'g')
            ->leftJoin('g.items', 'i')
            ->leftJoin('i.ingredient', 'n')
            ->innerJoin('g.startedBy', 'u')
            ->getQuery()
            ->getResult();
        $list = $lists[0] ?? null;

        return $list instanceof GroceryList ? GroceryListView::of($list) : null;
    }
}
