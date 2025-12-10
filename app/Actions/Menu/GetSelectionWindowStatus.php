<?php

namespace App\Actions\Menu;

use App\Helpers\Project;
use Carbon\Carbon;

class GetSelectionWindowStatus extends MenuAction
{
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
