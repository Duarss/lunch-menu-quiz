<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUser extends UserAction
{
    // Note: __invoke deletes the specified user from the database
    public function __invoke(User $user): void
    {
        if ($user) {
            User::where('id', $user->id)->delete();
        }
    }
}
