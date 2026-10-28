<?php

declare(strict_types=1);

namespace App\Presentation\Api\Cooking;

use App\Domain\Cooking\Meal;
use App\Domain\Cooking\MealType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MealPayload
{
    /**
     * @param list<string>      $eaterIds
     * @param list<UsedPayload> $used
     */
    public function __construct(
        public MealType $type,
        #[Assert\NotBlank(message: 'date.required')]
        public string $eatenOn = '',
        #[Assert\Length(max: Meal::MAX_TITLE_LENGTH, maxMessage: 'title.too_long', normalizer: 'trim')]
        public ?string $title = null,
        #[Assert\All([new Assert\Ulid(message: 'id.invalid')])]
        public array $eaterIds = [],
        #[Assert\Valid]
        public array $used = [],
    ) {
    }
}
