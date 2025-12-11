<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateKaryawanUser extends UserAction
{
    // Note: __invoke updates the specified karyawan user with the provided attributes
    public function __invoke(User $user, array $request): User
    {
        return DB::transaction(function () use ($user, $request) {
            $payload = $this->normalizePayload($request, true);
            $attributes = $payload['user'];

            if (array_key_exists('name', $attributes)) {
                $user->name = $attributes['name'];
            }

            if (!empty($attributes['password'])) {
                $user->password = Hash::make($attributes['password']);
            }

            if (array_key_exists('company_code', $attributes)) {
                $user->company_code = $attributes['company_code'];
            }

            $user->save();

            return $user;
        });
    }
}
