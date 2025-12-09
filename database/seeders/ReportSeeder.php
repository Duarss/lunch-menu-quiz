<?php

namespace Database\Seeders;

use App\Helpers\Project;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        // Find eligible exporters (admin or bm)
        $exporters = User::query()
            ->whereIn('role', ['admin', 'bm'])
            ->pluck('username')
            ->all();

        if (empty($exporters)) {
            // No eligible users; skip seeding to avoid FK violations
            return;
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $currentMonday = $now->copy()->startOfWeek(Carbon::MONDAY);

        // Seed reports for the last week, current week, and next week
        $mondays = [];
        $thisMonday = $now->copy()->startOfWeek(Carbon::MONDAY);
        $mondays[] = $thisMonday->copy()->subWeek();
        $mondays[] = $thisMonday->copy();
        $mondays[] = $thisMonday->copy()->addWeek();

        foreach ($mondays as $monday) {
            $code = Project::monthWeekCode($monday);
            $year = (int) $monday->year;
            $month = (int) $monday->month;
            $weekInMonth = (int) $monday->weekOfMonth;

            // Avoid duplicates when re-seeding
            $report = Report::firstOrCreate(
                ['code' => $code],
                [
                    'year' => $year,
                    'month' => $month,
                    'week_in_month' => $weekInMonth,
                ]
            );

            // Mark as exported by a random eligible user at an appropriate time
            $isPastWeek = $monday->lessThan($currentMonday);

            if ($isPastWeek && is_null($report->exported_at)) {
                $report->forceFill([
                    'exported_by' => $exporters[array_rand($exporters)],
                    'exported_at' => $monday->copy()->addDays(4)->setTime(10, 0, 0),
                ])->save();
            }

            if (!$isPastWeek && $report->exported_at && $report->exported_at->greaterThan($now)) {
                $report->forceFill([
                    'exported_at' => null,
                    'exported_by' => null,
                ])->save();
            }
        }
    }
}

?>