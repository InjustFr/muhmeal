<?php

declare(strict_types=1);

namespace App\Domain\Cooking;

use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Quantity;
use App\Domain\Pantry\Unit;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'meal_line')]
class MealLine
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Meal::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Meal $meal;

    #[ORM\ManyToOne(targetEntity: Ingredient::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Ingredient $ingredient;

    #[ORM\Column(length: Ingredient::MAX_NAME_LENGTH)]
    private string $name;

    #[ORM\Column(length: 16, enumType: Unit::class)]
    private Unit $unit;

    #[ORM\Embedded(class: Quantity::class)]
    private Quantity $used;

    #[ORM\Column]
    private int $position;

    public function __construct(Meal $meal, Portion $portion, int $position)
    {
        $this->id = new Ulid();
        $this->meal = $meal;
        $this->ingredient = $portion->ingredient;
        $this->name = $portion->ingredient->name();
        $this->unit = $portion->ingredient->unit();
        $this->used = $portion->amount;
        $this->position = $position;
    }

    public function giveBack(): void
    {
        $this->ingredient?->restock($this->used);
    }

    public function ingredientId(): ?Ulid
    {
        return $this->ingredient?->id();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function unit(): Unit
    {
        return $this->unit;
    }

    public function used(): Quantity
    {
        return $this->used;
    }
}
