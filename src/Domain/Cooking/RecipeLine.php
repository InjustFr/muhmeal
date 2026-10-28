<?php

declare(strict_types=1);

namespace App\Domain\Cooking;

use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Quantity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'recipe_line')]
#[ORM\UniqueConstraint(columns: ['recipe_id', 'ingredient_id'])]
class RecipeLine
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Recipe $recipe;

    #[ORM\ManyToOne(targetEntity: Ingredient::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Ingredient $ingredient;

    #[ORM\Column(nullable: true)]
    private ?int $amountTwelfths;

    #[ORM\Column]
    private int $position;

    public function __construct(Recipe $recipe, Ingredient $ingredient, ?Quantity $amount, int $position)
    {
        $this->id = new Ulid();
        $this->recipe = $recipe;
        $this->ingredient = $ingredient;
        $this->amountTwelfths = $amount?->twelfths();
        $this->position = $position;
    }

    public function measure(?Quantity $amount, int $position): void
    {
        $this->amountTwelfths = $amount?->twelfths();
        $this->position = $position;
    }

    public function isCovered(): bool
    {
        return $this->ingredient->covers($this->amount());
    }

    public function ingredient(): Ingredient
    {
        return $this->ingredient;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function amount(): ?Quantity
    {
        return null === $this->amountTwelfths ? null : Quantity::ofTwelfths($this->amountTwelfths);
    }
}
