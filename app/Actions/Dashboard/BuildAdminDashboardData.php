<?php

namespace App\Actions\Dashboard;

use App\Actions\Menu\MenuAction;
use App\Helpers\Project;
use App\Models\ChosenMenu;
use App\Models\Menu;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BuildAdminDashboardData extends MenuAction
{
    public function __invoke(string $rangeStart, string $rangeEnd, string $weekCode): array
    {
        $totalUsers = User::where('role', 'karyawan')->count();

        $completedUsers = ChosenMenu::query()
            ->select('chosen_by', DB::raw('COUNT(*) as total'))
            ->whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->groupBy('chosen_by')
            ->havingRaw('COUNT(*) >= 4')
            ->count();

        $completionPercent = $totalUsers > 0
            ? (int) round(($completedUsers / max($totalUsers, 1)) * 100)
            : 0;

        $dailyBreakdown = $this->buildDailyBreakdown($rangeStart, $totalUsers);

        $recentReports = Report::orderByDesc('exported_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $pendingExportWeeks = Report::whereNull('exported_at')->count();

        $menusThisWeek = ChosenMenu::whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->distinct('menu_code')
            ->count('menu_code');

        $stats = [
            'total_users' => $totalUsers,
            'menus_this_week' => $menusThisWeek,
            'completion_percent' => $completionPercent,
            'fully_completed_users' => $completedUsers,
            'daily_breakdown' => $dailyBreakdown,
            'pending_export_weeks' => $pendingExportWeeks,
        ];

        $upcoming = $this->buildUpcomingWeekMenus($weekCode);

        $prep = [
            'code' => $upcoming['code'],
            'days_ready' => $upcoming['days_ready'],
            'day_order' => ['Mon', 'Tue', 'Wed', 'Thu'],
            'days' => $this->formatPrepDays($upcoming['days']),
        ];

        return [
            'stats' => $stats,
            'recentReports' => $recentReports,
            'prep' => $prep,
        ];
    }

    private function buildDailyBreakdown(string $rangeStart, int $totalUsers): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $cursor = Carbon::parse($rangeStart, $tz);
        $labels = ['Mon', 'Tue', 'Wed', 'Thu'];
        $breakdown = [];

        foreach ($labels as $index => $label) {
            if ($index > 0) {
                $cursor->addDay();
            }

            $date = $cursor->toDateString();
            $count = ChosenMenu::where('chosen_for_day', $date)
                ->distinct('chosen_by')
                ->count('chosen_by');
            $percent = $totalUsers > 0
                ? (int) round(($count / max($totalUsers, 1)) * 100)
                : 0;

            $breakdown[$label] = [
                'count' => $count,
                'percent' => $percent,
            ];
        }

        return $breakdown;
    }

    private function buildUpcomingWeekMenus(string $weekCode): array
    {
        $records = Menu::where('code', 'like', $weekCode . '-%')
            ->orderBy('code')
            ->get(['code', 'name', 'image', 'catering']);

        $days = [
            'Mon' => [],
            'Tue' => [],
            'Wed' => [],
            'Thu' => [],
        ];

        foreach ($records as $menu) {
            $formatted = $this->formatMenu($menu);
            $code = $formatted['code'] ?? $menu->code;

            if ($this->matchesDay($code, $weekCode, 'MON')) {
                $days['Mon'][] = $formatted;
                continue;
            }

            if ($this->matchesDay($code, $weekCode, 'TUE')) {
                $days['Tue'][] = $formatted;
                continue;
            }

            if ($this->matchesDay($code, $weekCode, 'WED')) {
                $days['Wed'][] = $formatted;
                continue;
            }

            if ($this->matchesDay($code, $weekCode, 'THU')) {
                $days['Thu'][] = $formatted;
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

    private function matchesDay(?string $code, string $weekCode, string $day): bool
    {
        if ($code === null) {
            return false;
        }

        return (bool) preg_match('/^' . preg_quote($weekCode, '/') . '-' . $day . '-\d+$/i', $code);
    }

    private function formatPrepDays(array $days): array
    {
        $labels = ['Mon', 'Tue', 'Wed', 'Thu'];
        $formatted = [];

        foreach ($labels as $label) {
            $menus = $days[$label] ?? [];
            $count = count($menus);

            $formatted[] = [
                'label' => $label,
                'count' => $count,
                'is_full' => $count === 4,
                'menus' => array_map(
                    static fn(array $menu) => [
                        'code' => $menu['code'] ?? null,
                        'name' => $menu['name'] ?? null,
                    ],
                    $menus
                ),
            ];
        }

        return $formatted;
    }
}
