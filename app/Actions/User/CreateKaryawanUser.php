<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateKaryawanUser extends UserAction
{
    public function __invoke(array $request): User
    {
        return DB::transaction(function () use ($request) {
            $payload = $this->normalizePayload($request);
            $attributes = $payload['user'];

            $name = trim((string) ($attributes['name'] ?? ''));
            $username = trim((string) ($attributes['username'] ?? ''));

            if ($username === '') {
                throw ValidationException::withMessages([
                    'username' => 'Username wajib diisi.',
                ]);
            }

            $rawPassword = $attributes['password'] ?? $username;

            return User::create([
                'name' => $name,
                'username' => $username,
                'password' => Hash::make($rawPassword),
                'role' => 'karyawan',
                'company_code' => $attributes['company_code'] ?? null,
            ]);
        });
    }
}
