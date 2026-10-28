<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Pantry;

use App\Application\Pantry\IngredientQueries;
use App\Application\Pantry\IngredientViews;
use App\Domain\Pantry\Ingredient;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineIngredientQueries implements IngredientQueries
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IngredientViews $views,
    ) {
    }

    public function all(): array
    {
        $ingredients = $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Ingredient::class, 'i')
            ->orderBy('LOWER(i.name)')
            ->addOrderBy('i.id')
            ->getQuery()
            ->getResult();

        return $this->views->all($ingredients);
    }
}
