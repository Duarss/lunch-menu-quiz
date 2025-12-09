<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed companies first
        $this->call([
            CompanySeeder::class,
        ]);

        // 2. Ambil semua company codes
        $allCompanyCodes = Company::pluck('code');

        if ($allCompanyCodes->isEmpty()) {
            throw new RuntimeException('Company seeding did not generate any records.');
        }

        // 3. Pisahkan vendor & non-vendor
        $vendorCodeList = collect(['vendorA', 'vendorB']);

        $vendorCompanies = Company::whereIn('code', $vendorCodeList)->get();

        if ($vendorCompanies->count() !== $vendorCodeList->count()) {
            throw new RuntimeException('Vendor companies (vendorA, vendorB) must exist.');
        }

        $nonVendorCompanyCodes = $allCompanyCodes->diff($vendorCodeList);

        if ($nonVendorCompanyCodes->isEmpty()) {
            throw new RuntimeException('No non-vendor companies available for normal users.');
        }

        // Helper: random non-vendor company code
        $randomNonVendorCode = function () use ($nonVendorCompanyCodes) {
            return $nonVendorCompanyCodes->random();
        };

        // Track used barcodes in memory to avoid collisions in this seeding run
        $usedBarcodes = collect();

        // Helper: generate UNIQUE EAN-13 style barcode
        $generateUniqueEan13 = function () use (&$usedBarcodes): string {
            do {
                // 12 random digits
                $digits = [];
                for ($i = 0; $i < 12; $i++) {
                    $digits[] = random_int(0, 9);
                }

                // Calculate check digit
                $oddSum  = 0; // posisi 1,3,5,7,9,11
                $evenSum = 0; // posisi 2,4,6,8,10,12

                foreach ($digits as $index => $digit) {
                    $position = $index + 1;
                    if ($position % 2 === 1) {
                        $oddSum += $digit;
                    } else {
                        $evenSum += $digit;
                    }
                }

                $total      = $oddSum + ($evenSum * 3);
                $checkDigit = (10 - ($total % 10)) % 10;

                $barcode = implode('', $digits) . $checkDigit;

                // Loop again if:
                // - already used in this seeding run, OR
                // - already exists in DB (paranoid but safe)
            } while (
                $usedBarcodes->contains($barcode) ||
                User::where('barcode_num', $barcode)->exists()
            );

            $usedBarcodes->push($barcode);

            return $barcode;
        };

        // Now use $generateUniqueEan13() for each NON-VENDOR user:

        User::factory()->create([
            'barcode_num'  => $generateUniqueEan13(),
            'name'         => 'Test Employee',
            'username'     => 'testemployee',
            'password'     => Hash::make('test123'),
            'role'         => 'karyawan',
            'company_code' => $randomNonVendorCode(),
        ]);

        User::factory()->create([
            'barcode_num'  => $generateUniqueEan13(),
            'name'         => 'Test BM',
            'username'     => 'testbm',
            'password'     => Hash::make('test123'),
            'role'         => 'bm',
            'company_code' => $randomNonVendorCode(),
        ]);

        User::factory()->create([
            'barcode_num'  => $generateUniqueEan13(),
            'name'         => 'Test Admin',
            'username'     => 'testadmin',
            'password'     => Hash::make('test123'),
            'role'         => 'admin',
            'company_code' => $randomNonVendorCode(),
        ]);

        // 5. Seed vendor users (barcode_num = null, use Vendor A & Vendor B)
        $vendorACompany = $vendorCompanies->firstWhere('code', 'vendorA');
        $vendorBCompany = $vendorCompanies->firstWhere('code', 'vendorB');

        User::updateOrCreate(
            ['username' => 'vendor.a'],
            [
                'barcode_num'  => $generateUniqueEan13(), // vendor: no card / no barcode
                'name'         => 'Vendor A',
                'password'     => Hash::make('vendorA'),
                'role'         => 'vendor',
                'company_code' => $vendorACompany->code,
            ]
        );

        User::updateOrCreate(
            ['username' => 'vendor.b'],
            [
                'barcode_num'  => $generateUniqueEan13(), // vendor: no card / no barcode
                'name'         => 'Vendor B',
                'password'     => Hash::make('vendorB'),
                'role'         => 'vendor',
                'company_code' => $vendorBCompany->code,
            ]
        );

        // $this->call([
        //     MenuSeeder::class,
        //     ChosenMenuSeeder::class,
        //     ReportSeeder::class,
        // ]);
    }
}
