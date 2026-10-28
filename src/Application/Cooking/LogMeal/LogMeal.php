<?php

declare(strict_types=1);

namespace App\Application\Cooking\LogMeal;

use App\Application\Cooking\UsedInput;
use App\Domain\Cooking\MealType;

final readonly class LogMeal
{
    /**
     * @param list<string>    $eaterIds
     * @param list<UsedInput> $used
     */
    public function __construct(
        public ?string $title,
        public MealType $type,
        public \DateTimeImmutable $eatenOn,
        public array $eaterIds,
        public array $used,
    ) {
    }
}
