<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Groceries;

use App\Domain\Groceries\GroceryList;
use App\Domain\Groceries\GroceryListRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineGroceryListRepository implements GroceryListRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(GroceryList $list): void
    {
        $this->entityManager->persist($list);
    }

    public function remove(GroceryList $list): void
    {
        $this->entityManager->remove($list);
    }

    public function isOpen(): bool
    {
        return null !== $this->find();
    }

    public function open(): GroceryList
    {
        return $this->find() ?? throw new NotFound('grocery_list', 'open');
    }

    private function find(): ?GroceryList
    {
        return $this->entityManager->getRepository(GroceryList::class)->findOneBy([]);
    }
}
