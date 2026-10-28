<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Cooking;

use App\Application\Cooking\RecipeQueries;
use App\Application\Cooking\RecipeView;
use App\Domain\Cooking\Recipe;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRecipeQueries implements RecipeQueries
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function all(): array
    {
        $recipes = $this->entityManager->createQueryBuilder()
            ->select('r', 'l', 'i', 'o')
            ->from(Recipe::class, 'r')
            ->leftJoin('r.lines', 'l')
            ->leftJoin('l.ingredient', 'i')
            ->leftJoin('r.output', 'o')
            ->orderBy('LOWER(r.name)')
            ->addOrderBy('r.id')
            ->getQuery()
            ->getResult();

        return array_map(RecipeView::of(...), $recipes);
    }
}
