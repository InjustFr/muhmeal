<?php

declare(strict_types=1);

namespace App\Domain\Pantry;

use App\Domain\Pantry\Exception\InvalidMonth;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Months
{
    private const int ALL = 0b111111111111;

    private function __construct(
        #[ORM\Column(type: 'integer', options: ['default' => self::ALL])]
        private int $bits,
    ) {
    }

    /**
     * @param list<int> $months
     */
    public static function of(array $months): self
    {
        $bits = 0;
        foreach ($months as $month) {
            if ($month < 1 || $month > 12) {
                throw new InvalidMonth($month);
            }
            $bits |= 1 << ($month - 1);
        }

        return new self($bits);
    }

    public static function all(): self
    {
        return new self(self::ALL);
    }

    public function includes(int $month): bool
    {
        return 0 !== ($this->bits & (1 << ($month - 1)));
    }

    /**
     * @return list<int>
     */
    public function toList(): array
    {
        return array_values(array_filter(range(1, 12), $this->includes(...)));
    }
}
