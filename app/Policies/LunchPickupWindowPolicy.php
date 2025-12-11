<?php

namespace App\Policies;

use App\Models\LunchPickupWindow;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LunchPickupWindowPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'bm']);
    }

    public function view(User $user): bool
    {
        return in_array($user->role, ['admin', 'bm']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'bm']);
    }

    public function update(User $user): bool
    {
        return in_array($user->role, ['admin', 'bm']);
    }

    public function delete(User $user): bool
    {
        return in_array($user->role, ['admin', 'bm']);
    }
}
