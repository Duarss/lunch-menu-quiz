<?php

namespace App\Actions\Dashboard;

use App\Actions\Menu\MenuAction;
use App\Helpers\Project;
use App\Models\ChosenMenu;
use App\Models\Menu;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BuildBMDashboardData extends MenuAction
{
    private const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu'];

    // Note: __invoke is what gets called when using app(BuildBMDashboardData::class)(...)
    public function __invoke(string $rangeStart, string $rangeEnd, string $weekCode, Carbon $targetMonday, Carbon $now): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $totalUsers = User::where('role', 'karyawan')->count();

        $upcomingReport = Report::firstOrCreate(
            ['code' => $weekCode],
            [
                'year' => (int) $targetMonday->year,
                'month' => (int) $targetMonday->month,
                'week_in_month' => (int) $targetMonday->weekOfMonth,
            ]
        );

        $currentWeekMonday = $targetMonday->copy()->subWeek()->startOfDay();
        $currentWeekCode = Project::monthWeekCode($currentWeekMonday);

        $currentReport = Report::firstOrCreate(
            ['code' => $currentWeekCode],
            [
                'year' => (int) $currentWeekMonday->year,
                'month' => (int) $currentWeekMonday->month,
                'week_in_month' => (int) $currentWeekMonday->weekOfMonth,
            ]
        );

        $fridayCutoff = $currentWeekMonday->copy()->addDays(4)->endOfDay();

        $upcomingWeek = $this->buildUpcomingWeek($weekCode);
        $upcomingReadyLabel = ($upcomingWeek['days_ready'] ?? 0) . ' / 4';

        $selectionDays = $this->buildSelectionDays($rangeStart, $totalUsers);
        $pendingUsers = $this->findPendingUsers($rangeStart, $rangeEnd, $tz);
        $lockedPercent = $this->calculateLockedPercent($rangeStart, $rangeEnd);
        $nextExportLabel = $upcomingReport->exported_at
            ? 'Exported'
            : $fridayCutoff->copy()->startOfDay()->format('Y-m-d');

        $statCards = [
            [
                'id' => 'upcoming-ready',
                'title' => 'Menus Ready (Upcoming)',
                'subtitle' => 'Days prepared',
                'icon' => 'bx-calendar',
                'unit' => '',
                'colour' => 'primary',
                'value' => $upcomingReadyLabel,
            ],
            [
                'id' => 'locked-percent',
                'title' => 'Selections Locked',
                'subtitle' => 'Across all picks',
                'icon' => 'bx-lock',
                'unit' => '%',
                'colour' => 'warning',
                'value' => $lockedPercent,
            ],
            [
                'id' => 'pending-users',
                'title' => 'Pending Selections',
                'subtitle' => 'Users to remind',
                'icon' => 'bx-bell',
                'unit' => '',
                'colour' => 'primary',
                'value' => count($pendingUsers),
            ],
            [
                'id' => 'next-export',
                'title' => 'Next Export',
                'subtitle' => 'Scheduled Friday',
                'icon' => 'bx-download',
                'unit' => '',
                'colour' => 'primary',
                'value' => $nextExportLabel,
            ],
        ];

        $selectionCards = $this->buildSelectionCards($selectionDays);

        $upcomingSummaryCards = [
            [
                'id' => 'upcoming-code',
                'title' => 'Upcoming Code',
                'value' => $upcomingWeek['code'],
                'subtitle' => 'Identifier',
                'icon' => 'bx-hash',
                'unit' => '',
                'colour' => 'primary',
            ],
            [
                'id' => 'upcoming-days-ready',
                'title' => 'Days Ready',
                'value' => $upcomingReadyLabel,
                'subtitle' => 'Full 4 menus',
                'icon' => 'bx-calendar',
                'unit' => '',
                'colour' => 'primary',
            ],
        ];

        $upcomingDayBlocks = $this->buildUpcomingDayBlocks($upcomingWeek['days']);

        $exportOptions = $this->buildExportOptions($currentReport, $upcomingReport, $fridayCutoff, $tz);

        $recentReports = Report::orderByDesc('exported_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'statCards' => $statCards,
            'selectionCards' => $selectionCards,
            'exportOptions' => $exportOptions,
            'allReportsUrl' => route('bm.reports.index'),
            'upcomingSummaryCards' => $upcomingSummaryCards,
            'upcomingDayBlocks' => $upcomingDayBlocks,
            'pendingUsers' => $pendingUsers,
            'recentReports' => $recentReports,
        ];
    }

    // Note: buildSelectionDays assumes a 4-day work week (Mon-Thu)
    private function buildSelectionDays(string $rangeStart, int $totalUsers): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $cursor = Carbon::parse($rangeStart, $tz);
        $days = [];

        foreach (self::DAY_LABELS as $index => $label) {
            if ($index > 0) {
                $cursor->addDay();
            }

            $date = $cursor->toDateString();
            $completed = ChosenMenu::where('chosen_for_day', $date)
                ->distinct('chosen_by')
                ->count('chosen_by');
            $percent = $totalUsers > 0
                ? (int) round(($completed / max($totalUsers, 1)) * 100)
                : 0;

            $days[$label] = [
                'completed' => $completed,
                'total' => $totalUsers,
                'percent' => $percent,
                'percent_label' => $percent . '%',
            ];
        }

        return $days;
    }

    // Note: buildSelectionCards creates dashboard cards for each selection day
    private function buildSelectionCards(array $selectionDays): array
    {
        $cards = [];

        foreach (self::DAY_LABELS as $label) {
            $data = $selectionDays[$label] ?? null;
            if ($data === null) {
                continue;
            }

            $cards[] = [
                'id' => 'sel-' . strtolower($label),
                'title' => $label,
                'value' => sprintf('%d / %d', $data['completed'], $data['total']),
                'subtitle' => $data['percent_label'],
                'icon' => 'bx-check-square',
                'unit' => '',
                'colour' => 'primary',
            ];
        }

        return $cards;
    }

    // Note: findPendingUsers identifies users who have not completed their selections for the week
    private function findPendingUsers(string $rangeStart, string $rangeEnd, string $tz): array
    {
        $employees = User::where('role', 'karyawan')->get(['id', 'username']);
        $choices = ChosenMenu::whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->select('chosen_by', 'chosen_for_day')
            ->get()
            ->groupBy('chosen_by')
            ->map(fn($rows) => $rows->pluck('chosen_for_day')->map(fn($d) => (string) $d));

        $dayStrings = [];
        $cursor = Carbon::parse($rangeStart, $tz);
        foreach (self::DAY_LABELS as $index => $label) {
            if ($index > 0) {
                $cursor->addDay();
            }

            $dayStrings[] = $cursor->toDateString();
        }

        $pending = [];

        foreach ($employees as $employee) {
            $picked = collect($choices->get($employee->id, []));
            $missing = collect($dayStrings)
                ->reject(fn($date) => $picked->contains($date))
                ->values();

            if ($missing->isNotEmpty()) {
                $pending[] = [
                    'username' => $employee->username,
                    'missing_days' => $missing
                        ->map(fn($date) => Carbon::parse($date, $tz)->format('D'))
                        ->all(),
                ];
            }
        }

        return $pending;
    }

    // Note: calculateLockedPercent computes the percentage of locked selections for the week
    private function calculateLockedPercent(string $rangeStart, string $rangeEnd): int
    {
        $totalSelections = ChosenMenu::whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])->count();
        $lockedSelections = ChosenMenu::whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->where('is_locked', true)
            ->count();

        if ($totalSelections === 0) {
            return 0;
        }

        return (int) round(($lockedSelections / max($totalSelections, 1)) * 100);
    }

    // Note: buildUpcomingWeek retrieves menu data for the upcoming week (Mon-Thu)
    private function buildUpcomingWeek(string $weekCode): array
    {
        $records = Menu::where('code', 'like', $weekCode . '-%')
            ->orderBy('code')
            ->get(['code', 'name', 'image', 'catering']);

        $days = array_fill_keys(self::DAY_LABELS, []);

        foreach ($records as $menu) {
            $formatted = $this->formatMenu($menu);
            $code = $formatted['code'] ?? $menu->code;

            foreach (['Mon' => 'MON', 'Tue' => 'TUE', 'Wed' => 'WED', 'Thu' => 'THU'] as $label => $dayCode) {
                if ($this->matchesDay($code, $weekCode, $dayCode)) {
                    $days[$label][] = [
                        'code' => $formatted['code'] ?? $menu->code,
                        'name' => $formatted['name'] ?? $menu->name,
                    ];
                    break;
                }
            }
        }

        $daysReady = collect($days)
            ->filter(fn(array $menus) => count($menus) === 4)
            ->count();

        return [
            'code' => $weekCode,
            'days' => $days,
            'days_ready' => $daysReady,
        ];
    }

    // Note: matchesDay checks if a menu code corresponds to a specific day in the week
    private function matchesDay(?string $code, string $weekCode, string $dayCode): bool
    {
        if ($code === null) {
            return false;
        }

        return (bool) preg_match('/^' . preg_quote($weekCode, '/') . '-' . $dayCode . '-\\d+$/i', $code);
    }

    // Note: buildUpcomingDayBlocks creates dashboard blocks for each day in the upcoming week  
    private function buildUpcomingDayBlocks(array $days): array
    {
        $blocks = [];

        foreach (self::DAY_LABELS as $label) {
            $menus = $days[$label] ?? [];
            $count = count($menus);

            $blocks[] = [
                'label' => $label,
                'badge_class' => $count === 4 ? 'bg-success' : 'bg-secondary',
                'badge_label' => $count . '/4',
                'menus' => array_map(
                    static fn(array $menu) => [
                        'name' => $menu['name'] ?? '',
                        'short_name' => Str::limit($menu['name'] ?? '', 38),
                    ],
                    $menus
                ),
            ];
        }

        return $blocks;
    }

    // Note: buildExportOptions creates export options for current and upcoming reports
    private function buildExportOptions(Report $currentReport, Report $upcomingReport, Carbon $fridayCutoff, string $tz): array
    {
        $options = [];

        $entries = [
            [
                'key' => 'current',
                'label' => 'Export This Week (' . $currentReport->code . ')',
                'description' => 'Menus being served this week (finalised last Friday).',
                'note' => null,
                'report' => $currentReport,
            ],
            [
                'key' => 'upcoming',
                'label' => 'Export Next Week (' . $upcomingReport->code . ')',
                'description' => 'Optional: selections for next week; data stabilises after Friday.',
                'note' => 'Optional export. Final submissions expected by ' . $fridayCutoff->copy()->format('D, d M Y') . '.',
                'report' => $upcomingReport,
            ],
        ];

        foreach ($entries as $entry) {
            /** @var Report $report */
            $report = $entry['report'];

            $options[] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
                'description' => $entry['description'],
                'note' => $entry['note'],
                'button' => $report->exported_at ? null : [
                    'url' => route('bm.reports.export', $report->code),
                    'label' => $entry['label'],
                    'icon' => 'bx-download',
                    'method' => 'POST',
                ],
                'notification' => $report->exported_at ? [
                    'id' => 'exported-' . $entry['key'],
                    'title' => 'Exported',
                    'message' => 'Week ' . $report->code . ' exported',
                    'time' => $report->exported_at->copy()->timezone($tz)->format('Y-m-d H:i'),
                ] : null,
            ];
        }

        return $options;
    }
}
