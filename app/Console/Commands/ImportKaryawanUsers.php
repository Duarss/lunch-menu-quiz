<?php

namespace App\Console\Commands;

use App\Services\UserService;
use Illuminate\Console\Command;

class ImportKaryawanUsers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'users:import-karyawan {file : Path to CSV file with karyawan data}';

    /**
     * The console command description.
     */
    protected $description = 'Import karyawan users from a CSV file (barcode_num, username, name, company_code).';

    public function __construct(private UserService $userService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_readable($path)) {
            $this->error("File tidak dapat dibaca: {$path}");
            return self::FAILURE;
        }

        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $header = null;
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if ($header === null) {
                    $header = array_map('trim', $data);

                    $required = ['barcode_num', 'username', 'name', 'company_code'];
                    $missing = array_diff($required, $header);
                    if (!empty($missing)) {
                        fclose($handle);
                        $this->error('Header file tidak sesuai. Kolom wajib: barcode_num, username, name, company_code.');
                        $this->line('Kolom yang belum ada: ' . implode(', ', $missing));
                        return self::FAILURE;
                    }
                    continue;
                }
                if (count(array_filter($data, fn($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }
                $row = [];
                foreach ($header as $index => $key) {
                    $row[$key] = $data[$index] ?? null;
                }
                $rows[] = $row;
            }
            fclose($handle);
        }

        if (empty($rows)) {
            $this->warn('File tidak berisi data yang valid.');
            return self::SUCCESS;
        }

        $created = $this->userService->importFromRows($rows);
        $count = count($created);
        $this->info("Import karyawan berhasil. Total diproses: {$count}.");

        return self::SUCCESS;
    }
}
