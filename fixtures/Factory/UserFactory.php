<?php

declare(strict_types=1);

namespace App\Fixtures\Factory;

use App\Domain\Identity\User;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<User> */
final class UserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        return [
            'accountId' => self::faker()->unique()->uuid(),
            'email' => self::faker()->unique()->safeEmail(),
            'displayName' => self::faker()->firstName(),
            'now' => new \DateTimeImmutable(),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('join')->disableHydration());
    }
}
