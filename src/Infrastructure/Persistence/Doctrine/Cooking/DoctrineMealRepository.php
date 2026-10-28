<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Cooking;

use App\Domain\Cooking\Meal;
use App\Domain\Cooking\MealRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineMealRepository implements MealRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Meal $meal): void
    {
        $this->entityManager->persist($meal);
    }

    public function remove(Meal $meal): void
    {
        $this->entityManager->remove($meal);
    }

    public function get(Ulid $id): Meal
    {
        return $this->entityManager->find(Meal::class, $id) ?? throw new NotFound('meal', (string) $id);
    }
}
