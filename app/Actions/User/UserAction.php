<?php

namespace App\Actions\User;

abstract class UserAction
{
    protected function normalizePayload(array $request, bool $forUpdate = false): array
    {
        $username = $request['username'] ?? null;
        if ($username !== null) {
            $username = trim((string) $username);
            if ($username === '') {
                $username = null;
            }
        }

        $password = $request['password'] ?? null;
        if (!$forUpdate && $password === null) {
            $password = $username;
        }

        return [
            'user' => [
                'name' => $request['name'] ?? null,
                'username' => $username,
                'password' => $password,
                'role' => $request['role'] ?? 'karyawan',
                'company_code' => $request['company_code'] ?? null,
            ],
            'company' => [
                'code' => $request['company_code'] ?? null,
                'name' => $request['name'] ?? null,
            ],
        ];
    }
}
