<?php

declare(strict_types=1);

namespace App\Application\Cooking;

use App\Application\Identity\MemberView;
use App\Domain\Cooking\Meal;
use App\Domain\Identity\User;
use App\Domain\Shared\Day;

final readonly class MealView
{
    /**
     * @param list<MemberView>   $eaters
     * @param list<MealLineView> $lines
     */
    private function __construct(
        public string $id,
        public string $eatenOn,
        public string $type,
        public ?string $title,
        public array $eaters,
        public array $lines,
        public MemberView $loggedBy,
        public string $loggedAt,
    ) {
    }

    public static function of(Meal $meal): self
    {
        return new self(
            (string) $meal->id(),
            $meal->eatenOn()->format(Day::FORMAT),
            $meal->type()->value,
            $meal->title(),
            array_map(self::member(...), self::byName($meal->eaters())),
            array_map(MealLineView::of(...), $meal->lines()),
            self::member($meal->loggedBy()),
            $meal->loggedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param list<User> $users
     *
     * @return list<User>
     */
    private static function byName(array $users): array
    {
        usort($users, static fn (User $a, User $b): int => [mb_strtolower($a->displayName()), (string) $a->id()] <=> [mb_strtolower($b->displayName()), (string) $b->id()]);

        return $users;
    }

    private static function member(User $user): MemberView
    {
        return new MemberView((string) $user->id(), $user->displayName());
    }
}
