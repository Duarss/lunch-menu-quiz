<?php

namespace App\Actions\LunchPickupWindow;

use App\Models\LunchPickupWindow;

class UpdateLunchPickupWindows extends LunchPickupWindowAction
{
    // Note: __invoke updates lunch pickup windows based on the provided input array
    public function __invoke(array $windows): void
    {
        foreach (LunchPickupWindow::DAYS as $day) {
            $payload = $this->extractPayload($windows, $day);

            $window = LunchPickupWindow::firstOrNew(['day_of_week' => $day]);
            $window->start_time = $this->formatTimeForStorage($payload['start_time'] ?? null);
            $window->end_time = $this->formatTimeForStorage($payload['end_time'] ?? null);
            $window->save();
        }
    }
}
