<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Identity\Exception\EmptyDisplayName;
use App\Domain\Identity\Exception\InvalidEmail;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 255, unique: true)]
    private string $accountId;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 60)]
    private string $displayName;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(string $accountId, string $email, string $displayName, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->accountId = $accountId;
        $this->email = self::normalizeEmail($email);
        $this->displayName = self::validDisplayName($displayName);
        $this->createdAt = $now;
    }

    public static function join(string $accountId, string $email, string $displayName, \DateTimeImmutable $now): self
    {
        return new self($accountId, $email, $displayName, $now);
    }

    public static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmail($email);
        }

        return $email;
    }

    public function changeEmail(string $email): void
    {
        $this->email = self::normalizeEmail($email);
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    private static function validDisplayName(string $displayName): string
    {
        $displayName = trim($displayName);
        if ('' === $displayName) {
            throw new EmptyDisplayName();
        }

        return mb_substr($displayName, 0, 60);
    }
}
