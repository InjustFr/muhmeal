<?php

declare(strict_types=1);

namespace App\Fixtures\Story;

use App\Fixtures\Factory\UserFactory;
use Psr\Clock\ClockInterface;
use Zenstruck\Foundry\Story;

final class DemoStory extends Story
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function build(): void
    {
        $now = $this->clock->now();

        UserFactory::createOne(['accountId' => 'demo', 'email' => 'demo@muhmeal.local', 'displayName' => 'Demo', 'now' => $now]);
        UserFactory::createOne(['accountId' => 'sam', 'email' => 'sam@muhmeal.local', 'displayName' => 'Sam', 'now' => $now]);
    }
}
