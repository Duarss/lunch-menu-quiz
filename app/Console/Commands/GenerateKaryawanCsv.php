<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateKaryawanCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example:
     *   php artisan users:generate-karyawan-csv --rows=1000
     */
    protected $signature = 'users:generate-karyawan-csv 
                            {--rows=1000 : Jumlah baris dummy karyawan} 
                            {--path=storage/app/imports/karyawan_dummy.csv : Path output CSV}';

    /**
     * The console command description.
     */
    protected $description = 'Generate dummy CSV file for karyawan import based on existing non-vendor companies';

    public function handle(): int
    {
        $rows = (int) $this->option('rows');
        $relativePath = $this->option('path');

        $nonVendorCompanies = Company::whereNotIn('code', ['vendorA', 'vendorB'])
            ->pluck('code')
            ->all();

        if (empty($nonVendorCompanies)) {
            $this->error('Tidak ada company non-vendor di tabel companies. Jalankan CompanySeeder terlebih dulu.');
            return self::FAILURE;
        }

        $fullPath = base_path($relativePath);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $handle = fopen($fullPath, 'w');
        if ($handle === false) {
            $this->error("Tidak bisa menulis file ke: {$fullPath}");
            return self::FAILURE;
        }

        fputcsv($handle, ['barcode_num', 'username', 'name', 'company_code']);

        // Track barcodes generated in this file
        $usedBarcodes = [];

        for ($i = 1; $i <= $rows; $i++) {
            $barcodeNum = $this->generateUniqueEan13($usedBarcodes);
            $username   = 'user' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
            $name       = fake()->name();
            $companyCode = $nonVendorCompanies[array_rand($nonVendorCompanies)];

            fputcsv($handle, [
                $barcodeNum,
                $username,
                $name,
                $companyCode,
            ]);
        }

        fclose($handle);

        $this->info("Dummy CSV karyawan berhasil dibuat: {$fullPath}");
        $this->info("Baris: {$rows}");

        return self::SUCCESS;
    }

    private function generateUniqueEan13(array &$usedBarcodes): string
    {
        do {
            // 12 random digits
            $digits = [];
            for ($i = 0; $i < 12; $i++) {
                $digits[] = random_int(0, 9);
            }

            $oddSum  = 0;
            $evenSum = 0;

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

            // avoid:
            // - duplicates in current CSV
            // - duplicates vs already stored users
        } while (
            in_array($barcode, $usedBarcodes, true) ||
            User::where('barcode_num', $barcode)->exists()
        );

        $usedBarcodes[] = $barcode;

        return $barcode;
    }
}
