<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

class ChosenMenuService
{
    /**
     * Get deterministic 4 menu options for a given date (Mon-Thu).
     */
    public function getDayOptions(Carbon $day): Collection
    {
        $codes = DB::table('menus')->pluck('code');
        if ($codes->isEmpty()) {
            return collect();
        }

        $salt = $day->toDateString();
        return $codes->sortBy(fn($code) => hash_hmac('sha256', $code, $salt))
            ->take(min(4, $codes->count()))
            ->values();
    }

    /**
     * User chooses a menu for a given day with application-level guards.
     *
     * Rules:
     * - Only one choice per user per day
     * - Menu must be within the day's 4 options
     * - Can only choose during Wed–Fri in the week before the chosen day
     */
    public function choose(int $userId, string $menuCode, Carbon $chosenForDay, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $day = $chosenForDay->copy()->startOfDay();

        // Validate weekday (Mon-Thu)
        if (!in_array($day->dayOfWeekIso, [1, 2, 3, 4], true)) {
            throw new InvalidArgumentException('Chosen day must be Monday to Thursday.');
        }

        // Time window: previous week's Wed 00:00 to Fri 23:59 relative to chosen day
        $prevWeekMonday = $day->copy()->subWeek()->startOfDay();
        $windowStart = $prevWeekMonday->copy()->addDays(2)->startOfDay(); // Wed
        $windowEnd = $windowStart->copy()->addDays(2)->endOfDay();        // Fri
        if ($now->lt($windowStart) || $now->gt($windowEnd)) {
            throw new RuntimeException('Selection is only allowed Wed–Fri of prior week.');
        }

        // One per day guard
        $exists = DB::table('chosen_menus')
            ->where('chosen_by', $userId)
            ->whereDate('chosen_for_day', $day->toDateString())
            ->exists();
        if ($exists) {
            throw new RuntimeException('You have already chosen a menu for that day.');
        }

        // Must be within the day's options
        $options = $this->getDayOptions($day);
        if (!$options->contains($menuCode)) {
            throw new InvalidArgumentException('Menu is not available among today\'s options.');
        }

        $payload = [
            'code' => (string) Str::orderedUuid(),
            'menu_code' => $menuCode,
            'chosen_by' => $userId,
            'chosen_for_day' => $day->toDateString(),
            'chosen_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('chosen_menus')->insert($payload);
        return $payload;
    }
}
