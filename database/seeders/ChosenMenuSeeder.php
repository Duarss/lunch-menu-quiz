<?php

namespace Database\Seeders;

use App\Helpers\Project;
use App\Models\ChosenMenu;
use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChosenMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Only seed demo picks in non-production environments
        if (!app()->environment(['local','development','testing'])) {
            return;
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $targetMonday = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $targetWeekCode = Project::monthWeekCode($targetMonday);

        // Gather source data constrained to the target week
        $menuCodes = Menu::where('code', 'like', $targetWeekCode . '-%')->pluck('code');
        $users = User::where('role', 'karyawan')->select(['id','username'])->get();

        if ($menuCodes->isEmpty() || $users->isEmpty()) {
            return; // Nothing to seed
        }

        // Compute the target week: next week's Monday..Thursday (Mon-Thu)
        $dayOffsets = ['Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3];
        $dayMap = ['Mon' => 'MON', 'Tue' => 'TUE', 'Wed' => 'WED', 'Thu' => 'THU'];
        $menuByDay = [];
        foreach ($dayMap as $label => $suffix) {
            $menuByDay[$label] = $menuCodes
                ->filter(fn ($code) => Str::startsWith($code, $targetWeekCode . '-' . $suffix . '-'))
                ->values();
        }

        // Selection window: one week before on Wednesday to Friday
        $prevWeekMonday = $targetMonday->copy()->subWeek();
        $selectionStart = $prevWeekMonday->copy()->addDays(2)->startOfDay(); // Wednesday 00:00:00
        $selectionEnd = $selectionStart->copy()->addDays(2)->endOfDay();     // Friday 23:59:59

        // Skip if already seeded for the target week (keep user choices)
        $existingCount = ChosenMenu::whereBetween('chosen_for_day', [
            $targetMonday->toDateString(),
            $targetMonday->copy()->addDays(3)->toDateString(),
        ])->count();
        if ($existingCount > 0) {
            return; // Do not overwrite real user selections
        }

        $inserts = [];
        foreach ($dayOffsets as $label => $offset) {
            $day = $targetMonday->copy()->addDays($offset);
            $dayOptions = $menuByDay[$label] ?? collect();
            if ($dayOptions->isEmpty()) {
                continue;
            }
            foreach ($users as $u) {
                // User chooses one from today's options
                $menuCode = $dayOptions->random();

                // Random chosen_at within selection window
                $randTs = random_int($selectionStart->timestamp, $selectionEnd->timestamp);
                $chosenAt = Carbon::createFromTimestamp($randTs, $tz);

                $inserts[] = [
                    'code' => (string) Str::orderedUuid(),
                    'menu_code' => $menuCode,
                    'chosen_by' => $u->id,
                    'chosen_for_day' => $day->toDateString(),
                    'chosen_at' => $chosenAt,
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        // Bulk insert for speed; bypass Eloquent timestamps/created_at
        if (!empty($inserts)) {
            ChosenMenu::insert($inserts);
        }
    }
}
