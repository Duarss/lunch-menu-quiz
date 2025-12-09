<?php

namespace App\Services;

use App\Helpers\Project;
use App\Models\ChosenMenu;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuService
{
    private const EXPECTED_MENUS_PER_WEEK = 16;

    private const CATERING_SLOT_MAP = [
        'vendorA' => [
            1 => 'Opsi A',
            2 => 'Opsi B',
        ],
        'vendorB' => [
            3 => 'Opsi A',
            4 => 'Opsi B',
        ],
    ];

    private const CATERING_DISPLAY_NAMES = [
        'vendorA' => 'Vendor A',
        'vendorB' => 'Vendor B',
    ];
    private array $vendorDisplayCache = [];

    protected function normalizeCateringKey(string $catering): string
    {
        $sanitized = strtolower(trim($catering));
        $sanitized = str_replace([' ', '-', '_'], '', $sanitized);

        if ($sanitized === '') {
            throw new \InvalidArgumentException('Unsupported catering: ' . $catering);
        }

        $map = [
            'vendora' => 'vendorA',
            'vendorb' => 'vendorB',
        ];

        if (isset($map[$sanitized])) {
            return $map[$sanitized];
        }

        if (str_starts_with($sanitized, 'vendora')) {
            return 'vendorA';
        }

        if (str_starts_with($sanitized, 'vendorb')) {
            return 'vendorB';
        }

        throw new \InvalidArgumentException('Unsupported catering: ' . $catering);
    }

    protected function normalizeCatering(?string $catering): ?string
    {
        if ($catering === null || $catering === '') {
            return null;
        }

        return $this->normalizeCateringKey($catering);
    }

    protected function cateringDisplayName(?string $catering): string
    {
        if ($catering === null || $catering === '') {
            return 'Vendor';
        }

        try {
            $normalized = $this->normalizeCateringKey($catering);
        } catch (\InvalidArgumentException $e) {
            return ucfirst($catering);
        }

        if (!isset($this->vendorDisplayCache[$normalized])) {
            $label = Company::where('code', $normalized)->value('name');
            if (!$label) {
                $label = self::CATERING_DISPLAY_NAMES[$normalized] ?? ucfirst($normalized);
            }
            $this->vendorDisplayCache[$normalized] = $label;
        }

        return $this->vendorDisplayCache[$normalized];
    }

    protected function cateringCandidates(string $normalized): array
    {
        return match ($normalized) {
            'vendorA' => ['vendorA'],
            'vendorB' => ['vendorB'],
            default => [$normalized],
        };
    }

    protected function resolveMenuDate(string $weekCode, string $day): string
    {
        $day = strtoupper(trim($day));
        $offsets = ['MON' => 0, 'TUE' => 1, 'WED' => 2, 'THU' => 3];

        if (!isset($offsets[$day])) {
            throw new \InvalidArgumentException('Invalid day: ' . $day);
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $monday = Project::mondayFromMonthWeekCode($weekCode, $tz);

        return $monday->copy()->addDays($offsets[$day])->toDateString();
    }

    protected function isHoliday(Carbon $date): bool
    {
        // Only enforce holiday logic starting from 2026-01-01
        if ($date->lt(Carbon::create(2026, 1, 1, 0, 0, 0, $date->getTimezone()))) {
            return false;
        }

        return Holiday::whereDate('substitute_date', $date->toDateString())->exists();
    }

    private function imageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        return url('storage/' . ltrim($path, '/'));
    }

    protected function weekMenuCount(string $weekCode): int
    {
        return Menu::where('code', 'like', $weekCode . '-%')->count();
    }

    protected function resolveNextCreationWeek(Carbon $startMonday): array
    {
        $candidate = $startMonday->copy();
        $skipped = [];

        for ($i = 0; $i < 12; $i++) {
            $code = Project::monthWeekCode($candidate);
            $count = $this->weekMenuCount($code);

            if ($count < self::EXPECTED_MENUS_PER_WEEK) {
                return [
                    'monday' => $candidate,
                    'code' => $code,
                    'existing_count' => $count,
                    'skipped' => $skipped,
                ];
            }

            $skipped[] = [
                'code' => $code,
                'existing_count' => $count,
            ];

            $candidate = $candidate->copy()->addWeek();
        }

        $code = Project::monthWeekCode($candidate);

        return [
            'monday' => $candidate,
            'code' => $code,
            'existing_count' => $this->weekMenuCount($code),
            'skipped' => $skipped,
        ];
    }
    /**
     * Store image (compressed) and return relative path under public disk.
     */
    public function storeImage(UploadedFile $image, ?string $weekCode = null, ?string $day = null, ?string $catering = null): string
    {
        $segments = ['menus'];

        if ($weekCode) {
            $segments[] = strtolower($weekCode);
        }

        if ($day) {
            $segments[] = strtolower($day);
        }

        if ($catering) {
            $segments[] = strtolower($catering);
        }

        $directory = implode('/', $segments);

        $extension = strtolower($image->getClientOriginalExtension() ?: $image->guessExtension() ?: 'jpg');
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $compressed = $this->compressImage($image, $extension);

        $filename = Str::uuid()->toString() . '.' . $extension;
        $relativePath = trim($directory . '/' . $filename, '/');

        if ($compressed !== null) {
            Storage::disk('public')->put($relativePath, $compressed);
            return $relativePath;
        }

        return $image->storeAs($directory, $filename, 'public');
    }

    /**
     * Compress uploaded image using GD (if available).
     */
    private function compressImage(UploadedFile $image, string &$extension): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $contents = $image->get();
        $resource = @imagecreatefromstring($contents);
        if ($resource === false) {
            return null;
        }

        $mime = $image->getMimeType() ?: 'image/jpeg';
        $mime = strtolower($mime);

        ob_start();

        try {
            switch (true) {
                case str_contains($mime, 'png'):
                    imagesavealpha($resource, true);
                    imagealphablending($resource, false);
                    imagepng($resource, null, 6);
                    $extension = 'png';
                    break;
                case str_contains($mime, 'webp') && function_exists('imagewebp'):
                    imagealphablending($resource, true);
                    imagesavealpha($resource, true);
                    imagewebp($resource, null, 80);
                    $extension = 'webp';
                    break;
                default:
                    imageinterlace($resource, true);
                    imagejpeg($resource, null, 80);
                    $extension = 'jpg';
                    break;
            }
        } finally {
            imagedestroy($resource);
        }

        $data = ob_get_clean();

        if ($data === false || $data === '') {
            return null;
        }

        return $data;
    }

    private function cleanupReplacedImages(array $paths, ?string $currentPath = null): void
    {
        $unique = collect($paths)
            ->filter(fn($path) => $path && $path !== $currentPath)
            ->unique();

        foreach ($unique as $path) {
            if (!is_string($path)) {
                continue;
            }
            if (preg_match('/^https?:\/\//i', $path)) {
                continue;
            }

            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Create a weekly menu entry, handling optional image upload path.
     */
    public function createWeeklyMenu(array $data, UploadedFile $image): Menu
    {
        if (!empty($data['catering'])) {
            $data['catering'] = $this->normalizeCateringKey($data['catering']);
        }

        if (!empty($data['week_code']) && !empty($data['day'])) {
            $data['menu_date'] = $this->resolveMenuDate($data['week_code'], $data['day']);
        }

        $data['image'] = $this->storeImage(
            $image,
            $data['week_code'] ?? null,
            $data['day'] ?? null,
            $data['catering'] ?? null
        );

        return Menu::create($data);
    }

    /**
     * Create a pair of menus for a given day & catering using a single image.
     * Expects keys: week_code, day (MON/TUE/WED/THU), catering (Vendor A/B), name_1, name_2.
     */
    public function createCateringPair(array $data, UploadedFile $image): array
    {
        $weekCode = $data['week_code'];
        $day = strtoupper($data['day']);
        $catering = $this->normalizeCateringKey($data['catering']);

        $slotMap = self::CATERING_SLOT_MAP[$catering] ?? null;
        if ($slotMap === null) {
            throw new \InvalidArgumentException('Unsupported catering: ' . $catering);
        }

        $prefix = $weekCode . '-' . $day . '-';
        $allowedSeq = array_keys($slotMap);

        $menuDate = $this->resolveMenuDate($weekCode, $day);

        $existing = Menu::where('code', 'like', $prefix . '%')
            ->whereIn('catering', $this->cateringCandidates($catering))
            ->orderBy('code')
            ->get(['code']);

        $usedSeq = [];
        foreach ($existing as $menu) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(?P<seq>\d+)$/', $menu->code, $matches)) {
                $usedSeq[(int) $matches['seq']] = true;
            }
        }

        $missingSlots = array_values(array_filter($allowedSeq, fn($seq) => !isset($usedSeq[$seq])));
        if (empty($missingSlots)) {
            throw new \RuntimeException('Selected day already has a full set of ' . $this->cateringDisplayName($catering) . ' menus.');
        }

        $imagePath = $this->storeImage($image, $weekCode, $day, $catering);

        $created = [];
        foreach ($missingSlots as $slot) {
            $code = sprintf('%s%d', $prefix, $slot);
            $created[] = Menu::create([
                'code' => $code,
                'name' => $slotMap[$slot] ?? 'Opsi',
                'catering' => $catering,
                'menu_date' => $menuDate,
                'image' => $imagePath,
            ]);
        }

        return $created;
    }

    public function updateCateringImage(array $data, UploadedFile $image): array
    {
        $weekCode = $data['week_code'];
        $day = strtoupper($data['day']);
        $catering = $this->normalizeCateringKey($data['catering']);

        $slotMap = self::CATERING_SLOT_MAP[$catering] ?? null;
        if ($slotMap === null) {
            throw new \InvalidArgumentException('Unsupported catering: ' . $catering);
        }

        $prefix = $weekCode . '-' . $day . '-';
        $allowedSeq = array_keys($slotMap);
        $targetCodes = array_map(static fn($seq) => sprintf('%s%d', $prefix, $seq), $allowedSeq);

        $menus = Menu::whereIn('code', $targetCodes)
            ->whereIn('catering', $this->cateringCandidates($catering))
            ->get();

        if ($menus->isEmpty()) {
            throw new \RuntimeException('Menus are not available yet. Please create them first.');
        }

        $newImagePath = $this->storeImage($image, $weekCode, $day, $catering);
        $oldImages = [];

        foreach ($menus as $menu) {
            $oldImages[] = $menu->image;
            $updates = [
                'image' => $newImagePath,
                'catering' => $catering,
            ];

            if (empty($menu->menu_date)) {
                $updates['menu_date'] = $this->resolveMenuDate($weekCode, $day);
            }

            $menu->update($updates);
        }

        $this->cleanupReplacedImages($oldImages, $newImagePath);

        return Menu::whereIn('code', $targetCodes)
            ->whereIn('catering', $this->cateringCandidates($catering))
            ->orderBy('code')
            ->get()
            ->all();
    }

    /**
     * Format a menu for API responses (adds image_url if image exists).
     */
    public function formatMenu(Menu $menu): array
    {
        $catering = $menu->catering;
        $normalized = null;
        $label = null;

        if ($catering) {
            try {
                $normalized = $this->normalizeCateringKey($catering);
                $label = $this->cateringDisplayName($normalized);
            } catch (\InvalidArgumentException $e) {
                $label = ucfirst($catering);
            }
        }

        return [
            'code' => $menu->code,
            'name' => $menu->name,
            'image' => $menu->image,
            'image_url' => $this->imageUrl($menu->image),
            'menu_date' => $menu->menu_date,
            'catering' => $normalized ?? $catering,
            'catering_label' => $label,
        ];
    }

    /**
     * Clone current week's menus to next week (up to 4 per day) using format {weekCode}-DAY-N.
     * Returns number of created records.
     */
    public function generateNextWeekOptions(): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $nextMonday = $now->copy()->next(Carbon::MONDAY);
        $creation = $this->resolveNextCreationWeek($nextMonday);
        /** @var Carbon $targetMonday */
        $targetMonday = $creation['monday'];
        $nextWeekCode = $creation['code'];
        $currentMonday = $now->copy()->startOfWeek(Carbon::MONDAY);
        $currentWeekCode = Project::monthWeekCode($currentMonday);
        $days = ['MON', 'TUE', 'WED', 'THU'];
        $created = 0;

        $sourcePatterns = [
            'MON' => '/^' . preg_quote($currentWeekCode, '/') . '-MON-\d+$/i',
            'TUE' => '/^' . preg_quote($currentWeekCode, '/') . '-TUE-\d+$/i',
            'WED' => '/^' . preg_quote($currentWeekCode, '/') . '-WED-\d+$/i',
            'THU' => '/^' . preg_quote($currentWeekCode, '/') . '-THU-\d+$/i',
        ];

        $menusOrdered = Menu::orderBy('code')->get(['code', 'name', 'image', 'catering']);
        foreach ($days as $day) {
            $candidates = [];
            foreach ($menusOrdered as $m) {
                if (preg_match($sourcePatterns[$day], $m->code ?? '')) {
                    $candidates[] = $m;
                    if (count($candidates) >= 4) break;
                }
            }
            $seq = 1;
            foreach ($candidates as $base) {
                $newCode = sprintf('%s-%s-%d', $nextWeekCode, $day, $seq);
                if (!Menu::where('code', $newCode)->exists()) {
                    $menuDate = $this->resolveMenuDate($nextWeekCode, $day);
                    $catering = null;
                    if (!empty($base->catering)) {
                        try {
                            $catering = $this->normalizeCateringKey($base->catering);
                        } catch (\InvalidArgumentException $e) {
                            $catering = $base->catering;
                        }
                    }

                    $attributes = [
                        'code' => $newCode,
                        'name' => $base->name,
                        'image' => $base->image,
                        'menu_date' => $menuDate,
                    ];

                    if ($catering) {
                        $attributes['catering'] = $catering;
                    }

                    Menu::create($attributes);
                    $created++;
                }
                $seq++;
            }
        }
        return [
            'created' => $created,
            'target_week_code' => $nextWeekCode,
            'source_week_code' => $currentWeekCode,
        ];
    }

    public function getSelectionWindowStatus(): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $targetMonday = $now->copy()->next(Carbon::MONDAY);
        $weekCode = Project::monthWeekCode($targetMonday);

        return [
            'week_code' => $weekCode,
            'ready' => Project::isSelectionWindowReady($weekCode),
            'open' => Project::isSelectionWindowOpen($now),
            'timestamp' => $now->toIso8601String(),
        ];
    }

    public function getIndexData(User $user): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $role = $user->role ?? 'guest';
        $menus = collect();

        if ($role === 'vendor') {
            return $this->getVendorIndexData($user);
        }

        $targetMonday = $now->copy()->next(Carbon::MONDAY);

        $weekCode = Project::monthWeekCode($targetMonday);
        $currentMonday = $now->copy()->startOfWeek(Carbon::MONDAY);
        $currentWeekCode = Project::monthWeekCode($currentMonday);

        $creation = $this->resolveNextCreationWeek($targetMonday);
        /** @var Carbon $creationMonday */
        $creationMonday = $creation['monday'];
        $creationWeekCode = $creation['code'];
        $creationExistingCount = $creation['existing_count'];
        $creationSkippedWeeks = $creation['skipped'];
        $creationRangeStart = $creationMonday->toDateString();
        $creationRangeEnd = $creationMonday->copy()->addDays(3)->toDateString();

        $rangeStart = $targetMonday->toDateString();
        $rangeEnd = $targetMonday->copy()->addDays(3)->toDateString();

        // Build basic per-day structure (no selections/locks needed for admin/BM)
        $days = [];
        $cursor = $targetMonday->copy();
        foreach (['Mon', 'Tue', 'Wed', 'Thu'] as $idx => $label) {
            if ($idx > 0) {
                $cursor->addDay();
            }

            $isHoliday = $this->isHoliday($cursor);
            $days[$label] = [
                'date' => $cursor->toDateString(),
                'date_label' => $cursor->format('d, M Y'),
                'is_holiday' => $isHoliday,
            ];
        }

        // Build options for upcoming week, reusing same grouping as karyawan view
        $optionsByDay = [
            'Mon' => [],
            'Tue' => [],
            'Wed' => [],
            'Thu' => [],
        ];
        $optionsDetailedByDay = [
            'Mon' => [],
            'Tue' => [],
            'Wed' => [],
            'Thu' => [],
        ];
        $weekPatterns = [
            'Mon' => '/^' . preg_quote($weekCode, '/') . '-MON-\d+$/i',
            'Tue' => '/^' . preg_quote($weekCode, '/') . '-TUE-\d+$/i',
            'Wed' => '/^' . preg_quote($weekCode, '/') . '-WED-\d+$/i',
            'Thu' => '/^' . preg_quote($weekCode, '/') . '-THU-\d+$/i',
        ];

        if (in_array($role, ['admin', 'bm'])) {
            $selectionWindowReady = Project::isSelectionWindowReady($weekCode);
            $selectionWindowOpen = Project::isSelectionWindowOpen($now);
            $menusUpcoming = Menu::where('code', 'like', $weekCode . '-%')
                ->orderBy('code')
                ->get(['code', 'name', 'image', 'catering']);
            foreach ($menusUpcoming as $m) {
                foreach ($weekPatterns as $label => $regex) {
                    if (preg_match($regex, $m->code ?? '')) {
                        if (count($optionsByDay[$label]) < 4) {
                            $optionsByDay[$label][] = $this->formatMenu($m);
                        }

                        $normalizedCat = $this->normalizeCatering($m->catering);
                        $catKey = $normalizedCat ?? ($m->catering ?: 'unknown');
                        $catLabel = $this->cateringDisplayName($m->catering ?? '');

                        if (!isset($optionsDetailedByDay[$label][$catKey])) {
                            $optionsDetailedByDay[$label][$catKey] = [
                                'catering' => $catKey,
                                'catering_label' => $catLabel,
                                'image_url' => $this->imageUrl($m->image),
                                'menus' => [],
                            ];
                        }
                        if (empty($optionsDetailedByDay[$label][$catKey]['image_url']) && !empty($m->image)) {
                            $optionsDetailedByDay[$label][$catKey]['image_url'] = $this->imageUrl($m->image);
                        }
                        $optionsDetailedByDay[$label][$catKey]['menus'][] = [
                            'code' => $m->code,
                            'name' => $m->name,
                        ];
                        break;
                    }
                }
            }

            // Menus table: show only current week and next week menus if present
            $tableWeekCodes = collect([$currentWeekCode, $weekCode, $creationWeekCode])
                ->merge(collect($creationSkippedWeeks)->pluck('code'))
                ->filter()
                ->unique()
                ->values();

            $menus = Menu::where(function ($q) use ($tableWeekCodes) {
                $tableWeekCodes->each(function ($code, $index) use ($q) {
                    if ($index === 0) {
                        $q->where('code', 'like', $code . '-%');
                    } else {
                        $q->orWhere('code', 'like', $code . '-%');
                    }
                });
            })
                ->orderBy('code')
                ->get(['code', 'name', 'image']);

            return [
                'menus' => $menus,
                'weekCode' => $weekCode,
                'creationWeekCode' => $creationWeekCode,
                'creationRangeStart' => $creationRangeStart,
                'creationRangeEnd' => $creationRangeEnd,
                'creationExistingCount' => $creationExistingCount,
                'creationSkippedWeeks' => $creationSkippedWeeks,
                'creationAutoAdvanceWeeks' => count($creationSkippedWeeks),
                'tableWeekCodes' => $tableWeekCodes,
                'optionsByDay' => $optionsByDay,
                'optionsDetailedByDay' => $optionsDetailedByDay,
                'days' => $days,
                'rangeStart' => $rangeStart,
                'rangeEnd' => $rangeEnd,
                'selectionWindowReady' => $selectionWindowReady,
                'selectionWindowOpen' => $selectionWindowOpen,
                'vendorLabels' => $this->vendorLabels(),
            ];
        }

        // Karyawan data
        $targetMonday = $now->copy()->next(Carbon::MONDAY);
        $weekCode = Project::monthWeekCode($targetMonday);
        $rangeStart = $targetMonday->toDateString();
        $rangeEnd = $targetMonday->copy()->addDays(3)->toDateString();
        $windowOpen = Project::isSelectionWindowOpen($now);
        $windowReady = Project::isSelectionWindowReady($weekCode);

        $existing = ChosenMenu::where('chosen_by', $user->id)
            ->whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->get()
            ->keyBy('chosen_for_day');

        $days = [];
        $cursor = Carbon::parse($rangeStart, $tz);
        for ($i = 0; $i < 4; $i++) {
            $date = $cursor->toDateString();

            $isHoliday = $this->isHoliday($cursor);
            $record = $existing->get($date);
            // Suppress showing preselected menus before window opens (unless locked after export)
            $selectedValue = null;
            $isLocked = false;
            if ($record) {
                $isLocked = (bool) $record->is_locked;
                if ($windowOpen || $isLocked) {
                    $selectedValue = $record->menu_code;
                }
            }
            $days[$cursor->format('D')] = [
                'date' => $date,
                'date_label' => $cursor->format('d, M Y'),
                'selected' => $isHoliday ? null : $selectedValue,
                'locked' => $isLocked,
                'is_holiday' => $isHoliday,
            ];
            $cursor->addDay();
        }
        $pendingCount = collect($days)
            ->filter(fn($d) => empty($d['selected']) && empty($d['is_holiday']))
            ->count();

        // Build options by day for THIS week (single format) using {weekCode}-DAY-N
        $optionsByDay = [
            'Mon' => [],
            'Tue' => [],
            'Wed' => [],
            'Thu' => [],
        ];
        $optionsDetailedByDay = [
            'Mon' => [],
            'Tue' => [],
            'Wed' => [],
            'Thu' => [],
        ];
        $weekPatterns = [
            'Mon' => '/^' . preg_quote($weekCode, '/') . '-MON-\d+$/i',
            'Tue' => '/^' . preg_quote($weekCode, '/') . '-TUE-\d+$/i',
            'Wed' => '/^' . preg_quote($weekCode, '/') . '-WED-\d+$/i',
            'Thu' => '/^' . preg_quote($weekCode, '/') . '-THU-\d+$/i',
        ];
        $menusUpcoming = Menu::where('code', 'like', $weekCode . '-%')
            ->orderBy('code')
            ->get(['code', 'name', 'image', 'catering']);
        $menuDetails = [];
        foreach ($menusUpcoming as $m) {
            foreach ($weekPatterns as $label => $regex) {
                if (preg_match($regex, $m->code ?? '')) {
                    if (count($optionsByDay[$label]) < 4) {
                        // Karyawan view expects mapping code => name
                        $optionsByDay[$label][$m->code] = $m->name;
                    }
                    // Build grouped per-catering entries (two menus share one card)
                    $normalizedCat = $this->normalizeCatering($m->catering);
                    $catKey = $normalizedCat ?? ($m->catering ?: 'unknown');
                    $catLabel = $this->cateringDisplayName($m->catering ?? '');

                    if (!isset($optionsDetailedByDay[$label][$catKey])) {
                        $optionsDetailedByDay[$label][$catKey] = [
                            'catering' => $catKey,
                            'catering_label' => $catLabel,
                            'image_url' => $this->imageUrl($m->image),
                            'menus' => [],
                        ];
                    }
                    if (empty($optionsDetailedByDay[$label][$catKey]['image_url']) && !empty($m->image)) {
                        $optionsDetailedByDay[$label][$catKey]['image_url'] = $this->imageUrl($m->image);
                    }
                    $optionsDetailedByDay[$label][$catKey]['menus'][] = [
                        'code' => $m->code,
                        'name' => $m->name,
                    ];
                    break;
                }
            }
            // Collect detailed info for JS preview
            $menuDetails[$m->code] = [
                'code' => $m->code,
                'name' => $m->name,
                'catering' => $this->normalizeCatering($m->catering) ?? $m->catering,
                'catering_label' => $this->cateringDisplayName($m->catering ?? ''),
                'image_url' => $this->imageUrl($m->image),
            ];
        }
        // No fallback; missing options must be created by admin/BM.

        return [
            'menus' => $menus,
            'weekCode' => $weekCode,
            'windowOpen' => $windowOpen,
            'windowReady' => $windowReady,
            'days' => $days,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'pendingCount' => $pendingCount,
            'optionsByDay' => $optionsByDay,
            'optionsDetailedByDay' => $optionsDetailedByDay,
            'menuDetails' => $menuDetails,
            'vendorLabels' => $this->vendorLabels(),
        ];
    }

    public function saveKaryawanSelections(User $user, string $weekCode, array $choices, string $tz): array
    {
        $monday = Project::mondayFromMonthWeekCode($weekCode, $tz);
        $dayOffsets = ['Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3];
        $allowedDates = [];
        foreach ($dayOffsets as $label => $offset) {
            $allowedDates[$monday->copy()->addDays($offset)->toDateString()] = $label;
        }

        $dateToMenu = [];
        foreach ($choices as $date => $menuCode) {
            if (!$menuCode) {
                continue;
            }

            $normalizedDate = Carbon::parse($date, $tz)->toDateString();
            if (!isset($allowedDates[$normalizedDate])) {
                throw new \RuntimeException('Invalid day selected.');
            }

            $label = strtoupper($allowedDates[$normalizedDate]);
            $expectedPrefix = sprintf('%s-%s-', $weekCode, $label);

            $menu = Menu::where('code', $menuCode)->first();
            if (!$menu || !Str::startsWith(strtoupper($menu->code ?? ''), strtoupper($expectedPrefix))) {
                throw new \RuntimeException('Invalid menu selected.');
            }

            $dateToMenu[$normalizedDate] = $menu->code;
        }

        if (empty($dateToMenu)) {
            throw new \RuntimeException('No valid selections provided.');
        }

        $rangeStart = array_key_first($allowedDates);
        $rangeEnd = array_key_last($allowedDates);

        $existing = ChosenMenu::where('chosen_by', $user->id)
            ->whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->get()
            ->keyBy('chosen_for_day');

        $saved = [];
        foreach ($dateToMenu as $date => $menuCode) {
            $row = $existing->get($date);
            if ($row && $row->is_locked) {
                continue;
            }

            $entry = $row ?: new ChosenMenu([
                'code' => (string) Str::orderedUuid(),
                'chosen_by' => $user->id,
                'chosen_for_day' => $date,
            ]);
            $entry->menu_code = $menuCode;
            $entry->is_locked = false;
            $entry->chosen_at = Carbon::now($tz);
            $entry->save();
            $saved[] = $entry->chosen_for_day;
        }

        return $saved;
    }

    protected function resolveVendorCatering(User $vendor): string
    {
        $code = (string) $vendor->company_code;

        try {
            return $this->normalizeCateringKey($code);
        } catch (\InvalidArgumentException $e) {
            $sanitized = strtolower(trim($code));
            if (str_starts_with($sanitized, 'vendora')) {
                return 'vendorA';
            }
            if (str_starts_with($sanitized, 'vendorb')) {
                return 'vendorB';
            }
        }

        throw new \RuntimeException('Vendor tidak dikenali. Periksa kode perusahaan pengguna.');
    }

    public function vendorLabels(): array
    {
        return [
            'vendorA' => $this->cateringDisplayName('vendorA'),
            'vendorB' => $this->cateringDisplayName('vendorB'),
        ];
    }

    public function vendorSlotMap(string $catering): array
    {
        $normalized = $this->normalizeCateringKey($catering);
        $map = self::CATERING_SLOT_MAP[$normalized] ?? null;
        if ($map === null) {
            throw new \RuntimeException('Slot menu vendor belum dikonfigurasi.');
        }

        $slots = [];
        foreach ($map as $sequence => $label) {
            if (preg_match('/([A-Z])$/i', $label, $matches)) {
                $key = strtoupper($matches[1]);
            } else {
                $key = (string) $sequence;
            }
            $slots[$key] = (int) $sequence;
        }

        return $slots;
    }

    protected function vendorOptionLabel(string $option): string
    {
        return 'Opsi ' . strtoupper($option);
    }

    public function getVendorIndexData(User $vendor): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $catering = $this->resolveVendorCatering($vendor);

        $targetMonday = $now->copy()->next(Carbon::MONDAY);
        $creation = $this->resolveNextCreationWeek($targetMonday);
        /** @var Carbon $weekMonday */
        $weekMonday = $creation['monday'];
        $weekCode = $creation['code'];
        $rangeStart = $weekMonday->toDateString();
        $rangeEnd = $weekMonday->copy()->addDays(3)->toDateString();

        $slotMap = $this->vendorSlotMap($catering);

        $menus = Menu::where('code', 'like', $weekCode . '-%')
            ->where('catering', $catering)
            ->orderBy('code')
            ->get()
            ->keyBy('code');

        $dayOrder = ['Mon', 'Tue', 'Wed', 'Thu'];
        $vendorDays = [];
        foreach ($dayOrder as $offset => $label) {
            $date = $weekMonday->copy()->addDays($offset);
            $dayCode = strtoupper(substr($label, 0, 3));

            $isHoliday = $this->isHoliday($date);

            $options = [];
            if (!$isHoliday) {
                foreach ($slotMap as $optionKey => $sequence) {
                    $code = sprintf('%s-%s-%d', $weekCode, $dayCode, $sequence);
                    /** @var Menu|null $menu */
                    $menu = $menus->get($code);

                    $options[$optionKey] = [
                        'option' => $optionKey,
                        'label' => $this->vendorOptionLabel($optionKey),
                        'code' => $menu?->code,
                        'name' => $menu?->name ?? '',
                        'image' => $menu?->image,
                        'image_url' => $this->imageUrl($menu?->image),
                        'has_menu' => $menu !== null,
                    ];
                }
            }

            $vendorDays[$label] = [
                'label' => $label,
                'day_code' => $dayCode,
                'date' => $date->toDateString(),
                'date_label' => $date->format('D, d M Y'),
                'is_holiday' => $isHoliday,
                'options' => $options,
            ];
        }

        return [
            'weekCode' => $weekCode,
            'vendorWeekCode' => $weekCode,
            'vendorDayOrder' => $dayOrder,
            'vendorDays' => $vendorDays,
            'vendorCatering' => $catering,
            'vendorCateringLabel' => $this->cateringDisplayName($catering),
            'vendorRangeStart' => $rangeStart,
            'vendorRangeEnd' => $rangeEnd,
            'selectionWindowReady' => Project::isSelectionWindowReady($weekCode),
            'selectionWindowOpen' => Project::isSelectionWindowOpen($now),
            'vendorExistingCount' => $creation['existing_count'],
        ];
    }
    public function saveVendorMenu(User $vendor, array $payload, ?UploadedFile $image = null): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $catering = $this->resolveVendorCatering($vendor);

        $targetMonday = $now->copy()->next(Carbon::MONDAY);
        $creation = $this->resolveNextCreationWeek($targetMonday);
        /** @var Carbon $weekMonday */
        $weekMonday = $creation['monday'];
        $weekCode = $creation['code'];

        $day = strtoupper($payload['day'] ?? '');
        $nameA = trim((string) ($payload['name_a'] ?? ''));
        $nameB = trim((string) ($payload['name_b'] ?? ''));

        if ($nameA === '' || $nameB === '') {
            throw new \RuntimeException('Nama menu A dan B wajib diisi.');
        }

        if (!in_array($day, ['MON', 'TUE', 'WED', 'THU'], true)) {
            throw new \RuntimeException('Hari menu tidak valid.');
        }

        $slotMap = $this->vendorSlotMap($catering); // maps A/B => sequence
        if (!isset($slotMap['A'], $slotMap['B'])) {
            throw new \RuntimeException('Slot menu vendor belum lengkap dikonfigurasi.');
        }

        $seqA = $slotMap['A'];
        $seqB = $slotMap['B'];

        $codeA = sprintf('%s-%s-%d', $weekCode, $day, $seqA);
        $codeB = sprintf('%s-%s-%d', $weekCode, $day, $seqB);

        /** @var Menu|null $menuA */
        $menuA = Menu::where('code', $codeA)->first();
        /** @var Menu|null $menuB */
        $menuB = Menu::where('code', $codeB)->first();

        $menuACatering = $menuA ? $this->normalizeCatering($menuA->catering) : null;
        $menuBCatering = $menuB ? $this->normalizeCatering($menuB->catering) : null;

        if (($menuA && $menuACatering && $menuACatering !== $catering)
            || ($menuB && $menuBCatering && $menuBCatering !== $catering)
        ) {
            throw new \RuntimeException('Menu ini tidak terdaftar untuk vendor Anda.');
        }

        $anyExisting = $menuA || $menuB;
        if (!$anyExisting && !$image) {
            throw new \RuntimeException('Gambar wajib diunggah untuk menu baru.');
        }

        $imagePath = null;
        if ($image) {
            // gunakan path yang sama untuk kedua opsi (seperti admin/BM)
            $imagePath = $this->storeImage($image, $weekCode, $day, $catering);
        }

        $updatedMenus = [];
        $menuDate = $this->resolveMenuDate($weekCode, $day);

        if (!$menuA) {
            $menuA = Menu::create([
                'code' => $codeA,
                'name' => $nameA,
                'image' => $imagePath,
                'catering' => $catering,
                'menu_date' => $menuDate,
            ]);
        } else {
            $updateA = [
                'name' => $nameA,
                'catering' => $catering,
                'menu_date' => $menuDate,
            ];
            if ($imagePath !== null) {
                $oldImageA = $menuA->image;
                $updateA['image'] = $imagePath;
                if ($oldImageA && !preg_match('/^https?:\/\//i', $oldImageA) && $oldImageA !== $imagePath) {
                    Storage::disk('public')->delete($oldImageA);
                }
            }
            $menuA->fill($updateA)->save();
        }
        $updatedMenus[] = $menuA->refresh();

        if (!$menuB) {
            $menuB = Menu::create([
                'code' => $codeB,
                'name' => $nameB,
                'image' => $imagePath,
                'catering' => $catering,
                'menu_date' => $menuDate,
            ]);
        } else {
            $updateB = [
                'name' => $nameB,
                'catering' => $catering,
                'menu_date' => $menuDate,
            ];
            if ($imagePath !== null) {
                $oldImageB = $menuB->image;
                $updateB['image'] = $imagePath;
                if ($oldImageB && !preg_match('/^https?:\/\//i', $oldImageB) && $oldImageB !== $imagePath) {
                    Storage::disk('public')->delete($oldImageB);
                }
            }
            $menuB->fill($updateB)->save();
        }
        $updatedMenus[] = $menuB->refresh();

        return $updatedMenus;
    }
}
