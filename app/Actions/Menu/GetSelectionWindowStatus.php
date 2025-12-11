<?php

namespace App\Actions\Menu;

use App\Helpers\Project;
use Carbon\Carbon;

class GetSelectionWindowStatus extends MenuAction
{
    // Note: __invoke retrieves the status of the menu selection window for the upcoming week
    public function __invoke(): array
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
}
