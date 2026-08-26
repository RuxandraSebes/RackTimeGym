<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function create(User $actor, Role $role): bool
    {
        return match ($actor->role) {
            Role::Owner => true,
            Role::Staff => $role === Role::Member,
            Role::Member => false,
        };
    }
}
