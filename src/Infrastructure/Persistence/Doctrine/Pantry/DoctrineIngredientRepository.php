<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Pantry;

use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\IngredientRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineIngredientRepository implements IngredientRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Ingredient $ingredient): void
    {
        $this->entityManager->persist($ingredient);
    }

    public function remove(Ingredient $ingredient): void
    {
        $this->entityManager->remove($ingredient);
    }

    public function get(Ulid $id): Ingredient
    {
        return $this->entityManager->find(Ingredient::class, $id) ?? throw new NotFound('ingredient', (string) $id);
    }

    public function named(string $name, ?Ulid $except = null): ?Ingredient
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Ingredient::class, 'i')
            ->where('LOWER(i.name) = :name')
            ->setParameter('name', mb_strtolower(trim($name)))
            ->setMaxResults(1);
        if (null !== $except) {
            $query->andWhere('i.id != :except')->setParameter('except', $except, UlidType::NAME);
        }

        $ingredient = $query->getQuery()->getOneOrNullResult();

        return $ingredient instanceof Ingredient ? $ingredient : null;
    }
}
