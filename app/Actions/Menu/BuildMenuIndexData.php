<?php

namespace App\Actions\Menu;

use App\Helpers\Project;
use App\Models\ChosenMenu;
use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;

class BuildMenuIndexData extends MenuAction
{
    // Note: __construct injects the BuildVendorIndexData action
    public function __construct(private BuildVendorIndexData $buildVendorIndexData) {}

    // Note: __invoke builds menu index data based on the user's role and current date
    public function __invoke(User $user): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $role = $user->role ?? 'guest';
        $dayOrder = ['Mon', 'Tue', 'Wed', 'Thu'];
        $windowEligible = in_array(
            $now->dayOfWeekIso,
            // [Carbon::MONDAY, Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY],
            [Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY],
            true
        );

        if ($role === 'vendor') {
            $vendorData = ($this->buildVendorIndexData)($user);
            $vendorDayOrder = $vendorData['vendorDayOrder'] ?? [];
            $vendorDays = $vendorData['vendorDays'] ?? [];

            return array_merge([
                'role' => $role,
                'dayOrder' => $dayOrder,
                'windowEligible' => $windowEligible,
                'vendorLabels' => $this->vendorLabels(),
                'vendorLabel' => $vendorData['vendorCateringLabel'] ?? 'Vendor',
                'vendorWeekLabel' => $vendorData['vendorWeekCode'] ?? ($vendorData['weekCode'] ?? '—'),
                'vendorRangeLabelStart' => $this->formatDateLabel($vendorData['vendorRangeStart'] ?? null),
                'vendorRangeLabelEnd' => $this->formatDateLabel($vendorData['vendorRangeEnd'] ?? null),
                'vendorSlides' => $this->prepareVendorSlides($vendorDayOrder, $vendorDays),
                'rangeStartLabel' => $this->formatDateLabel($vendorData['vendorRangeStart'] ?? null),
                'rangeEndLabel' => $this->formatDateLabel($vendorData['vendorRangeEnd'] ?? null),
            ], $vendorData);
        }

        $targetMonday = $now->copy()->next(Carbon::MONDAY);
        $weekCode = Project::monthWeekCode($targetMonday);
        $rangeStart = $targetMonday->toDateString();
        $rangeEnd = $targetMonday->copy()->addDays(3)->toDateString();

        if (in_array($role, ['admin', 'bm'])) {
            return $this->buildAdminData(
                user: $user,
                weekCode: $weekCode,
                targetMonday: $targetMonday,
                now: $now,
                dayOrder: $dayOrder,
                rangeStart: $rangeStart,
                rangeEnd: $rangeEnd,
                windowEligible: $windowEligible
            );
        }

