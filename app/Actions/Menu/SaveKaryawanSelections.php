<?php

namespace App\Actions\Menu;

use App\Helpers\Project;
use App\Models\ChosenMenu;
use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SaveKaryawanSelections extends MenuAction
{
    // Note: __invoke saves the menu selections for a user for a given week
    public function __invoke(User $user, string $weekCode, array $choices, string $tz): array
    {
        $monday = Project::mondayFromMonthWeekCode($weekCode, $tz);
        $dayOffsets = ['Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3];
        $allowedDates = [];
        foreach ($dayOffsets as $label => $offset) {
            $allowedDates[$monday->copy()->addDays($offset)->toDateString()] = $label;
        }

        $dateToMenu = [];
        foreach ($choices as $date => $menuCode) {
            if (!$menuCode) {
                continue;
            }

            $normalizedDate = Carbon::parse($date, $tz)->toDateString();
            if (!isset($allowedDates[$normalizedDate])) {
                throw new \RuntimeException('Invalid day selected.');
            }

            $label = strtoupper($allowedDates[$normalizedDate]);
            $expectedPrefix = sprintf('%s-%s-', $weekCode, $label);

            $menu = Menu::where('code', $menuCode)->first();
            if (!$menu || !Str::startsWith(strtoupper($menu->code ?? ''), strtoupper($expectedPrefix))) {
                throw new \RuntimeException('Invalid menu selected.');
            }

            $dateToMenu[$normalizedDate] = $menu->code;
        }

        if (empty($dateToMenu)) {
            throw new \RuntimeException('No valid selections provided.');
        }

        $rangeStart = array_key_first($allowedDates);
        $rangeEnd = array_key_last($allowedDates);

        $existing = ChosenMenu::where('chosen_by', $user->id)
            ->whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->get()
            ->keyBy('chosen_for_day');

        $saved = [];
        foreach ($dateToMenu as $date => $menuCode) {
            $row = $existing->get($date);
            if ($row && $row->is_locked) {
                continue;
            }

            $entry = $row ?: new ChosenMenu([
                'code' => (string) Str::orderedUuid(),
                'chosen_by' => $user->id,
                'chosen_for_day' => $date,
            ]);
            $entry->menu_code = $menuCode;
            $entry->is_locked = false;
            $entry->chosen_at = Carbon::now($tz);
            $entry->save();
            $saved[] = $entry->chosen_for_day;
        }

        return $saved;
    }
}
