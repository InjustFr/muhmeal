<?php

declare(strict_types=1);

namespace App\Domain\Cooking;

use App\Domain\Cooking\Exception\EmptyMeal;
use App\Domain\Cooking\Exception\MealNeedsEater;
use App\Domain\Cooking\Exception\MealTitleTooLong;
use App\Domain\Identity\User;
use App\Domain\Shared\Day;
use App\Domain\Shared\OptionalText;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'meal')]
#[ORM\Index(columns: ['eaten_on'])]
class Meal
{
    public const int MAX_TITLE_LENGTH = 80;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $eatenOn;

    #[ORM\Column(length: 16, enumType: MealType::class)]
    private MealType $type;

    #[ORM\Column(length: self::MAX_TITLE_LENGTH, nullable: true)]
    private ?string $title;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'meal_eater')]
    private Collection $eaters;

    /** @var Collection<int, MealLine> */
    #[ORM\OneToMany(targetEntity: MealLine::class, mappedBy: 'meal', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $loggedBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $loggedAt;

    /**
     * @param list<User>    $eaters
     * @param list<Portion> $portions
     */
    private function __construct(\DateTimeImmutable $eatenOn, MealType $type, ?string $title, array $eaters, array $portions, User $loggedBy, \DateTimeImmutable $now)
    {
        $title = OptionalText::of($title);
        if (null !== $title && mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new MealTitleTooLong(self::MAX_TITLE_LENGTH);
        }
        $distinct = [];
        foreach ($eaters as $eater) {
            $distinct[(string) $eater->id()] = $eater;
        }
        if ([] === $distinct) {
            throw new MealNeedsEater();
        }
        if (null === $title && [] === $portions) {
            throw new EmptyMeal();
        }
        Portion::assertDistinct($portions);

        $this->id = new Ulid();
        $this->eatenOn = Day::normalize($eatenOn);
        $this->type = $type;
        $this->title = $title;
        $this->eaters = new ArrayCollection(array_values($distinct));
        $this->lines = new ArrayCollection();
        $this->loggedBy = $loggedBy;
        $this->loggedAt = $now;

        foreach ($portions as $position => $portion) {
            $portion->ingredient->consume($portion->amount);
            $this->lines->add(new MealLine($this, $portion, $position));
        }
    }

    /**
     * @param list<User>    $eaters
     * @param list<Portion> $portions
     */
    public static function log(\DateTimeImmutable $eatenOn, MealType $type, ?string $title, array $eaters, array $portions, User $loggedBy, \DateTimeImmutable $now): self
    {
        return new self($eatenOn, $type, $title, $eaters, $portions, $loggedBy, $now);
    }

    public function giveBack(): void
    {
        foreach ($this->lines as $line) {
            $line->giveBack();
        }
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function eatenOn(): \DateTimeImmutable
    {
        return $this->eatenOn;
    }

    public function type(): MealType
    {
        return $this->type;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    /**
     * @return list<User>
     */
    public function eaters(): array
    {
        return array_values($this->eaters->toArray());
    }

    /**
     * @return list<MealLine>
     */
    public function lines(): array
    {
        return array_values($this->lines->toArray());
    }

    public function loggedBy(): User
    {
        return $this->loggedBy;
    }

    public function loggedAt(): \DateTimeImmutable
    {
        return $this->loggedAt;
    }
}
