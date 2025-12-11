<?php

namespace App\Actions\LunchPickupWindow;

use App\Models\LunchPickupWindow;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

abstract class LunchPickupWindowAction
{
    // Note: ensureDefaultWindows creates default lunch pickup windows if they don't exist
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

    // Note: formatTimeForStorage converts time from 'H:i' to 'H:i:s' format for storage
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

    // Note: extractPayload retrieves the time window data for a specific day from the input array
    protected function extractPayload(array $windows, string $day): array
    {
        return Arr::get($windows, $day, []);
    }
}
