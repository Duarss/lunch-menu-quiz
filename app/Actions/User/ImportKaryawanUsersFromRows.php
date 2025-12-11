<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportKaryawanUsersFromRows extends UserAction
{
    // Note: __invoke imports karyawan users from the provided rows of data
    public function __invoke(array $rows): array
    {
        $created = [];

        DB::transaction(function () use ($rows, &$created) {
            foreach ($rows as $row) {
                $barcodeNum = trim((string) ($row['barcode_num'] ?? ''));
                $username = trim((string) ($row['username'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));
                $companyCode = trim((string) ($row['company_code'] ?? ''));

                if ($barcodeNum === '' || $username === '' || $name === '' || $companyCode === '') {
                    continue;
                }

                $user = User::firstOrNew(['username' => $username]);

                $user->name = $name;
                $user->role = 'karyawan';
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
