<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Cooking;

use App\Application\Cooking\MealHistory;
use App\Application\Cooking\MealPage;
use App\Application\Cooking\MealView;
use App\Domain\Cooking\Meal;
use App\Domain\Shared\Day;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMealHistory implements MealHistory
{
    private const int DAYS = 30;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function before(?\DateTimeImmutable $before): MealPage
    {
        $days = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT m.eatenOn')
            ->from(Meal::class, 'm')
            ->orderBy('m.eatenOn', 'DESC')
            ->setMaxResults(self::DAYS);
        if (null !== $before) {
            $days->where('m.eatenOn < :before')->setParameter('before', $before, 'date_immutable');
        }
        $found = $days->getQuery()->getSingleColumnResult();
        $last = end($found);
        if (!\is_string($last)) {
            return new MealPage([], null);
        }
        $oldest = Day::of($last);

        $meals = $this->entityManager->createQueryBuilder()
            ->select('m', 'l', 'e', 'u')
            ->from(Meal::class, 'm')
            ->leftJoin('m.lines', 'l')
            ->leftJoin('m.eaters', 'e')
            ->innerJoin('m.loggedBy', 'u')
            ->where('m.eatenOn >= :oldest')
            ->setParameter('oldest', $oldest, 'date_immutable')
            ->orderBy('m.eatenOn', 'DESC')
            ->addOrderBy('m.loggedAt', 'DESC')
            ->addOrderBy('m.id', 'DESC');
        if (null !== $before) {
            $meals->andWhere('m.eatenOn < :before')->setParameter('before', $before, 'date_immutable');
        }

        return new MealPage(
            array_map(MealView::of(...), $meals->getQuery()->getResult()),
            $this->hasMealsBefore($oldest) ? Day::format($oldest) : null,
        );
    }

    private function hasMealsBefore(\DateTimeImmutable $day): bool
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Meal::class, 'm')
            ->where('m.eatenOn < :day')
            ->setParameter('day', $day, 'date_immutable')
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
