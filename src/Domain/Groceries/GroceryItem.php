<?php

declare(strict_types=1);

namespace App\Domain\Groceries;

use App\Domain\Pantry\Ingredient;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'grocery_item')]
#[ORM\UniqueConstraint(columns: ['list_id', 'ingredient_id'])]
class GroceryItem
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: GroceryList::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'list_id', nullable: false, onDelete: 'CASCADE')]
    private GroceryList $list;

    #[ORM\ManyToOne(targetEntity: Ingredient::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ingredient $ingredient;

    #[ORM\Column]
    private bool $ticked = false;

    public function __construct(GroceryList $list, Ingredient $ingredient)
    {
        $this->id = new Ulid();
        $this->list = $list;
        $this->ingredient = $ingredient;
    }

    public function tick(bool $ticked): void
    {
        $this->ticked = $ticked;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function ingredient(): Ingredient
    {
        return $this->ingredient;
    }

    public function isTicked(): bool
    {
        return $this->ticked;
    }
}
