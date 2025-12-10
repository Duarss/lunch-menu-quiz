<?php

namespace App\Actions\Menu;

use App\Helpers\Project;
use App\Models\Menu;
use Carbon\Carbon;

class GenerateNextWeekOptions extends MenuAction
{
    public function __invoke(): array
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
                    if (count($candidates) >= 4) {
                        break;
                    }
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
}
