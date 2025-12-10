<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUser extends UserAction
{
    public function __invoke(User $user): void
    {
        DB::transaction(static function () use ($user) {
            $user->delete();
        });
    }
}