        return $this->buildKaryawanData(
            user: $user,
            weekCode: $weekCode,
            targetMonday: $targetMonday,
            now: $now,
            tz: $tz,
            dayOrder: $dayOrder,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
            windowEligible: $windowEligible,
        );
    }

    // Note: buildAdminData constructs the data array for admin and bm users
    private function buildAdminData(
        User $user,
        string $weekCode,
        Carbon $targetMonday,
        Carbon $now,
        array $dayOrder,
        string $rangeStart,
        string $rangeEnd,
        bool $windowEligible
    ): array {
        $creation = $this->resolveNextCreationWeek($targetMonday);
        /** @var Carbon $creationMonday */
        $creationMonday = $creation['monday'];
        $creationRangeStart = $creationMonday->toDateString();
        $creationRangeEnd = $creationMonday->copy()->addDays(3)->toDateString();
        $creationStartLabel = $creationRangeStart ? Carbon::parse($creationRangeStart)->format('D, d M Y') : null;
        $creationEndLabel = $creationRangeEnd ? Carbon::parse($creationRangeEnd)->format('D, d M Y') : null;

        $days = [];
        $cursor = $targetMonday->copy();
        foreach ($dayOrder as $index => $label) {
            if ($index > 0) {
                $cursor->addDay();
            }

            $days[$label] = [
                'date' => $cursor->toDateString(),
                'date_label' => $cursor->format('d, M Y'),
                'is_holiday' => $this->isHoliday($cursor),
            ];
        }

        $optionsByDay = array_fill_keys($dayOrder, []);
        $optionsDetailedByDay = array_fill_keys($dayOrder, []);
        $patterns = $this->buildWeekPatterns($weekCode);

        $selectionWindowReady = Project::isSelectionWindowReady($weekCode);
        $selectionWindowOpen = Project::isSelectionWindowOpen($now);

        $menusUpcoming = Menu::where('code', 'like', $weekCode . '-%')
            ->orderBy('code')
            ->get(['code', 'name', 'image', 'catering']);

        foreach ($menusUpcoming as $menu) {
            $label = $this->detectDayLabel($patterns, $menu->code);
            if (!$label) {
                continue;
            }

            $formatted = $this->formatMenu($menu);

            if (count($optionsByDay[$label]) < 4) {
                $optionsByDay[$label][] = $formatted;
            }

            $catKey = $formatted['catering'] ?? ($menu->catering ?? 'unknown');
            $catLabel = $formatted['catering_label'] ?? ($menu->catering ? ucfirst($menu->catering) : 'Vendor');

            if (!isset($optionsDetailedByDay[$label][$catKey])) {
                $optionsDetailedByDay[$label][$catKey] = [
                    'catering' => $catKey,
                    'catering_label' => $catLabel,
                    'image_url' => $formatted['image_url'] ?? null,
                    'menus' => [],
                ];
            }

            if (empty($optionsDetailedByDay[$label][$catKey]['image_url']) && !empty($formatted['image_url'])) {
                $optionsDetailedByDay[$label][$catKey]['image_url'] = $formatted['image_url'];
            }

            $optionsDetailedByDay[$label][$catKey]['menus'][] = [
                'code' => $formatted['code'],
                'name' => $formatted['name'],
            ];
        }

        $currentMonday = $now->copy()->startOfWeek(Carbon::MONDAY);
        $currentWeekCode = Project::monthWeekCode($currentMonday);

        $tableWeekCodes = collect([$currentWeekCode, $weekCode, $creation['code']])
            ->merge(collect($creation['skipped'])->pluck('code'))
            ->filter()
            ->unique()
            ->values();

        $menus = Menu::where(function ($query) use ($tableWeekCodes) {
            $tableWeekCodes->each(function ($code, $index) use ($query) {
                if ($index === 0) {
                    $query->where('code', 'like', $code . '-%');
                } else {
                    $query->orWhere('code', 'like', $code . '-%');
                }
            });
        })
            ->orderBy('code')
            ->get(['code', 'name', 'image']);

        return [
            'role' => $user->role ?? 'guest',
            'dayOrder' => $dayOrder,
            'windowEligible' => $windowEligible,
            'menus' => $menus,
            'weekCode' => $weekCode,
            'creationWeekCode' => $creation['code'],
            'creationRangeStart' => $creationRangeStart,
            'creationRangeEnd' => $creationRangeEnd,
            'creationStartLabel' => $creationStartLabel,
            'creationEndLabel' => $creationEndLabel,
            'creationExistingCount' => $creation['existing_count'],
            'creationSkippedWeeks' => $creation['skipped'],
            'creationAutoAdvanceWeeks' => count($creation['skipped']),
            'tableWeekCodes' => $tableWeekCodes,
            'optionsByDay' => $optionsByDay,
            'optionsDetailedByDay' => $optionsDetailedByDay,
            'days' => $days,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'rangeStartLabel' => $this->formatDateLabel($rangeStart),
            'rangeEndLabel' => $this->formatDateLabel($rangeEnd),
            'selectionWindowReady' => $selectionWindowReady,
            'selectionWindowOpen' => $selectionWindowOpen,
            'windowReady' => $selectionWindowReady,
            'windowOpen' => $selectionWindowOpen,
            'vendorLabels' => $this->vendorLabels(),
            'vendorDayOrder' => [],
            'vendorDays' => [],
            'vendorWeekCode' => null,
            'vendorRangeStart' => null,
            'vendorRangeEnd' => null,
            'vendorRangeLabelStart' => null,
            'vendorRangeLabelEnd' => null,
            'vendorSlides' => [],
            'menuDetails' => [],
        ];
    }

    // Note: buildKaryawanData constructs the data array for karyawan users
    private function buildKaryawanData(
        User $user,
        string $weekCode,
        Carbon $targetMonday,
        Carbon $now,
        string $tz,
        array $dayOrder,
        string $rangeStart,
        string $rangeEnd,
        bool $windowEligible
    ): array {
        $windowOpen = Project::isSelectionWindowOpen($now);
        $windowReady = Project::isSelectionWindowReady($weekCode);

        $existing = ChosenMenu::where('chosen_by', $user->id)
            ->whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->get()
            ->keyBy('chosen_for_day');

        $days = [];
        $cursor = Carbon::parse($rangeStart, $tz);
        for ($i = 0; $i < count($dayOrder); $i++) {
            $label = $cursor->format('D');
            $date = $cursor->toDateString();
            $isHoliday = $this->isHoliday($cursor);

            $record = $existing->get($date);
            $selectedValue = null;
            $isLocked = false;
            if ($record) {
                $isLocked = (bool) $record->is_locked;
                if ($windowOpen || $isLocked) {
                    $selectedValue = $record->menu_code;
                }
            }

            $days[$label] = [
                'date' => $date,
                'date_label' => $cursor->format('d, M Y'),
                'selected' => $isHoliday ? null : $selectedValue,
                'locked' => $isLocked,
                'is_holiday' => $isHoliday,
            ];

            $cursor->addDay();
        }

        $pendingCount = collect($days)
            ->filter(fn(array $day) => empty($day['selected']) && empty($day['is_holiday']))
            ->count();

        $optionsByDay = array_fill_keys($dayOrder, []);
        $optionsDetailedByDay = array_fill_keys($dayOrder, []);
        $patterns = $this->buildWeekPatterns($weekCode);
        $menusUpcoming = Menu::where('code', 'like', $weekCode . '-%')
            ->orderBy('code')
            ->get(['code', 'name', 'image', 'catering']);

        $menuDetails = [];

        foreach ($menusUpcoming as $menu) {
            $label = $this->detectDayLabel($patterns, $menu->code);
            if (!$label) {
                continue;
            }

            if (count($optionsByDay[$label]) < 4) {
                $optionsByDay[$label][$menu->code] = $menu->name;
            }

            $formatted = $this->formatMenu($menu);
            $catKey = $formatted['catering'] ?? ($menu->catering ?? 'unknown');
            $catLabel = $formatted['catering_label'] ?? ($menu->catering ? ucfirst($menu->catering) : 'Vendor');

            if (!isset($optionsDetailedByDay[$label][$catKey])) {
                $optionsDetailedByDay[$label][$catKey] = [
                    'catering' => $catKey,
                    'catering_label' => $catLabel,
                    'image_url' => $formatted['image_url'] ?? null,
                    'menus' => [],
                    'is_selected' => false,
                ];
            }

            if (empty($optionsDetailedByDay[$label][$catKey]['image_url']) && !empty($formatted['image_url'])) {
                $optionsDetailedByDay[$label][$catKey]['image_url'] = $formatted['image_url'];
            }

            $optionsDetailedByDay[$label][$catKey]['menus'][] = [
                'code' => $menu->code,
                'name' => $menu->name,
                'is_selected' => isset($days[$label]['selected']) && $days[$label]['selected'] === $menu->code,
            ];

            if (isset($days[$label]['selected']) && $days[$label]['selected'] === $menu->code) {
                $optionsDetailedByDay[$label][$catKey]['is_selected'] = true;
            }

            $menuDetails[$menu->code] = [
                'code' => $formatted['code'],
                'name' => $formatted['name'],
                'catering' => $formatted['catering'] ?? $menu->catering,
                'catering_label' => $formatted['catering_label'] ?? ($menu->catering ? ucfirst($menu->catering) : 'Vendor'),
                'image_url' => $formatted['image_url'] ?? null,
            ];
        }

        return [
            'role' => $user->role ?? 'guest',
            'dayOrder' => $dayOrder,
            'windowEligible' => $windowEligible,
            'menus' => collect(),
            'weekCode' => $weekCode,
            'windowOpen' => $windowOpen,
            'windowReady' => $windowReady,
            'selectionWindowReady' => $windowReady,
            'selectionWindowOpen' => $windowOpen,
            'days' => $days,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'rangeStartLabel' => $this->formatDateLabel($rangeStart),
            'rangeEndLabel' => $this->formatDateLabel($rangeEnd),
            'pendingCount' => $pendingCount,
            'optionsByDay' => $optionsByDay,
            'optionsDetailedByDay' => $optionsDetailedByDay,
            'menuDetails' => $menuDetails,
            'vendorLabels' => $this->vendorLabels(),
            'vendorDayOrder' => [],
            'vendorDays' => [],
            'vendorSlides' => [],
            'karyawanSummary' => $this->buildKaryawanSummary($dayOrder, $days, $windowOpen, $windowReady),
        ];
    }

    // Note: buildWeekPatterns creates regex patterns for matching menu codes to days
    private function buildWeekPatterns(string $weekCode): array
    {
        return [
            'Mon' => '/^' . preg_quote($weekCode, '/') . '-MON-\d+$/i',
            'Tue' => '/^' . preg_quote($weekCode, '/') . '-TUE-\d+$/i',
            'Wed' => '/^' . preg_quote($weekCode, '/') . '-WED-\d+$/i',
            'Thu' => '/^' . preg_quote($weekCode, '/') . '-THU-\d+$/i',
        ];
    }

    // Note: detectDayLabel identifies the day label (Mon/Tue/Wed/Thu) for a given menu code
    private function detectDayLabel(array $patterns, ?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $code)) {
                return $label;
            }
        }

        return null;
    }

    // Note: formatDateLabel formats a date string into a more readable label
    private function formatDateLabel(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        return Carbon::parse($date)->format('D, d M Y');
    }

    // Note: prepareVendorSlides formats the vendor day data into slides for display
    private function prepareVendorSlides(array $vendorDayOrder, array $vendorDays): array
    {
        $slides = [];

        foreach ($vendorDayOrder as $label) {
            if (!isset($vendorDays[$label])) {
                continue;
            }

            $day = $vendorDays[$label];
            $options = $day['options'] ?? [];
            $optionList = [];

            foreach ($options as $key => $option) {
                $hasMenu = !empty($option['has_menu']);

                $optionList[] = [
                    'option_key' => $key,
                    'label' => $option['label'] ?? ('Opsi ' . strtoupper((string) $key)),
                    'code' => $option['code'] ?? null,
                    'name' => $option['name'] ?? '',
                    'image_url' => $option['image_url'] ?? null,
                    'has_menu' => $hasMenu,
                    'card_state_class' => $hasMenu ? 'is-complete' : 'is-pending',
                    'status_badge_class' => $hasMenu ? 'bg-success' : 'bg-secondary',
                    'status_badge_text' => $hasMenu ? 'Sudah ada' : 'Belum ada',
                    'icon' => $hasMenu ? 'bx-check-circle' : 'bx-time-five',
                    'icon_class' => $hasMenu ? 'text-success' : 'text-warning',
                    'input_placeholder' => 'Masukkan nama menu ' . strtoupper((string) $key),
                ];
            }

            $totalOptions = count($optionList);
            $completedOptions = 0;
            $primaryImageUrl = null;

            foreach ($optionList as $option) {
                if (!empty($option['has_menu'])) {
                    $completedOptions++;
                }

                if ($primaryImageUrl === null && !empty($option['image_url'])) {
                    $primaryImageUrl = $option['image_url'];
                }
            }

            $dayComplete = $totalOptions > 0 && $completedOptions === $totalOptions;

            $slides[] = [
                'label' => $day['label'] ?? $label,
                'day_code' => $day['day_code'] ?? strtoupper(substr($label, 0, 3)),
                'date_label' => $day['date_label'] ?? null,
                'summary_text' => $totalOptions
                    ? sprintf('%d dari %d opsi siap', $completedOptions, $totalOptions)
                    : 'Belum ada opsi untuk hari ini.',
                'badge_class' => $dayComplete ? 'bg-success' : 'bg-warning text-white',
                'badge_text' => $dayComplete ? 'Complete' : 'Needs menu',
                'options' => $optionList,
                'option_total' => $totalOptions,
                'option_completed' => $completedOptions,
                'primary_image_url' => $primaryImageUrl,
                'image_alt' => ($day['label'] ?? $label) . ' preview',
            ];
        }

        return $slides;
    }

    // Note: buildKaryawanSummary creates the summary data for karyawan dashboard
    private function buildKaryawanSummary(array $dayOrder, array $days, bool $windowOpen, bool $windowReady): array
    {
        $totalDays = 0;
        $selectedCount = 0;
        $pendingCount = 0;

        foreach ($dayOrder as $label) {
            if (!isset($days[$label])) {
                continue;
            }

            $totalDays++;

            if (empty($days[$label]['selected'])) {
                $pendingCount++;
            } else {
                $selectedCount++;
            }
        }

        $selectionPercent = $totalDays
            ? (int) round(($selectedCount / max(1, $totalDays)) * 100)
            : 0;

        $pendingBadgeClass = $pendingCount ? 'bg-warning text-white' : 'bg-success';
        $pendingBadgeText = $pendingCount ? 'Butuh Dipilih' : 'Semua Dipilih';
        $pendingCardClass = $pendingCount ? 'card-border-shadow-warning' : 'card-border-shadow-primary';
        $windowStatusLabel = $windowOpen ? 'Buka' : ($windowReady ? 'Segera Siap' : 'Tutup');
        $windowStatusBadgeClass = $windowOpen ? 'bg-success' : ($windowReady ? 'bg-success text-white' : 'bg-danger');

        return [
            'total_days' => $totalDays,
            'selected_count' => $selectedCount,
            'pending_count' => $pendingCount,
            'selection_percent' => $selectionPercent,
            'pending_badge_class' => $pendingBadgeClass,
            'pending_badge_text' => $pendingBadgeText,
            'pending_card_class' => $pendingCardClass,
            'window_status_label' => $windowStatusLabel,
            'window_status_badge_class' => $windowStatusBadgeClass,
        ];
    }
}
