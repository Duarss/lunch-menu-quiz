<?php

namespace App\Actions\LunchPickupWindow;

use App\Models\LunchPickupWindow;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

abstract class LunchPickupWindowAction
{
    protected function ensureDefaultWindows(): void
    {
        $days = LunchPickupWindow::DAYS;
        $existing = LunchPickupWindow::whereIn('day_of_week', $days)->get()->keyBy('day_of_week');

        foreach ($days as $day) {
            if (!$existing->has($day)) {
                LunchPickupWindow::create(['day_of_week' => $day]);
            }
        }
    }

    protected function formatTimeForStorage(?string $value): ?string
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

    protected function extractPayload(array $windows, string $day): array
    {
        return Arr::get($windows, $day, []);
    }
}
