<?php

declare(strict_types=1);

namespace App\Application\Groceries;

use App\Application\Identity\MemberView;
use App\Domain\Groceries\GroceryItem;
use App\Domain\Groceries\GroceryList;

final readonly class GroceryListView
{
    /**
     * @param list<GroceryItemView> $items
     */
    private function __construct(
        public string $id,
        public string $startedAt,
        public MemberView $startedBy,
        public array $items,
    ) {
    }

    public static function of(GroceryList $list): self
    {
        $items = $list->items();
        usort($items, static fn (GroceryItem $a, GroceryItem $b): int => [mb_strtolower($a->ingredient()->name()), (string) $a->id()] <=> [mb_strtolower($b->ingredient()->name()), (string) $b->id()]);

        return new self(
            (string) $list->id(),
            $list->startedAt()->format(\DateTimeInterface::ATOM),
            new MemberView((string) $list->startedBy()->id(), $list->startedBy()->displayName()),
            array_map(GroceryItemView::of(...), $items),
        );
    }
}
