<?php

declare(strict_types=1);

namespace App\Domain\Pantry;

use App\Domain\Pantry\Exception\QuantityOutOfRange;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Quantity
{
    public const int TWELFTHS_PER_UNIT = 12;
    public const int MAX_UNITS = 999;
    public const int MAX_TWELFTHS = self::MAX_UNITS * self::TWELFTHS_PER_UNIT;

    private function __construct(
        #[ORM\Column(type: 'integer')]
        private int $twelfths,
    ) {
        if ($twelfths < 0 || $twelfths > self::MAX_TWELFTHS) {
            throw new QuantityOutOfRange(self::MAX_UNITS);
        }
    }

    public static function ofTwelfths(int $twelfths): self
    {
        return new self($twelfths);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->twelfths + $other->twelfths);
    }

    public function minus(self $other): self
    {
        return new self(max(0, $this->twelfths - $other->twelfths));
    }

    public function isZero(): bool
    {
        return 0 === $this->twelfths;
    }

    public function atLeast(self $other): bool
    {
        return $this->twelfths >= $other->twelfths;
    }

    public function twelfths(): int
    {
        return $this->twelfths;
    }
}
