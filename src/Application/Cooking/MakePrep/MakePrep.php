<?php

declare(strict_types=1);

namespace App\Application\Cooking\MakePrep;

use App\Application\Cooking\UsedInput;
use Symfony\Component\Uid\Ulid;

final readonly class MakePrep
{
    /**
     * @param list<UsedInput> $used
     */
    public function __construct(
        public Ulid $recipeId,
        public array $used,
        public int $made,
    ) {
    }
}
