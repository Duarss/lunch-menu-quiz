<?php

namespace App\Actions\LunchPickupWindow;

use App\Models\LunchPickupWindow;
use Illuminate\Support\Collection;

class GetLunchPickupWindows extends LunchPickupWindowAction
{
    // Note: __invoke retrieves and returns all lunch pickup windows, ensuring defaults exist
    public function __invoke(): Collection
    {
        $this->ensureDefaultWindows();

        return LunchPickupWindow::whereIn('day_of_week', LunchPickupWindow::DAYS)
            ->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 ELSE 5 END")
            ->get();
    }
}
