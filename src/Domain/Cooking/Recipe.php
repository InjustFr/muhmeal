<?php

declare(strict_types=1);

namespace App\Domain\Cooking;

use App\Domain\Cooking\Exception\EmptyRecipeName;
use App\Domain\Cooking\Exception\IngredientListedTwice;
use App\Domain\Cooking\Exception\NotAPrep;
use App\Domain\Cooking\Exception\RecipeNameTooLong;
use App\Domain\Cooking\Exception\RecipeNeedsIngredient;
use App\Domain\Cooking\Exception\ZeroYield;
use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Quantity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'recipe')]
class Recipe
{
    public const int MAX_NAME_LENGTH = 80;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: self::MAX_NAME_LENGTH)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Ingredient::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Ingredient $output;

    #[ORM\Column(nullable: true)]
    private ?int $yieldTwelfths;

    /** @var Collection<int, RecipeLine> */
    #[ORM\OneToMany(targetEntity: RecipeLine::class, mappedBy: 'recipe', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<Need> $needs
     */
    private function __construct(string $name, array $needs, ?Ingredient $output, ?Quantity $yield, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->name = self::validName($name);
        $this->output = $output;
        $this->yieldTwelfths = null === $output ? null : self::validYield($yield)->twelfths();
        $this->lines = new ArrayCollection();
        $this->follow($needs);
        $this->createdAt = $now;
    }

    /**
     * @param list<Need> $needs
     */
    public static function meal(string $name, array $needs, \DateTimeImmutable $now): self
    {
        return new self($name, $needs, null, null, $now);
    }

    /**
     * @param list<Need> $needs
     */
    public static function prep(string $name, array $needs, Ingredient $output, Quantity $yield, \DateTimeImmutable $now): self
    {
        return new self($name, $needs, $output, $yield, $now);
    }

    /**
     * @param list<Need> $needs
     */
    public function rewrite(string $name, array $needs, ?Ingredient $output, ?Quantity $yield): void
    {
        $name = self::validName($name);
        $yieldTwelfths = null === $output ? null : self::validYield($yield)->twelfths();
        $this->follow($needs);
        $this->name = $name;
        $this->output = $output;
        $this->yieldTwelfths = $yieldTwelfths;
    }

    /**
     * @param list<Portion> $used
     */
    public function make(array $used, Quantity $made): void
    {
        if (null === $this->output) {
            throw new NotAPrep($this->name);
        }
        if ($made->isZero()) {
            throw new ZeroYield();
        }
        Portion::assertDistinct($used);

        foreach ($used as $portion) {
            $portion->ingredient->consume($portion->amount);
        }
        $this->output->restock($made);
    }

    /**
     * @return list<RecipeLine>
     */
    public function missing(): array
    {
        return array_values(array_filter($this->lines(), static fn (RecipeLine $line): bool => !$line->isCovered()));
    }

    public function isMakeable(): bool
    {
        return [] === $this->missing();
    }

    public function isPrep(): bool
    {
        return null !== $this->output;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function output(): ?Ingredient
    {
        return $this->output;
    }

    public function yieldAmount(): ?Quantity
    {
        return null === $this->yieldTwelfths ? null : Quantity::ofTwelfths($this->yieldTwelfths);
    }

    /**
     * @return list<RecipeLine>
     */
    public function lines(): array
    {
        $lines = array_values($this->lines->toArray());
        usort($lines, static fn (RecipeLine $a, RecipeLine $b): int => $a->position() <=> $b->position());

        return $lines;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param list<Need> $needs
     */
    private function follow(array $needs): void
    {
        if ([] === $needs) {
            throw new RecipeNeedsIngredient();
        }

        $wanted = [];
        foreach ($needs as $need) {
            $id = (string) $need->ingredient->id();
            if (isset($wanted[$id])) {
                throw new IngredientListedTwice($need->ingredient->name());
            }
            $wanted[$id] = $need;
        }

        $stale = [];
        foreach ($this->lines as $line) {
            $stale[(string) $line->ingredient()->id()] = $line;
        }

        foreach ($needs as $position => $need) {
            $id = (string) $need->ingredient->id();
            if (isset($stale[$id])) {
                $stale[$id]->measure($need->amount, $position);
                unset($stale[$id]);
            } else {
                $this->lines->add(new RecipeLine($this, $need->ingredient, $need->amount, $position));
            }
        }

        foreach ($stale as $line) {
            $this->lines->removeElement($line);
        }
    }

    private static function validName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new EmptyRecipeName();
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new RecipeNameTooLong(self::MAX_NAME_LENGTH);
        }

        return $name;
    }

    private static function validYield(?Quantity $yield): Quantity
    {
        if (null === $yield || $yield->isZero()) {
            throw new ZeroYield();
        }

        return $yield;
    }
}
