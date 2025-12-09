<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserService
{
    /**
     * Get paginated karyawan users with optional search by username or name.
     */
    public function getMasterIndexData(array $params = []): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', 'karyawan')
            ->orWhere('role', 'vendor');
        $search = trim($params['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }
        $perPage = (int)($params['per_page'] ?? 15);
        return $query->orderBy('username')->paginate($perPage)->appends(['search' => $search]);
    }

    /**
     * Normalize incoming payload to a predictable structure, mirroring the legacy service style.
     */
    public function fetch(array $request, bool $forUpdate = false): object
    {
        $username = $request['username'] ?? null;
        if ($username !== null) {
            $username = trim((string) $username);
        }

        $password = $request['password'] ?? null;
        if (!$forUpdate && $password === null) {
            $password = $username;
        }

        return (object) [
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
            ]
        ];
    }

    /**
     * Create a new karyawan user (legacy-compatible alias: createKaryawan).
     */
    public function store(array $request): User
    {
        return DB::transaction(function () use ($request) {
            $name = trim((string) ($request['name'] ?? ''));
            $username = trim((string) ($request['username'] ?? ''));

            // Safety net tambahan: kalau username entah bagaimana kosong,
            // lempar ValidationException (tapi mestinya sudah ketahan di UserRequest).
            if ($username === '') {
                throw ValidationException::withMessages([
                    'username' => 'Username wajib diisi.',
                ]);
            }

            // Password default = username kalau tidak dikirim
            $rawPassword = $request['password'] ?? $username;

            $attributes = [
                'name'         => $name,
                'username'     => $username,
                'password'     => Hash::make($rawPassword),
                'role'         => 'karyawan',
                'company_code' => $request['company_code'] ?? null,
            ];

            return User::create($attributes);
        });
    }

    /**
     * Update an existing karyawan user (legacy-compatible alias: updateKaryawan).
     */
    public function update(User $user, array $request): User
    {
        return DB::transaction(function () use ($user, $request) {
            $payload = $this->fetch($request, true);
            $attributes = $payload->user;

            // Username is read-only now: ignore any incoming username just in case.
            unset($attributes['username']);

            $user->name = $attributes['name'];

            if (!empty($attributes['password'])) {
                $user->password = Hash::make($attributes['password']);
            }

            // Always keep role locked as karyawan on this flow.
            // if ($user->role !== 'karyawan') {
            //     $user->role = 'karyawan';
            // }

            if (array_key_exists('company_code', $attributes)) {
                $user->company_code = $attributes['company_code'];
            }

            $user->save();

            return $user;
        });
    }

    /**
     * Remove a karyawan user (legacy-compatible alias: deleteKaryawan).
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->delete();
        });
    }

    /**
     * Reset a karyawan password back to their username.
     */
    public function resetPassword(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->password = Hash::make($user->username);
            $user->save();

            return $user;
        });
    }

    /**
     * Backwards compatible wrappers.
     */
    public function createKaryawan(array $request): User
    {
        return $this->store($request);
    }

    public function updateKaryawan(string $username, array $request): User
    {
        $user = User::where('username', $username)->firstOrFail();
        return $this->update($user, $request);
    }

    public function deleteKaryawan(string $username): void
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->delete($user);
    }

    /**
     * Allow a karyawan to change their own password.
     */
    public function changeOwnPassword(User $user, string $newPassword): User
    {
        return DB::transaction(function () use ($user, $newPassword) {
            $user->password = Hash::make($newPassword);
            $user->save();
            return $user;
        });
    }

    /**
     * Bulk create karyawan users from simple Excel/CSV-like rows.
     * Expected columns per row (required): barcode_num, username, name, company_code.
     */
    public function importFromRows(array $rows): array
    {
        $created = [];

        DB::transaction(function () use ($rows, &$created) {
            foreach ($rows as $row) {
                $barcodeNum = trim((string)($row['barcode_num'] ?? ''));
                $username   = trim((string)($row['username'] ?? ''));
                $name       = trim((string)($row['name'] ?? ''));
                $companyCode = trim((string)($row['company_code'] ?? ''));

                // required: nomor kartu (barcode_num), username, nama, perusahaan
                if ($barcodeNum === '' || $username === '' || $name === '' || $companyCode === '') {
                    continue;
                }

                $user = User::firstOrNew(['username' => $username]);

                $user->name        = $name;
                $user->role        = 'karyawan';
                $user->company_code = $companyCode;
                $user->barcode_num = $barcodeNum;

                if (!$user->exists) {
                    $user->password = Hash::make($username);
                }

                $user->save();
                $created[] = $user;
            }
        });

        return $created;
    }
}
