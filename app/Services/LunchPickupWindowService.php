<?php

namespace App\Services;

use App\Models\LunchPickupWindow;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LunchPickupWindowService
{
    public const DAY_LABELS = [
        'monday' => 'Senin',
        'tuesday' => 'Selasa',
        'wednesday' => 'Rabu',
        'thursday' => 'Kamis',
    ];

    public function getWindows(): Collection
    {
        $days = LunchPickupWindow::DAYS;

        $existing = LunchPickupWindow::whereIn('day_of_week', $days)->get()->keyBy('day_of_week');

        foreach ($days as $day) {
            if (!$existing->has($day)) {
                LunchPickupWindow::create(['day_of_week' => $day]);
            }
        }

        return LunchPickupWindow::whereIn('day_of_week', $days)
            ->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 ELSE 5 END")
            ->get();
    }

    public function getWindowsForForm(): array
    {
        return $this->getWindows()
            ->mapWithKeys(function (LunchPickupWindow $window) {
                return [
                    $window->day_of_week => [
                        'start_time' => $window->start_time_for_input,
                        'end_time' => $window->end_time_for_input,
                    ],
                ];
            })
            ->toArray();
    }

    public function updateWindows(array $windows): void
    {
        $days = LunchPickupWindow::DAYS;

        foreach ($days as $day) {
            $payload = Arr::get($windows, $day, []);

            $window = LunchPickupWindow::firstOrNew(['day_of_week' => $day]);

            $window->start_time = $this->formatTimeForStorage($payload['start_time'] ?? null);
            $window->end_time = $this->formatTimeForStorage($payload['end_time'] ?? null);

            $window->save();
        }
    }

    private function formatTimeForStorage(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', $value)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
