<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ChangeUserPassword extends UserAction
{
    // Note: __invoke changes the password for a given user
    public function __invoke(User $user, string $newPassword): User
    {
        return DB::transaction(function () use ($user, $newPassword) {
            $user->password = Hash::make($newPassword);
            $user->save();

            return $user;
        });
    }
}
