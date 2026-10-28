<?php

declare(strict_types=1);

namespace App\Application\Identity;

interface KitchenMembers
{
    /**
     * @return list<MemberView>
     */
    public function all(): array;
}
