<?php

namespace App\Policies;

use App\Enum\Tier;
use App\Models\User;

class UserPolicy
{
    public function update(User $viewer, User $target): bool
    {
        if ($viewer->id === $target->id) {
            return false;
        }

        if ($viewer->tier === Tier::SiteAdmin) {
            return true;
        }

        return ! $target->isAdmin();
    }

    public function delete(User $viewer, User $target): bool
    {
        if ($viewer->id === $target->id) {
            return false;
        }

        if ($viewer->tier === Tier::SiteAdmin) {
            return true;
        }

        return ! $target->isAdmin();
    }
}
