<?php

declare(strict_types=1);

namespace App\Domain\Pantry;

enum Unit: string
{
    case Piece = 'piece';
    case Bottle = 'bottle';
    case Jar = 'jar';
    case Pack = 'pack';
    case Bag = 'bag';
    case Box = 'box';
    case Carton = 'carton';
    case Can = 'can';
    case Tub = 'tub';

    public function defaultLowAt(): Quantity
    {
        return Quantity::ofTwelfths(self::Piece === $this ? Quantity::TWELFTHS_PER_UNIT : 3);
    }
}
