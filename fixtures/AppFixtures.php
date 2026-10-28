<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Fixtures\Story\DemoStory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        DemoStory::load();
    }
}
