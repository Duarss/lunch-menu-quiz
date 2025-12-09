<?php

namespace App\Services;

use App\Helpers\Project;
use App\Models\ChosenMenu;
use App\Models\Company;
use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;

class ReportExportService
{
    /**
     * Generate and stream an Excel weekly report for given month-based week code (YYYY-MM-Wn).
     * Locks the selections before streaming (should be called in a controller with auth checks).
     */
    public function streamWeekly(string $weekCode): StreamedResponse
    {
        $monday = Project::mondayFromMonthWeekCode($weekCode);
        $thursday = $monday->copy()->addDays(3);

        // Lock selections atomically prior to export if needed.
        DB::transaction(function () use ($monday, $thursday) {
            ChosenMenu::whereBetween('chosen_for_day', [
                $monday->toDateString(),
                $thursday->toDateString()
            ])->update(['is_locked' => true]);
        });

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Lunch Menu Quiz')
            ->setTitle('Weekly Lunch Selections ' . $weekCode)
            ->setSubject('Lunch Selections')
            ->setDescription('Export of lunch menu selections for week ' . $weekCode);

        $rows = ChosenMenu::query()
            ->whereBetween('chosen_for_day', [$monday->toDateString(), $thursday->toDateString()])
            ->with(['menu', 'user.company'])
            ->orderBy('chosen_for_day')
            ->orderBy('menu_code')
            ->orderBy('chosen_by')
            ->get()
            ->groupBy(fn($row) => Carbon::parse($row->chosen_for_day)->format('Y-m-d'));

        $orderedDays = collect();
        $cursor = $monday->copy();
        for ($i = 0; $i < 4; $i++) {
            $dateKey = $cursor->format('Y-m-d');
            $orderedDays->put($dateKey, $rows->get($dateKey, collect()));
            $cursor->addDay();
        }

        $allUsers = User::with('company')
            ->where('role', 'karyawan')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $formatUserLabel = function (?User $user, string $fallbackUsername): string {
            if (!$user) {
                return $fallbackUsername;
            }

            $name = $user->name ?: $fallbackUsername;
            $company = $user->company->name ?? null;

            return $company ? sprintf('%s - %s', $name, $company) : $name;
        };

        $sheetIndex = 0;
        foreach ($orderedDays as $day => $dayRows) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle(Carbon::parse($day)->format('D'));

            // Section headers
            $sheet->setCellValue('A1', 'Date');
            $sheet->setCellValue('B1', Carbon::parse($day)->format('D, d M Y'));

            $vendorHeaderRow = 3;
            $voteRow = 4;
            $menuRow = 5;
            $namesStartRow = 6;

            // group by vendor (catering) and sort for consistent output
            $grouped = $dayRows
                ->groupBy(fn($row) => $row->menu?->catering ?? 'Unknown')
                ->sortKeys();

            $startColumn = 1; // Column A
            $maxFilledRow = $namesStartRow - 1;

            foreach ($grouped as $vendor => $items) {
                // Count votes per menu and sort by vote desc
                $menusForVendor = $items->groupBy('menu_code')->map(function ($entries) {
                    return [
                        'menu' => $entries->first()->menu,
                        'total' => $entries->count(),
                        'users' => $entries->sortBy(function ($entry) {
                            $name = $entry->user?->name
                                ?? $entry->user?->username
                                ?? (int) $entry->chosen_by;
                            return mb_strtolower($name);
                        })->values(),
                    ];
                })->sortByDesc('total')->values();

                if ($menusForVendor->isEmpty()) {
                    continue;
                }

                $colSpan = $menusForVendor->count();
                $endColumnIndex = $startColumn + $colSpan - 1;

                $vendorLabel = $this->formatVendorLabel($vendor);

                $sheet->setCellValueByColumnAndRow($startColumn, $vendorHeaderRow, strtoupper($vendorLabel));
                if ($colSpan > 1) {
                    $sheet->mergeCellsByColumnAndRow($startColumn, $vendorHeaderRow, $endColumnIndex, $vendorHeaderRow);
                }
                $sheet->getStyleByColumnAndRow($startColumn, $vendorHeaderRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach ($menusForVendor as $index => $menuData) {
                    $columnIndex = $startColumn + $index;
                    $sheet->setCellValueByColumnAndRow($columnIndex, $voteRow, $menuData['total'] . ' Vote');
                    $sheet->getStyleByColumnAndRow($columnIndex, $voteRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->setCellValueByColumnAndRow($columnIndex, $menuRow, $menuData['menu']->name ?? $menuData['menu']?->code ?? 'Menu');

                    $rowIndex = $namesStartRow;
                    foreach ($menuData['users'] as $entry) {
                        $fallback = $entry->user?->username ?? (int) $entry->chosen_by;
                        $label = $formatUserLabel($entry->user, $fallback);
                        $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $label);
                        $rowIndex++;
                    }

                    $maxFilledRow = max($maxFilledRow, $rowIndex - 1);
                }

                $startColumn = $endColumnIndex + 2; // leave a blank column between vendors
            }

            // Adjust column widths
            $highestColumn = $sheet->getHighestColumn();
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $selectedUserIds = $dayRows->pluck('chosen_by')->filter()->unique()->all();
            $missingUsers = $allUsers->reject(function (User $user, $id) use ($selectedUserIds) {
                return in_array($id, $selectedUserIds, true);
            })->values();

            if ($missingUsers->isNotEmpty()) {
                $summaryHeaderRow = $maxFilledRow + 2;
                $sheet->setCellValueByColumnAndRow(1, $summaryHeaderRow, 'Belum memilih');
                $sheet->setCellValueByColumnAndRow(2, $summaryHeaderRow, 'Jumlah');
                $sheet->setCellValueByColumnAndRow(1, $summaryHeaderRow + 1, 'Total');
                $sheet->setCellValueByColumnAndRow(2, $summaryHeaderRow + 1, $missingUsers->count());

                $sheet->getStyle('A' . $summaryHeaderRow)->getFont()->setBold(true);
                $sheet->getStyle('B' . $summaryHeaderRow)->getFont()->setBold(true);
                $sheet->getStyle('A' . ($summaryHeaderRow + 1))->getFont()->setBold(true);

                $listStartRow = $summaryHeaderRow + 3;
                foreach ($missingUsers as $index => $user) {
                    $label = $formatUserLabel($user, $user->username ?? (int) $user->id);
                    $sheet->setCellValueByColumnAndRow(1, $listStartRow + $index, ($index + 1) . '. ' . $label);
                }
            }

            $sheetIndex++;
        }

        $filename = $weekCode . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    protected function formatVendorLabel(?string $vendor): string
    {
        if ($vendor === null || $vendor === '') {
            return 'Vendor';
        }

        $sanitized = strtolower(trim($vendor));
        $sanitized = str_replace([' ', '_', '-'], '', $sanitized);

            $map = [
                'vendora' => 'vendorA',
                'vendorb' => 'vendorB',
            ];

            $normalized = $map[$sanitized] ?? null;
            if ($normalized === null) {
                return ucfirst($vendor);
            }

            return Company::where('code', $normalized)->value('name')
                ?? match ($normalized) {
                    'vendorA' => 'Vendor A',
                    'vendorB' => 'Vendor B',
                    default => ucfirst($vendor),
                };
    }
}
