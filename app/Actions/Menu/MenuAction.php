<?php

namespace App\Actions\Menu;

use App\Helpers\Project;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class MenuAction
{
    protected const EXPECTED_MENUS_PER_WEEK = 16;

    protected const CATERING_SLOT_MAP = [
        'vendorA' => [
            1 => 'Opsi A',
            2 => 'Opsi B',
        ],
        'vendorB' => [
            3 => 'Opsi A',
            4 => 'Opsi B',
        ],
    ];

    protected const CATERING_DISPLAY_NAMES = [
        'vendorA' => 'Vendor A',
        'vendorB' => 'Vendor B',
    ];

    protected array $vendorDisplayCache = [];

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

    /**
     * @return string[]
     */
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
        if ($date->lt(Carbon::create(2026, 1, 1, 0, 0, 0, $date->getTimezone()))) {
            return false;
        }

        return Holiday::whereDate('substitute_date', $date->toDateString())->exists();
    }

    protected function imageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

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

    protected function storeImage(UploadedFile $image, ?string $weekCode = null, ?string $day = null, ?string $catering = null): string
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

    protected function compressImage(UploadedFile $image, string &$extension): ?string
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

    protected function cleanupReplacedImages(array $paths, ?string $currentPath = null): void
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

    protected function vendorLabels(): array
    {
        return [
            'vendorA' => $this->cateringDisplayName('vendorA'),
            'vendorB' => $this->cateringDisplayName('vendorB'),
        ];
    }

    protected function vendorSlotMap(string $catering): array
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

    protected function formatMenu(Menu $menu): array
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
}
