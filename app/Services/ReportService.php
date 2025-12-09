<?php

namespace App\Services;

use App\Helpers\Project;
use App\Models\ChosenMenu;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Build dataset for the master report index view.
     */
    public function getIndexData(): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $currentUser = Auth::user();
        $currentUsername = $currentUser?->username;
        $upcomingMonday = $now->copy()->next(Carbon::MONDAY)->startOfDay();
        $currentMonday = $upcomingMonday->copy()->subWeek()->startOfDay();

        $currentWeekCode = Project::monthWeekCode($currentMonday);
        $upcomingWeekCode = Project::monthWeekCode($upcomingMonday);

        $currentReport = Report::firstOrCreate(
            ['code' => $currentWeekCode],
            [
                'year' => (int) $currentMonday->year,
                'month' => (int) $currentMonday->month,
                'week_in_month' => (int) $currentMonday->weekOfMonth,
            ]
        );

        $upcomingReport = Report::firstOrCreate(
            ['code' => $upcomingWeekCode],
            [
                'year' => (int) $upcomingMonday->year,
                'month' => (int) $upcomingMonday->month,
                'week_in_month' => (int) $upcomingMonday->weekOfMonth,
            ]
        );

        $currentReport->loadMissing('exporter');
        $upcomingReport->loadMissing('exporter');

        $totalKaryawan = User::where('role', 'karyawan')->count();

        $currentWeek = $this->buildWeekDataset($currentReport, $currentMonday, $now, $totalKaryawan, $tz, true);
        $upcomingWeek = $this->buildWeekDataset($upcomingReport, $upcomingMonday, $now, $totalKaryawan, $tz, false);

        $reports = Report::with('exporter')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('week_in_month')
            ->get();

        $pendingReports = $reports->filter(function (Report $item) use ($now) {
            return !$item->exported_at || $item->exported_at->greaterThan($now);
        })->values()->map(function (Report $item) use ($totalKaryawan, $tz, $currentUsername) {
            $summary = $this->summarizeWeekCompletion($item, $totalKaryawan, $tz);

            $item->pending_completed_users = $summary['completed'];
            $item->pending_pending_users = $summary['pending'];
            $item->ready_for_export = $summary['ready'];
            $item->pending_range_start = $summary['range_start'];
            $item->pending_range_end = $summary['range_end'];

            $item->exported_by_current_user = $currentUsername
                && $item->exported_by
                && $item->exported_by === $currentUsername;

            return $item;
        })->values();

        $historyReports = $reports->filter(function (Report $item) use ($now) {
            return $item->exported_at && $item->exported_at->lessThanOrEqualTo($now);
        })->values();

        return [
            'week' => $upcomingWeek,
            'current_week' => $currentWeek,
            'pending_reports' => $pendingReports,
            'history_reports' => $historyReports,
        ];
    }

    protected function buildWeekDataset(Report $report, Carbon $monday, Carbon $now, int $totalKaryawan, string $tz, bool $isCurrentWeek): array
    {
        $rangeStartDate = $monday->copy();
        $rangeEndDate = $monday->copy()->addDays(3);
        $rangeStart = $rangeStartDate->toDateString();
        $rangeEnd = $rangeEndDate->toDateString();
        $deadline = $monday->copy()->subDays(3)->endOfDay();
        $availableAt = $monday->copy()->subDays(3)->startOfDay();

        $selectionsQuery = ChosenMenu::query()
            ->whereBetween('chosen_for_day', [$rangeStart, $rangeEnd]);
        $totalSelections = (clone $selectionsQuery)->count();

        $completedUsers = (clone $selectionsQuery)
            ->select('chosen_by', DB::raw('COUNT(*) as total'))
            ->groupBy('chosen_by')
            ->havingRaw('COUNT(*) >= 4')
            ->get()
            ->count();

        $completionPercent = $totalKaryawan > 0
            ? (int) round(($completedUsers / max($totalKaryawan, 1)) * 100)
            : 0;

        [$dailyProgress, $dayDates] = $this->buildDailyProgress($monday, $totalKaryawan);
        $pendingUsers = $this->buildPendingUsers($dayDates, $rangeStart, $rangeEnd);

        $hasPastExport = $report->exported_at && $report->exported_at->lessThanOrEqualTo($now);
        $hasFutureExport = $report->exported_at && $report->exported_at->greaterThan($now);
        $exportPending = !$hasPastExport;

        $windowReady = Project::isSelectionWindowReady($report->code);
        $windowOpen = $isCurrentWeek ? false : Project::isSelectionWindowOpen($now);

        [$windowStatusLabel, $windowBadgeClass, $windowHelp] = $this->resolveWindowStatus(
            $report,
            $windowReady,
            $windowOpen,
            $deadline,
            $tz,
            $hasPastExport,
            $hasFutureExport ? $report->exported_at : null
        );

        if ($isCurrentWeek && !$hasPastExport) {
            $windowStatusLabel = 'Awaiting Export';
            $windowBadgeClass = 'bg-warning';
            $windowHelp = 'Window closed last Friday. Export to distribute this week\'s report.';
        }

        $canExport = $exportPending && $now->greaterThanOrEqualTo($availableAt);
        $exportDisabledReason = null;
        if ($hasPastExport) {
            $exportDisabledReason = 'Report already exported.';
        } elseif ($now->lessThan($availableAt)) {
            $exportDisabledReason = 'Export window opens on ' . $availableAt->format('D, d M Y H:i');
        }

        $exportedAtLabel = $hasPastExport
            ? $report->exported_at->timezone($tz)->format('D, d M Y H:i')
            : null;

        $exportedByLabel = $hasPastExport
            ? ($report->exporter?->name ?? $report->exported_by ?? '—')
            : null;

        $futureExportLabel = $hasFutureExport
            ? $report->exported_at->timezone($tz)->format('D, d M Y H:i')
            : null;

        $repeatWarningMessage = null;
        if ($hasPastExport && $exportedAtLabel) {
            $repeatWarningMessage = sprintf(
                'This report was already exported on %s by %s.',
                $exportedAtLabel,
                $exportedByLabel ?? '—'
            );
        }

        return [
            'code' => $report->code,
            'range_label' => sprintf('%s – %s', $rangeStartDate->format('d M'), $rangeEndDate->format('d M Y')),
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
            'deadline_label' => $deadline->format('D, d M Y'),
            'deadline_time_label' => $deadline->format('D, d M Y H:i'),
            'export_available_label' => $availableAt->format('D, d M Y H:i'),
            'can_export' => $canExport,
            'export_disabled_reason' => $exportDisabledReason,
            'export_pending' => $exportPending,
            'exported_at_label' => $exportedAtLabel,
            'exported_by_label' => $exportedByLabel,
            'export_future_label' => $futureExportLabel,
            'export_repeat_message' => $repeatWarningMessage,
            'report' => $report,
            'is_current' => $isCurrentWeek,
            'window' => [
                'status' => $windowStatusLabel,
                'badge_class' => $windowBadgeClass,
                'help' => $windowHelp,
            ],
            'totals' => [
                'karyawan' => $totalKaryawan,
                'completed' => $completedUsers,
                'completion_percent' => $completionPercent,
                'pending_count' => count($pendingUsers),
                'selections' => $totalSelections,
            ],
            'daily' => $dailyProgress,
            'pending_users' => $pendingUsers,
        ];
    }

    /**
     * Build per-day completion stats and the date labels for Mon–Thu.
     */
    protected function buildDailyProgress(Carbon $monday, int $totalUsers): array
    {
        $daily = [];
        $dates = collect();
        $cursor = $monday->copy();

        for ($i = 0; $i < 4; $i++) {
            $date = $cursor->toDateString();
            $count = ChosenMenu::where('chosen_for_day', $date)
                ->distinct('chosen_by')
                ->count('chosen_by');
            $percent = $totalUsers > 0 ? (int) round(($count / max($totalUsers, 1)) * 100) : 0;

            $daily[] = [
                'label' => $cursor->format('D'),
                'date' => $date,
                'count' => $count,
                'percent' => $percent,
            ];

            $dates->push([
                'date' => $date,
                'label' => $cursor->format('D'),
            ]);

            $cursor->addDay();
        }

        return [$daily, $dates];
    }

    /**
     * Identify karyawan who have not completed all four selections.
     */
    protected function buildPendingUsers(Collection $dayDates, string $rangeStart, string $rangeEnd): array
    {
        $users = User::where('role', 'karyawan')
            ->orderBy('name')
            ->get(['id', 'username', 'name']);

        $choicesByUser = ChosenMenu::whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->select('chosen_by', 'chosen_for_day')
            ->get()
            ->groupBy('chosen_by')
            ->map(fn($rows) => $rows->pluck('chosen_for_day'));

        $pending = [];

        foreach ($users as $user) {
            $pickedDates = $choicesByUser->get($user->id, collect())
                ->map(fn($date) => Carbon::parse($date)->toDateString());

            $missing = $dayDates->filter(
                fn($day) => !$pickedDates->contains($day['date'])
            )->values();

            if ($missing->isNotEmpty()) {
                $pending[] = [
                    'username' => $user->username,
                    'name' => $user->name,
                    'missing_labels' => $missing->pluck('label')->all(),
                ];
            }
        }

        return $pending;
    }

    /**
     * Summarize completion metrics for a report's week.
     */
    protected function summarizeWeekCompletion(Report $report, int $totalKaryawan, string $tz): array
    {
        $monday = Project::mondayFromMonthWeekCode($report->code, $tz);
        $rangeStart = $monday->toDateString();
        $rangeEnd = $monday->copy()->addDays(3)->toDateString();

        if ($totalKaryawan <= 0) {
            return [
                'completed' => 0,
                'pending' => 0,
                'ready' => true,
                'range_start' => $rangeStart,
                'range_end' => $rangeEnd,
            ];
        }

        $completed = ChosenMenu::whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->select('chosen_by', DB::raw('COUNT(*) as total'))
            ->groupBy('chosen_by')
            ->havingRaw('COUNT(*) >= 4')
            ->get()
            ->count();

        $pending = max($totalKaryawan - $completed, 0);

        return [
            'completed' => $completed,
            'pending' => $pending,
            'ready' => $pending === 0,
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
        ];
    }

    /**
     * Resolve human-readable selection window status for the view.
     */
    protected function resolveWindowStatus(Report $report, bool $windowReady, bool $windowOpen, Carbon $deadline, string $tz, bool $hasPastExport, ?Carbon $futureExportAt = null): array
    {
        if ($hasPastExport && $report->exported_at) {
            $label = 'Closed';
            $badge = 'bg-success';
            $help = 'Exported on ' . $report->exported_at->timezone($tz)->format('D, d M Y H:i')
                . ($report->exported_by ? ' by ' . $report->exported_by : '');
            return [$label, $badge, $help];
        }

        if ($futureExportAt) {
            return [
                'Pending',
                'bg-warning',
                'Export scheduled for ' . $futureExportAt->timezone($tz)->format('D, d M Y H:i') . '. Week remains open until finalized.',
            ];
        }

        if ($windowOpen) {
            return ['Open', 'bg-primary', 'Karyawan can still submit until ' . $deadline->format('D, d M Y H:i') . '.'];
        }

        if ($windowReady) {
            return ['Ready', 'bg-info', 'Window is marked ready; it opens automatically Wed–Fri.'];
        }

        return ['Pending', 'bg-secondary', 'Release the window from Master Menu once menus are final.'];
    }
}
