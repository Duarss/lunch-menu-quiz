<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetUserPassword extends UserAction
{
    public function __invoke(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->password = Hash::make($user->username);
            $user->save();

            return $user;
        });
    }
}
