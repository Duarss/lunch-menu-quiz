<?php

namespace App\Http\Controllers;

use App\Traits\HasResponse;
use App\Traits\HasTransaction;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\ChosenMenu;
use App\Models\Report;
use App\Helpers\Project;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Menu;
use App\Services\MenuService;

class DashboardController extends Controller
{
    use HasTransaction, HasResponse;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);

        // Target week for selection/exports: upcoming week (Mon–Thu)
        $targetMonday = $now->copy()->next(Carbon::MONDAY);
        if (!$this->isSelectionWindow()) {
            // If not in Wed–Fri, still use next Monday to standardize views
            $targetMonday = $now->copy()->next(Carbon::MONDAY);
        }
        $weekCode = Project::monthWeekCode($targetMonday);
        $rangeStart = $targetMonday->toDateString();
        $rangeEnd = $targetMonday->copy()->addDays(3)->toDateString();

        if ($user->role === 'admin') {
            [$stats, $recentReports, $prep] = $this->buildAdminData($rangeStart, $rangeEnd, $weekCode);
            return view('dashboard.admin.index', compact('stats', 'recentReports', 'prep'));
        } elseif ($user->role === 'bm') {
            [$stats, $currentReport, $selection, $pendingUsers, $upcomingWeek, $recentReports, $exportOptions] =
                $this->buildBmData($rangeStart, $rangeEnd, $weekCode, $targetMonday);
            return view('dashboard.bm.index', compact('stats', 'currentReport', 'selection', 'pendingUsers', 'upcomingWeek', 'recentReports', 'exportOptions'));
        } elseif ($user->role === 'vendor') {
            $menuService = app(MenuService::class);
            $vendorData = $menuService->getVendorIndexData($user);

            $dayOrder = $vendorData['vendorDayOrder'] ?? [];
            $days = $vendorData['vendorDays'] ?? [];

            $totalSlots = 0;
            $filledSlots = 0;

            foreach ($dayOrder as $label) {
                $day = $days[$label] ?? null;
                if (!$day) {
                    continue;
                }

                foreach (($day['options'] ?? []) as $option) {
                    $totalSlots++;
                    if (!empty($option['has_menu'])) {
                        $filledSlots++;
                    }
                }
            }

            $remainingSlots = max($totalSlots - $filledSlots, 0);
            $completionPercent = $totalSlots > 0 ? round(($filledSlots / $totalSlots) * 100) : 0;

            $rangeStartVendor = $vendorData['vendorRangeStart'] ?? null;
            $rangeEndVendor = $vendorData['vendorRangeEnd'] ?? null;
            $rangeStartLabel = $rangeStartVendor ? Carbon::parse($rangeStartVendor)->format('D, d M Y') : null;
            $rangeEndLabel = $rangeEndVendor ? Carbon::parse($rangeEndVendor)->format('D, d M Y') : null;

            $windowReady = $vendorData['selectionWindowReady'] ?? false;
            $windowOpen = $vendorData['selectionWindowOpen'] ?? false;

            $windowStatus = $windowOpen ? 'Open' : ($windowReady ? 'Ready' : 'Pending');
            $windowSubtitle = $windowOpen
                ? 'Karyawan dapat memilih menu minggu ini.'
                : ($windowReady ? 'Menunggu jadwal Wed–Fri untuk pemilihan karyawan.' : 'Menunggu admin membuka window.');

            $summary = [
                'week_code' => $vendorData['vendorWeekCode'] ?? $weekCode,
                'range_label' => ($rangeStartLabel && $rangeEndLabel)
                    ? $rangeStartLabel . ' – ' . $rangeEndLabel
                    : ($rangeStartLabel ?? '—'),
                'filled_slots' => $filledSlots,
                'total_slots' => $totalSlots,
                'remaining_slots' => $remainingSlots,
                'completion_percent' => $completionPercent,
                'window_status' => $windowStatus,
                'window_subtitle' => $windowSubtitle,
                'window_ready' => $windowReady,
                'window_open' => $windowOpen,
                'catering_label' => $vendorData['vendorCateringLabel']
                    ?? $this->vendorLabel($vendorData['vendorCatering'] ?? null),
            ];

            return view('dashboard.vendor.index', [
                'summary' => $summary,
                'dayOrder' => $dayOrder,
                'days' => $days,
            ]);
        }

        [$summary, $days, $recentSelections] =
            $this->buildKaryawanData($user, $rangeStart, $rangeEnd, $weekCode, $targetMonday, $now);

        return view('dashboard.karyawan.index', compact('summary', 'days', 'recentSelections'));
    }

    protected function vendorLabel(?string $code): string
    {
        if ($code === null || $code === '') {
            return 'Vendor';
        }

        $sanitized = strtolower(trim($code));
        $sanitized = str_replace([' ', '-', '_'], '', $sanitized);

        $map = [
            'vendora' => 'vendorA',
            'vendorb' => 'vendorB',
        ];

        $normalized = $map[$sanitized] ?? null;
        if ($normalized === null) {
            return ucfirst($code);
        }

        return Company::where('code', $normalized)->value('name')
            ?? match ($normalized) {
                'vendorA' => 'Vendor A',
                'vendorB' => 'Vendor B',
                default => ucfirst($code),
            };
    }

    protected function isSelectionWindow(): bool
    {
        return Project::isSelectionWindowOpen();
    }

    protected function buildAdminData(string $start, string $end, string $weekCode): array
    {
        $karyawan = User::where('role', 'karyawan')->get(['id', 'username']);
        $totalUsers = $karyawan->count();

        // How many users selected all 4 days within range
        $completedUsers = ChosenMenu::query()
            ->select('chosen_by', DB::raw('COUNT(*) as c'))
            ->whereBetween('chosen_for_day', [$start, $end])
            ->groupBy('chosen_by')
            ->havingRaw('COUNT(*) >= 4')
            ->pluck('chosen_by')
            ->count();

        $completionPercent = $totalUsers > 0 ? round(($completedUsers / $totalUsers) * 100) : 0;

        // Per day breakdown
        $dailyBreakdown = [];
        $day = Carbon::parse($start);
        for ($i = 0; $i < 4; $i++) {
            $dayStr = $day->toDateString();
            $count = ChosenMenu::where('chosen_for_day', $dayStr)->distinct('chosen_by')->count('chosen_by');
            $percent = $totalUsers > 0 ? round(($count / $totalUsers) * 100) : 0;
            $dailyBreakdown[$day->format('D')] = ['count' => $count, 'percent' => $percent];
            $day->addDay();
        }

        // Recent reports and pending
        $recentReports = Report::orderByDesc('exported_at')->orderByDesc('created_at')->limit(5)->get();
        $pendingExportWeeks = Report::whereNull('exported_at')->count();

        // Menus this week (approx: distinct menus chosen in range)
        $menusThisWeek = ChosenMenu::whereBetween('chosen_for_day', [$start, $end])->distinct('menu_code')->count('menu_code');

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
            'menus' => $upcoming['days'],
        ];

        return [$stats, $recentReports, $prep];
    }

    protected function buildBmData(string $start, string $end, string $weekCode, Carbon $targetMonday): array
    {
        $totalUsers = User::where('role', 'karyawan')->count();

        // Ensure upcoming report record exists for reference (upcoming week menus use this code)
        $upcomingReport = Report::firstOrCreate(
            ['code' => $weekCode],
            [
                'year' => (int) $targetMonday->year,
                'month' => (int) $targetMonday->month,
                'week_in_month' => (int) $targetMonday->weekOfMonth,
            ]
        );

        // Selection daily status
        $daysData = [];
        $day = Carbon::parse($start);
        for ($i = 0; $i < 4; $i++) {
            $dayStr = $day->toDateString();
            $completed = ChosenMenu::where('chosen_for_day', $dayStr)->distinct('chosen_by')->count('chosen_by');
            $percent = $totalUsers > 0 ? round(($completed / $totalUsers) * 100) : 0;
            $daysData[$day->format('D')] = [
                'completed' => $completed,
                'total' => $totalUsers,
                'percent' => $percent,
            ];
            $day->addDay();
        }

        // Pending users list (who are missing at least one day)
        $karyawan = User::where('role', 'karyawan')->get(['id', 'username']);
        $choices = ChosenMenu::whereBetween('chosen_for_day', [$start, $end])
            ->select('chosen_by', 'chosen_for_day')
            ->get()
            ->groupBy('chosen_by')
            ->map(fn($rows) => $rows->pluck('chosen_for_day')->map(fn($d) => (string) $d)->all());

        $dayStrings = [
            Carbon::parse($start)->toDateString(),
            Carbon::parse($start)->copy()->addDay()->toDateString(),
            Carbon::parse($start)->copy()->addDays(2)->toDateString(),
            Carbon::parse($start)->copy()->addDays(3)->toDateString(),
        ];

        $pendingUsers = [];
        foreach ($karyawan as $employee) {
            $picked = collect($choices->get($employee->id, []));
            $missingDates = collect($dayStrings)->reject(fn($d) => $picked->contains($d))->values();
            if ($missingDates->isNotEmpty()) {
                $pendingUsers[] = [
                    'username' => $employee->username,
                    'missing_days' => $missingDates->map(fn($d) => Carbon::parse($d)->format('D'))->all(),
                ];
            }
        }

        // Locked percent
        $totalSelections = ChosenMenu::whereBetween('chosen_for_day', [$start, $end])->count();
        $lockedSelections = ChosenMenu::whereBetween('chosen_for_day', [$start, $end])->where('is_locked', true)->count();
        $lockedPercent = $totalSelections > 0 ? round(($lockedSelections / $totalSelections) * 100) : 0;

        // Upcoming week menus and readiness
        $upcomingWeek = $this->buildUpcomingWeekMenus($weekCode);

        // Reports available for export:
        // - Current week: menus being served now (chosen last week)
        // - Upcoming week: menus being chosen this week for next week
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
        if ($upcomingReport->exported_at) {
            $nextExportLabel = 'Exported';
        } else {
            $nextExportLabel = $fridayCutoff->copy()->startOfDay()->format('Y-m-d');
        }

        $exportOptions = [
            [
                'key' => 'current',
                'label' => 'Export This Week (' . $currentReport->code . ')',
                'description' => 'Menus being served this week (finalised last Friday).',
                'report' => $currentReport,
                'note' => null,
            ],
            [
                'key' => 'upcoming',
                'label' => 'Export Next Week (' . $upcomingReport->code . ')',
                'description' => 'Optional: selections for next week; data stabilises after Friday.',
                'report' => $upcomingReport,
                'note' => 'Optional export. Final submissions expected by ' . $fridayCutoff->copy()->format('D, d M Y') . '.',
            ],
        ];

        // Stats summary for BM (dynamic upcoming readiness + export label)
        $stats = [
            'upcoming_ready_days' => ($upcomingWeek['days_ready'] ?? 0) . ' / 4',
            'locked_percent' => $lockedPercent,
            'pending_users' => count($pendingUsers),
            'next_export_date' => $nextExportLabel,
        ];

        $selection = [
            'days' => $daysData,
        ];

        $recentReports = Report::orderByDesc('exported_at')->orderByDesc('created_at')->limit(5)->get();

        return [$stats, $currentReport, $selection, $pendingUsers, $upcomingWeek, $recentReports, $exportOptions];
    }

    /**
     * Build upcoming week menus grouped by day with readiness stats.
     */
    protected function buildUpcomingWeekMenus(string $weekCode): array
    {
        $records = Menu::where('code', 'like', $weekCode . '-%')
            ->orderBy('code')
            ->get(['code', 'name', 'image', 'catering']);
        $days = ['Mon' => [], 'Tue' => [], 'Wed' => [], 'Thu' => []];
        foreach ($records as $m) {
            if (preg_match('/^' . preg_quote($weekCode, '/') . '-MON-\d+$/i', $m->code)) {
                $days['Mon'][] = $this->formatMenuPreview($m);
                continue;
            }
            if (preg_match('/^' . preg_quote($weekCode, '/') . '-TUE-\d+$/i', $m->code)) {
                $days['Tue'][] = $this->formatMenuPreview($m);
                continue;
            }
            if (preg_match('/^' . preg_quote($weekCode, '/') . '-WED-\d+$/i', $m->code)) {
                $days['Wed'][] = $this->formatMenuPreview($m);
                continue;
            }
            if (preg_match('/^' . preg_quote($weekCode, '/') . '-THU-\d+$/i', $m->code)) {
                $days['Thu'][] = $this->formatMenuPreview($m);
                continue;
            }
        }
        $daysReady = collect($days)->filter(fn($list) => count($list) === 4)->count();
        return [
            'code' => $weekCode,
            'days' => $days,
            'days_ready' => $daysReady,
        ];
    }

    protected function formatMenuPreview(Menu $m): array
    {
        $formatted = app(MenuService::class)->formatMenu($m);

        return [
            'code' => $formatted['code'],
            'name' => $formatted['name'],
            'catering' => $formatted['catering'] ?? $m->catering,
            'catering_label' => $formatted['catering_label'] ?? ($m->catering ? ucfirst($m->catering) : null),
            'image_url' => $formatted['image_url'] ?? ($m->image ? rtrim(config('app.url'), '/') . '/storage/' . ltrim($m->image, '/') : null),
        ];
    }

    protected function buildKaryawanData(User $user, string $start, string $end, string $weekCode, Carbon $targetMonday, Carbon $now): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $windowOpen = Project::isSelectionWindowOpen($now);
        $windowReady = Project::isSelectionWindowReady($weekCode);

        $rangeStart = Carbon::parse($start, $tz);
        $rangeEnd = Carbon::parse($end, $tz);
        $windowStart = $targetMonday->copy()->subDays(5)->startOfDay();
        $windowEnd = $targetMonday->copy()->subDays(3)->endOfDay();

        $selections = ChosenMenu::with('menu')
            ->where('chosen_by', $user->id)
            ->whereBetween('chosen_for_day', [$start, $end])
            ->get()
            ->keyBy(function ($item) use ($tz) {
                return Carbon::parse($item->chosen_for_day, $tz)->toDateString();
            });

        $days = [];
        $dayCursor = $rangeStart->copy();
        $completed = 0;
        $pendingSubtitle = 'Due by ' . $windowEnd->copy()->format('D, d M H:i');

        for ($i = 0; $i < 4; $i++) {
            $dateKey = $dayCursor->toDateString();
            $dayLabel = $dayCursor->format('D');
            $entry = $selections->get($dateKey);

            if ($entry) {
                $completed++;
            }

            $timestampString = $entry->updated_at ?? $entry->chosen_at ?? null;
            $timestamp = $timestampString ? Carbon::parse($timestampString, $tz) : null;

            $status = 'pending';
            $subtitle = $pendingSubtitle;
            if ($entry) {
                $status = $entry->is_locked ? 'locked' : 'saved';
                $subtitlePrefix = $entry->is_locked ? 'Locked' : '';
                $subtitle = $timestamp ? $subtitlePrefix . ' ' . $timestamp->format('D, d M H:i') : $subtitlePrefix;
            }

            $days[$dayLabel] = [
                'date' => $dateKey,
                'value' => $entry?->menu?->name ?? 'Pending',
                'status' => $status,
                'subtitle' => $subtitle,
                'colour' => $status === 'pending' ? 'warning' : 'primary',
            ];

            $dayCursor->addDay();
        }

        $remaining = max(0, 4 - $completed);

        if ($windowOpen) {
            $windowStatusLabel = 'Open';
            $windowSubtitle = 'Submit before ' . $windowEnd->copy()->format('D, d M H:i');
            $windowColour = 'primary';
            $ctaLabel = $remaining > 0 ? 'Lengkapi Pilihan Saya' : 'Tinjau Pilihan Saya';
        } elseif (!$windowReady) {
            $windowStatusLabel = 'Pending';
            $windowSubtitle = 'Waiting for admin to release menus';
            $windowColour = 'warning';
            $ctaLabel = 'Lihat Menu Minggu Depan';
        } else {
            $windowStatusLabel = 'Closed';
            $windowSubtitle = 'Opens ' . $windowStart->copy()->format('D, d M H:i');
            $windowColour = 'warning';
            $ctaLabel = 'Lihat Menu Minggu Depan';
        }

        $summary = [
            'week_code' => $weekCode,
            'week_subtitle' => sprintf('%s – %s', $rangeStart->format('d M'), $rangeEnd->format('d M')),
            'window_status_label' => $windowStatusLabel,
            'window_subtitle' => $windowSubtitle,
            'window_colour' => $windowColour,
            'completed_days' => $completed,
            'remaining_days' => $remaining,
            'remaining_colour' => $remaining > 0 ? 'warning' : 'primary',
            'cta_label' => $ctaLabel,
            'pending_days' => collect($days)
                ->filter(fn($day) => $day['status'] === 'pending')
                ->keys()
                ->values()
                ->all(),
            'window_ready' => $windowReady,
        ];

        $recentSelections = ChosenMenu::with('menu')
            ->where('chosen_by', $user->username)
            ->orderByDesc('chosen_at')
            ->limit(5)
            ->get()
            ->map(function ($item) use ($tz) {
                $chosenFor = Carbon::parse($item->chosen_for_day, $tz);
                $chosenAt = $item->chosen_at ? Carbon::parse($item->chosen_at, $tz) : null;

                return [
                    'date_label' => $chosenFor->format('D, d M Y'),
                    'menu_name' => optional($item->menu)->name ?? '—',
                    'status' => $item->is_locked ? 'Locked' : 'Saved',
                    'timestamp_label' => $chosenAt ? $chosenAt->format('d M Y H:i') : '—',
                ];
            });

        return [$summary, $days, $recentSelections];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
