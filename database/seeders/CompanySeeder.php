<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = fake();

        // ================================
        // 1. Seed VENDOR companies (fixed)
        // ================================
        $vendorCompanies = [
            ['code' => 'vendorA', 'name' => 'Vendor A'],
            ['code' => 'vendorB',   'name' => 'Vendor B'],
        ];

        $vendorCodes = collect();
        foreach ($vendorCompanies as $vendor) {
            $company = Company::updateOrCreate(
                ['code' => $vendor['code']],
                ['name' => $vendor['name']]
            );

            $vendorCodes->push($company->code);
        }

        // =========================================
        // 2. Seed EMPLOYEE companies (non-vendor)
        //    with more unique, readable codes
        // =========================================
        //
        // Example generated codes:
        // - GTS01A23
        // - NRS02B57
        // - etc.
        //
        // Pattern:
        //   - 3–4 letters from company initials
        //   - + 2-digit index
        //   - + 2 random uppercase letters/digits
        //
        // This gives uniqueness & readability.
        $createdCodes = collect($vendorCodes->all()); // track all used codes

        $employeeTarget = 10; // how many non-vendor companies you want

        for ($i = 1; $i <= $employeeTarget; $i++) {
            $name = $faker->unique()->company();

            // Build a base from initials: e.g. "Global Tech Solutions" -> "GTS"
            $words = preg_split('/\s+/', Str::upper($name));
            $initials = collect($words)
                ->filter()
                ->map(fn($w) => Str::substr($w, 0, 1))
                ->join('');

            $base = preg_replace('/[^A-Z0-9]/', '', $initials);
            $base = Str::limit($base, 4, ''); // max 4 chars

            if ($base === '') {
                $base = 'COMP';
            }

            // Generate a unique company code
            // e.g. GTS01A3, NRS02Z9, COMP03Q4, etc.
            $code = null;
            do {
                $suffixIndex = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $randPart = Str::upper(Str::random(2)); // 2 random chars
                $code = $base . $suffixIndex . $randPart;
            } while (
                Company::where('code', $code)->exists()
                || $createdCodes->contains($code)
            );

            $company = Company::updateOrCreate(
                ['code' => $code],
                ['name' => $name]
            );

            $createdCodes->push($company->code);
        }

        // =====================================================
        // 3. Assign NON-VENDOR companies to users (karyawan)
        //    NOTE: Vendor A & Vendor B are excluded here
        // =====================================================
        $assignableCompanyCodes = Company::whereNotIn('code', $vendorCodes->all())
            ->pluck('code');

        if ($assignableCompanyCodes->isEmpty()) {
            return;
        }

        User::query()
            ->where(function ($query) {
                $query->whereNull('company_code')
                    ->orWhere('company_code', '');
            })
            ->each(function (User $user) use ($assignableCompanyCodes) {
                $user->forceFill([
                    'company_code' => $assignableCompanyCodes->random(),
                ])->save();
            });
    }
}
