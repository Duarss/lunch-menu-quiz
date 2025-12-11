<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetUserPassword extends UserAction
{
    // Note: __invoke resets the user's password to their username
    public function __invoke(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->password = Hash::make($user->username);
            $user->save();

            return $user;
        });
    }
}
